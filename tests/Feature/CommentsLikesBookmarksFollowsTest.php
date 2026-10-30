<?php

namespace Tests\Feature;

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Notifications\CommentReplyNotification;
use App\Notifications\NewCommentNotification;
use App\Notifications\NewFollowerNotification;
use App\Notifications\PostLikedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommentsLikesBookmarksFollowsTest extends TestCase
{
    use RefreshDatabase;

    protected User $author;
    protected User $reader;
    protected User $bannedUser;
    protected Post $post;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create([
            'name' => 'Alice Author',
            'username' => 'alice',
            'role' => UserRole::USER,
        ]);

        $this->reader = User::factory()->create([
            'name' => 'Bob Reader',
            'username' => 'bob',
            'role' => UserRole::USER,
        ]);

        $this->bannedUser = User::factory()->create([
            'name' => 'Bad Actor',
            'username' => 'badactor',
            'is_banned' => true,
        ]);

        $this->category = Category::factory()->create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);

        $this->post = Post::factory()->create([
            'user_id' => $this->author->id,
            'category_id' => $this->category->id,
            'title' => 'Mastering SQLite with Laravel',
            'slug' => 'mastering-sqlite-with-laravel',
            'status' => PostStatus::PUBLISHED,
            'published_at' => now()->subDay(),
            'likes_count' => 0,
            'comments_count' => 0,
        ]);
    }

    public function test_guest_cannot_write_comments_likes_bookmarks_follows_or_reports(): void
    {
        $this->post('/posts/mastering-sqlite-with-laravel/comments', ['body' => 'Nice!'])
            ->assertRedirect('/login');

        $this->post('/posts/mastering-sqlite-with-laravel/like')
            ->assertRedirect('/login');

        $this->post('/posts/mastering-sqlite-with-laravel/bookmark')
            ->assertRedirect('/login');

        $this->post('/users/alice/follow')
            ->assertRedirect('/login');

        $this->post('/reports', [
            'reportable_type' => 'post',
            'reportable_id' => $this->post->id,
            'reason' => 'spam',
        ])->assertRedirect('/login');
    }

    public function test_banned_user_is_blocked_from_write_actions(): void
    {
        $this->actingAs($this->bannedUser)
            ->post('/posts/mastering-sqlite-with-laravel/comments', ['body' => 'Spam comment'])
            ->assertForbidden();

        $this->actingAs($this->bannedUser)
            ->post('/posts/mastering-sqlite-with-laravel/like')
            ->assertForbidden();

        $this->actingAs($this->bannedUser)
            ->post('/posts/mastering-sqlite-with-laravel/bookmark')
            ->assertForbidden();

        $this->actingAs($this->bannedUser)
            ->post('/users/alice/follow')
            ->assertForbidden();
    }

    public function test_comments_are_threaded_max_2_levels_deep(): void
    {
        // 1. Root comment
        $this->actingAs($this->reader)
            ->post('/posts/mastering-sqlite-with-laravel/comments', [
                'body' => 'This is a **bold** root comment with `code`.',
            ])
            ->assertRedirect();

        $rootComment = Comment::where('post_id', $this->post->id)->whereNull('parent_id')->first();
        $this->assertNotNull($rootComment);
        $this->assertEquals(1, $this->post->fresh()->comments_count);
        $this->assertStringContainsString('<strong>bold</strong>', $rootComment->formatted_body);
        $this->assertStringContainsString('<code', $rootComment->formatted_body);

        // 2. Reply to root comment (Level 2)
        $replyUser = User::factory()->create(['username' => 'carol']);
        $this->actingAs($replyUser)
            ->post('/posts/mastering-sqlite-with-laravel/comments', [
                'body' => 'I agree with your root comment!',
                'parent_id' => $rootComment->id,
            ])
            ->assertRedirect();

        $level2Reply = Comment::where('parent_id', $rootComment->id)->first();
        $this->assertNotNull($level2Reply);
        $this->assertEquals($rootComment->id, $level2Reply->parent_id);
        $this->assertEquals(2, $this->post->fresh()->comments_count);

        // 3. Replying to a level 2 reply is flattened to rootComment (preserving max 2 visual levels)
        $thirdUser = User::factory()->create(['username' => 'dave']);
        $this->actingAs($thirdUser)
            ->post('/posts/mastering-sqlite-with-laravel/comments', [
                'body' => 'Replying to Carol, but kept at level 2!',
                'parent_id' => $level2Reply->id,
            ])
            ->assertRedirect();

        $flattenedReply = Comment::where('body', 'Replying to Carol, but kept at level 2!')->first();
        $this->assertNotNull($flattenedReply);
        // Parent ID should be rootComment->id, NOT level2Reply->id
        $this->assertEquals($rootComment->id, $flattenedReply->parent_id);
        $this->assertEquals(3, $this->post->fresh()->comments_count);
    }

    public function test_comment_editing_window_within_15_minutes_and_forbidden_after(): void
    {
        $comment = Comment::create([
            'post_id' => $this->post->id,
            'user_id' => $this->reader->id,
            'body' => 'Initial comment',
            'status' => CommentStatus::VISIBLE,
        ]);
        $comment->forceFill(['created_at' => now()->subMinutes(10)])->save();

        // Within 15 minutes: reader can update
        $this->actingAs($this->reader)
            ->put("/comments/{$comment->id}", ['body' => 'Updated comment within 10 minutes'])
            ->assertRedirect();

        $this->assertEquals('Updated comment within 10 minutes', $comment->fresh()->body);

        // Simulate 20 minutes passed (outside 15-minute window)
        $comment->forceFill(['created_at' => now()->subMinutes(20)])->save();

        $this->actingAs($this->reader)
            ->put("/comments/{$comment->id}", ['body' => 'Attempting edit after 20 minutes'])
            ->assertForbidden();

        $this->assertEquals('Updated comment within 10 minutes', $comment->fresh()->body);

        // Admin can still update
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->actingAs($admin)
            ->put("/comments/{$comment->id}", ['body' => 'Moderator edit'])
            ->assertRedirect();

        $this->assertEquals('Moderator edit', $comment->fresh()->body);
    }

    public function test_comment_soft_delete_preserves_thread_and_decrements_count_atomically(): void
    {
        $this->post->update(['comments_count' => 1]);

        $comment = Comment::create([
            'post_id' => $this->post->id,
            'user_id' => $this->reader->id,
            'body' => 'Comment to be deleted',
            'status' => CommentStatus::VISIBLE,
        ]);

        $this->actingAs($this->reader)
            ->delete("/comments/{$comment->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
        $this->assertEquals(0, $this->post->fresh()->comments_count);

        $trashedComment = Comment::withTrashed()->find($comment->id);
        $this->assertTrue($trashedComment->trashed);
        $this->assertStringContainsString('[Comment deleted by user]', $trashedComment->formatted_body);
    }

    public function test_atomic_likes_toggle_and_batch_notifications(): void
    {
        Notification::fake();

        // 1. Bob likes Alice's post
        $response = $this->actingAs($this->reader)
            ->postJson("/posts/{$this->post->slug}/like")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'liked' => true,
                'likes_count' => 1,
            ]);

        $this->assertEquals(1, $this->post->fresh()->likes_count);
        $this->assertDatabaseHas('likes', [
            'user_id' => $this->reader->id,
            'post_id' => $this->post->id,
        ]);

        Notification::assertSentTo($this->author, PostLikedNotification::class);

        // 2. Unlike post
        $this->actingAs($this->reader)
            ->postJson("/posts/{$this->post->slug}/like")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'liked' => false,
                'likes_count' => 0,
            ]);

        $this->assertEquals(0, $this->post->fresh()->likes_count);
        $this->assertDatabaseMissing('likes', [
            'user_id' => $this->reader->id,
            'post_id' => $this->post->id,
        ]);
    }

    public function test_batched_like_notifications_update_message_for_multiple_likers(): void
    {
        $liker1 = User::factory()->create(['name' => 'Charlie Day']);
        $liker2 = User::factory()->create(['name' => 'Dennis Reynolds']);
        $liker3 = User::factory()->create(['name' => 'Dee Reynolds']);

        // First like creates new notification
        $this->actingAs($liker1)->post("/posts/{$this->post->slug}/like");
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->author->id,
            'type' => PostLikedNotification::class,
        ]);

        $notif = $this->author->unreadNotifications()->first();
        $this->assertStringContainsString('Charlie Day liked your story', $notif->data['message']);

        // Second like batches into the same notification
        $this->actingAs($liker2)->post("/posts/{$this->post->slug}/like");
        $notif->refresh();
        $this->assertStringContainsString('Charlie Day and Dennis Reynolds liked your story', $notif->data['message']);
        $this->assertCount(2, $notif->data['liker_ids']);

        // Third like batches with "others" count
        $this->actingAs($liker3)->post("/posts/{$this->post->slug}/like");
        $notif->refresh();
        $this->assertStringContainsString('Charlie Day and 2 others liked your story', $notif->data['message']);
        $this->assertCount(3, $notif->data['liker_ids']);
    }

    public function test_bookmarks_toggle_and_reading_list_page(): void
    {
        // Toggle bookmark
        $this->actingAs($this->reader)
            ->postJson("/posts/{$this->post->slug}/bookmark")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'bookmarked' => true,
            ]);

        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $this->reader->id,
            'post_id' => $this->post->id,
        ]);

        // Access /bookmarks page
        $this->actingAs($this->reader)
            ->get('/bookmarks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Bookmarks/Index')
                ->has('posts.data', 1)
                ->where('posts.data.0.id', $this->post->id)
            );

        // Unbookmark
        $this->actingAs($this->reader)
            ->postJson("/posts/{$this->post->slug}/bookmark")
            ->assertOk()
            ->assertJson(['bookmarked' => false]);

        $this->assertDatabaseMissing('bookmarks', [
            'user_id' => $this->reader->id,
            'post_id' => $this->post->id,
        ]);
    }

    public function test_follow_toggle_following_page_and_new_follower_notification(): void
    {
        // Cannot follow self
        $this->actingAs($this->author)
            ->post("/users/{$this->author->username}/follow")
            ->assertStatus(400);

        // Follow Alice
        $this->actingAs($this->reader)
            ->postJson("/users/{$this->author->username}/follow")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'is_following' => true,
                'followers_count' => 1,
            ]);

        $this->assertTrue($this->reader->isFollowing($this->author));

        // Follow notification created for Alice
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->author->id,
            'type' => NewFollowerNotification::class,
        ]);

        // Following management page
        $this->actingAs($this->reader)
            ->get('/following')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Following/Index')
                ->has('following.data', 1)
                ->where('following.data.0.id', $this->author->id)
                ->where('stats.following_count', 1)
            );

        // Unfollow
        $this->actingAs($this->reader)
            ->postJson("/users/{$this->author->username}/follow")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'is_following' => false,
                'followers_count' => 0,
            ]);

        $this->assertFalse($this->reader->isFollowing($this->author));
    }

    public function test_notifications_page_and_marking_as_read(): void
    {
        $this->author->notify(new NewFollowerNotification($this->reader));

        $this->assertEquals(1, $this->author->unreadNotifications()->count());

        $notification = $this->author->unreadNotifications()->first();

        // Check index page
        $this->actingAs($this->author)
            ->get('/notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notifications/Index')
                ->has('notifications.data', 1)
                ->where('unreadCount', 1)
            );

        // Mark single as read
        $this->actingAs($this->author)
            ->postJson("/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJson(['success' => true, 'unreadCount' => 0]);

        $this->assertNotNull($notification->fresh()->read_at);

        // Mark all as read
        $this->author->notify(new NewFollowerNotification($this->reader));
        $this->assertEquals(1, $this->author->unreadNotifications()->count());

        $this->actingAs($this->author)
            ->postJson('/notifications/read-all')
            ->assertOk()
            ->assertJson(['success' => true, 'unreadCount' => 0]);

        $this->assertEquals(0, $this->author->unreadNotifications()->count());
    }

    public function test_reports_can_be_submitted_for_posts_and_comments(): void
    {
        $comment = Comment::create([
            'post_id' => $this->post->id,
            'user_id' => $this->author->id,
            'body' => 'Controversial comment',
            'status' => CommentStatus::VISIBLE,
        ]);

        // Report Post
        $this->actingAs($this->reader)
            ->postJson('/reports', [
                'reportable_type' => 'post',
                'reportable_id' => $this->post->id,
                'reason' => 'misinformation',
                'notes' => 'The claims need fact-checking.',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('reports', [
            'user_id' => $this->reader->id,
            'reportable_type' => Post::class,
            'reportable_id' => $this->post->id,
            'reason' => 'misinformation',
            'status' => 'pending',
        ]);

        // Report Comment
        $this->actingAs($this->reader)
            ->postJson('/reports', [
                'reportable_type' => 'comment',
                'reportable_id' => $comment->id,
                'reason' => 'harassment',
            ])
            ->assertOk();

        $this->assertDatabaseHas('reports', [
            'user_id' => $this->reader->id,
            'reportable_type' => Comment::class,
            'reportable_id' => $comment->id,
            'reason' => 'harassment',
        ]);

        // Duplicate report doesn't duplicate record
        $this->actingAs($this->reader)
            ->postJson('/reports', [
                'reportable_type' => 'post',
                'reportable_id' => $this->post->id,
                'reason' => 'spam',
            ])
            ->assertOk();

        $this->assertEquals(1, Report::where('user_id', $this->reader->id)->where('reportable_type', Post::class)->count());
    }
}
