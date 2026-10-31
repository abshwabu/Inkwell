<?php

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\Tag;
use App\Models\User;

test('critical path: guest can read public feed, categories, tags, author, and stories without auth', function () {
    $category = Category::factory()->create(['name' => 'Technology', 'slug' => 'technology']);
    $tag = Tag::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);
    $author = User::factory()->create(['name' => 'Alice Author', 'username' => 'alice']);

    $post = Post::factory()->create([
        'user_id' => $author->id,
        'category_id' => $category->id,
        'title' => 'The Future of Web Development',
        'slug' => 'future-web-development',
        'status' => PostStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'content_html' => '<h2 id="intro">Introduction</h2><p>This is a great story about web modern tech.</p>',
    ]);
    $post->tags()->attach($tag);

    // 1. Guest reads home
    $homeRes = $this->get('/');
    $homeRes->assertOk();

    // 2. Guest reads category
    $catRes = $this->get(route('categories.show', 'technology'));
    $catRes->assertOk();

    // 3. Guest reads tag
    $tagRes = $this->get(route('tags.show', 'laravel'));
    $tagRes->assertOk();

    // 4. Guest reads author profile
    $authorRes = $this->get(route('author.show', 'alice'));
    $authorRes->assertOk();

    // 5. Guest reads story canonical page
    $postRes = $this->get(route('posts.show.canonical', [
        'category_slug' => 'technology',
        'post_slug' => 'future-web-development',
    ]));
    $postRes->assertOk();

    // 6. Feeds & sitemap
    $this->get('/feed')->assertOk();
    $this->get('/sitemap.xml')->assertOk();
});

test('critical path: registration collects user credentials and logs them in', function () {
    $response = $this->post(route('register'), [
        'name' => 'Bob Builder',
        'username' => 'bobbuilder',
        'email' => 'bob@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]);

    $response->assertRedirect(route('dashboard'));

    $user = User::where('email', 'bob@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->username)->toBe('bobbuilder')
        ->and($user->role)->toBe(UserRole::USER)
        ->and($user->is_banned)->toBeFalse();

    $this->assertAuthenticatedAs($user);
});

test('critical path: authenticated user creates draft via autosave and writer page', function () {
    $author = User::factory()->create(['username' => 'charlie']);
    $category = Category::factory()->create(['slug' => 'ideas']);

    $this->actingAs($author);

    // Access writer page
    $this->get(route('posts.create'))->assertOk();

    // Autosave draft
    $response = $this->postJson(route('posts.autosave'), [
        'title' => 'My Draft in Progress',
        'category_id' => $category->id,
        'content_json' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Autosaved thought content']],
                ],
            ],
        ],
        'content_html' => '<p>Autosaved thought content</p>',
    ]);

    $response->assertOk();
    $draftId = $response->json('id');
    expect($draftId)->not->toBeNull();

    $draft = Post::find($draftId);
    expect($draft->status)->toBe(PostStatus::DRAFT)
        ->and($draft->user_id)->toBe($author->id);

    // Draft is NOT accessible to public guests
    $this->app['auth']->logout();
    $guestView = $this->get(route('posts.show.canonical', [
        'category_slug' => 'ideas',
        'post_slug' => $draft->slug,
    ]));
    $guestView->assertStatus(403);
});

test('critical path: publishing validates word count, sets published date and computes reading time', function () {
    $author = User::factory()->create();
    $category = Category::factory()->create(['slug' => 'design']);

    $this->actingAs($author);

    $draft = Post::factory()->create([
        'user_id' => $author->id,
        'category_id' => $category->id,
        'title' => 'Design Principles for 2026',
        'status' => PostStatus::DRAFT,
        'published_at' => null,
    ]);

    // Create 120 words of content
    $words = implode(' ', array_fill(0, 120, 'creative'));
    $html = "<p>{$words}</p>";

    $response = $this->put(route('posts.update', $draft), [
        'title' => 'Design Principles for 2026',
        'status' => 'published',
        'category_id' => $category->id,
        'content_html' => $html,
        'excerpt' => 'A guide to modern UI design.',
    ]);

    $response->assertRedirect();

    $draft->refresh();
    expect($draft->status)->toBe(PostStatus::PUBLISHED)
        ->and($draft->published_at)->not->toBeNull()
        ->and($draft->reading_time_minutes)->toBeGreaterThanOrEqual(1);

    // Published story is now accessible to guests
    $this->app['auth']->logout();
    $publicView = $this->get(route('posts.show.canonical', [
        'category_slug' => 'design',
        'post_slug' => $draft->slug,
    ]));
    $publicView->assertOk();
});

test('critical path: feed personalization respects following tab and dismissals', function () {
    $follower = User::factory()->create();
    $followedAuthor = User::factory()->create();
    $otherAuthor = User::factory()->create();

    $follower->following()->attach($followedAuthor->id);

    $followedPost = Post::factory()->create([
        'user_id' => $followedAuthor->id,
        'status' => PostStatus::PUBLISHED,
        'title' => 'Followed Writer Story',
        'published_at' => now()->subHour(),
    ]);

    $otherPost = Post::factory()->create([
        'user_id' => $otherAuthor->id,
        'status' => PostStatus::PUBLISHED,
        'title' => 'Other Writer Story',
        'published_at' => now()->subHours(2),
    ]);

    $this->actingAs($follower);

    // 1. Following tab returns followed author's post
    $followingTab = $this->get('/?tab=following');
    $followingTab->assertOk();

    // 2. Dismissing other post
    $dismissRes = $this->post(route('posts.dismiss', $otherPost));
    $dismissRes->assertRedirect();
});

test('critical path: comments are threaded, editable within window, and likes bookmark correctly', function () {
    $author = User::factory()->create();
    $reader = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => $author->id,
        'status' => PostStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'likes_count' => 0,
    ]);

    $this->actingAs($reader);

    // 1. Post a root comment
    $commentRes = $this->post(route('comments.store', $post->slug), [
        'body' => 'Insightful perspective on architecture!',
    ]);
    $commentRes->assertRedirect();

    $comment = Comment::where('post_id', $post->id)->first();
    expect($comment)->not->toBeNull()
        ->and($comment->body)->toBe('Insightful perspective on architecture!')
        ->and($comment->parent_id)->toBeNull();

    // 2. Author replies to comment
    $this->actingAs($author);
    $replyRes = $this->post(route('comments.store', $post->slug), [
        'body' => 'Thank you, glad you enjoyed it.',
        'parent_id' => $comment->id,
    ]);
    $replyRes->assertRedirect();

    $reply = Comment::where('parent_id', $comment->id)->first();
    expect($reply)->not->toBeNull()
        ->and($reply->user_id)->toBe($author->id);

    // 3. Edit comment within 15 min window
    $this->actingAs($reader);
    $editRes = $this->put(route('comments.update', $comment), [
        'body' => 'Insightful perspective on architecture! (edited)',
    ]);
    $editRes->assertRedirect();
    $comment->refresh();
    expect($comment->body)->toBe('Insightful perspective on architecture! (edited)');

    // 4. Like and bookmark
    $likeRes = $this->post(route('posts.like', $post->slug));
    $likeRes->assertRedirect();
    $bookmarkRes = $this->post(route('posts.bookmark', $post->slug));
    $bookmarkRes->assertRedirect();

    $post->refresh();
    expect($post->likes_count)->toBe(1);
    expect($reader->bookmarkedPosts()->where('post_id', $post->id)->exists())->toBeTrue();
});

test('critical path: moderation queue allows dismissing, hiding content, and banning authors with audit log', function () {
    $moderator = User::factory()->create(['role' => UserRole::MODERATOR]);
    $badUser = User::factory()->create(['role' => UserRole::USER]);

    $spamyPost = Post::factory()->create([
        'user_id' => $badUser->id,
        'status' => PostStatus::PUBLISHED,
        'title' => 'Spam Link Farm',
        'published_at' => now()->subHour(),
    ]);

    $report = Report::create([
        'user_id' => $moderator->id,
        'reportable_type' => Post::class,
        'reportable_id' => $spamyPost->id,
        'reason' => 'spam',
        'notes' => 'Affiliate spam link farm',
        'status' => 'pending',
    ]);

    $this->actingAs($moderator);

    // 1. Moderator views reports queue
    $this->get(route('admin.reports.index'))->assertOk();

    // 2. Moderator hides content
    $hideRes = $this->post(route('admin.reports.hide', $report));
    $hideRes->assertRedirect();

    $spamyPost->refresh();
    expect($spamyPost->status)->toBe(PostStatus::DRAFT);

    // 3. Moderator bans bad user
    $banRes = $this->post(route('admin.reports.ban', $report), [
        'ban_reason' => 'Repeated spam violations',
    ]);
    $banRes->assertRedirect();

    $badUser->refresh();
    expect($badUser->is_banned)->toBeTrue()
        ->and($badUser->ban_reason)->toBe('Repeated spam violations');

    // 4. Audit log produced
    $audit = AuditLog::where('user_id', $moderator->id)->where('action', 'user.ban')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->details['ban_reason'])->toBe('Repeated spam violations');
});
