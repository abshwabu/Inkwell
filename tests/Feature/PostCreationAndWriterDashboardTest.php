<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostCreationAndWriterDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'role' => UserRole::USER,
            'is_banned' => false,
        ]);

        $this->otherUser = User::factory()->create([
            'role' => UserRole::USER,
            'is_banned' => false,
        ]);

        $this->category = Category::factory()->create();
    }

    /**
     * Helper to generate a text of exact word count.
     */
    protected function generateWords(int $count): string
    {
        $words = [];
        for ($i = 1; $i <= $count; $i++) {
            $words[] = 'word' . $i;
        }
        return implode(' ', $words);
    }

    /**
     * Test /write and /posts/{id}/edit page renders for author.
     */
    public function test_distraction_free_write_and_edit_pages_render(): void
    {
        // /write
        $response = $this->actingAs($this->user)->get(route('posts.create'));
        $response->assertOk()
            ->assertInertia(fn ($page) => $page->component('Posts/Create')
                ->has('categories')
                ->has('availableTags')
            );

        // Create draft
        $post = Post::factory()->draft()->create([
            'user_id' => $this->user->id,
            'title' => 'My Draft Story',
        ]);

        // /posts/{id}/edit
        $editResponse = $this->actingAs($this->user)->get(route('posts.edit', $post->id));
        $editResponse->assertOk()
            ->assertInertia(fn ($page) => $page->component('Posts/Edit')
                ->where('post.id', $post->id)
                ->has('categories')
                ->has('availableTags')
            );
    }

    /**
     * Test full lifecycle: draft -> scheduled -> published -> archived.
     */
    public function test_full_lifecycle_draft_scheduled_published_archived(): void
    {
        $content = '<p>' . $this->generateWords(120) . '</p>';

        // 1. Save as Draft
        $draftResponse = $this->actingAs($this->user)->post(route('posts.store'), [
            'title' => 'Lifecycle Story',
            'status' => 'draft',
            'category_id' => $this->category->id,
            'content_html' => '<p>Short draft content</p>',
        ]);
        $draftResponse->assertRedirect(route('dashboard'));

        $post = Post::where('title', 'Lifecycle Story')->firstOrFail();
        $this->assertEquals(PostStatus::DRAFT, $post->status);
        $this->assertNull($post->published_at);

        // 2. Schedule for future
        $futureTime = now()->addHours(3);
        $scheduleResponse = $this->actingAs($this->user)->put(route('posts.update', $post->id), [
            'title' => 'Lifecycle Story',
            'status' => 'scheduled',
            'published_at' => $futureTime->toDateTimeString(),
            'category_id' => $this->category->id,
            'content_html' => $content,
        ]);
        $scheduleResponse->assertRedirect(route('dashboard'));

        $post->refresh();
        $this->assertEquals(PostStatus::SCHEDULED, $post->status);
        $this->assertNotNull($post->published_at);

        // 3. Publish via posts:publish-scheduled command
        // Simulate time arrival
        $post->update(['published_at' => now()->subMinute()]);
        $this->artisan('posts:publish-scheduled')
            ->expectsOutput('Published 1 scheduled post(s).')
            ->assertSuccessful();

        $post->refresh();
        $this->assertEquals(PostStatus::PUBLISHED, $post->status);

        // 4. Archive
        $archiveResponse = $this->actingAs($this->user)->post(route('posts.archive', $post->id));
        $archiveResponse->assertRedirect();

        $post->refresh();
        $this->assertEquals(PostStatus::ARCHIVED, $post->status);

        // 5. Unpublish back to draft
        $unpublishResponse = $this->actingAs($this->user)->post(route('posts.unpublish', $post->id));
        $unpublishResponse->assertRedirect();

        $post->refresh();
        $this->assertEquals(PostStatus::DRAFT, $post->status);
        $this->assertNull($post->published_at);

        // 6. Soft Delete
        $deleteResponse = $this->actingAs($this->user)->delete(route('posts.destroy', $post->id));
        $deleteResponse->assertRedirect(route('dashboard'));

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    /**
     * Test reading time is computed at ≈225 wpm on save.
     */
    public function test_reading_time_minutes_computed_at_225_wpm(): void
    {
        // 225 words -> 1 minute
        $post1 = Post::create([
            'user_id' => $this->user->id,
            'title' => 'Story 225 Words',
            'content_html' => '<p>' . $this->generateWords(225) . '</p>',
            'status' => PostStatus::DRAFT,
        ]);
        $this->assertEquals(1, $post1->reading_time_minutes);

        // 450 words -> 2 minutes
        $post2 = Post::create([
            'user_id' => $this->user->id,
            'title' => 'Story 450 Words',
            'content_html' => '<p>' . $this->generateWords(450) . '</p>',
            'status' => PostStatus::DRAFT,
        ]);
        $this->assertEquals(2, $post2->reading_time_minutes);

        // 500 words -> 3 minutes (ceil(500 / 225) = 3)
        $post3 = Post::create([
            'user_id' => $this->user->id,
            'title' => 'Story 500 Words',
            'content_html' => '<p>' . $this->generateWords(500) . '</p>',
            'status' => PostStatus::DRAFT,
        ]);
        $this->assertEquals(3, $post3->reading_time_minutes);
    }

    /**
     * Test publishing requires a title and at least 100 words.
     */
    public function test_publishing_requires_title_and_at_least_100_words(): void
    {
        // 1. Missing title when publishing
        $response1 = $this->actingAs($this->user)->post(route('posts.store'), [
            'title' => '',
            'status' => 'published',
            'content_html' => '<p>' . $this->generateWords(120) . '</p>',
        ]);
        $response1->assertSessionHasErrors('title');

        // 2. Under 100 words when publishing
        $response2 = $this->actingAs($this->user)->post(route('posts.store'), [
            'title' => 'Short story',
            'status' => 'published',
            'content_html' => '<p>' . $this->generateWords(50) . '</p>',
        ]);
        $response2->assertSessionHasErrors('content_html');

        // 3. >= 100 words and title succeeds
        $response3 = $this->actingAs($this->user)->post(route('posts.store'), [
            'title' => 'Proper Story',
            'status' => 'published',
            'content_html' => '<p>' . $this->generateWords(105) . '</p>',
        ]);
        $response3->assertSessionHasNoErrors();
    }

    /**
     * Test tags input with autocomplete and create-new up to 5 tags.
     */
    public function test_tags_support_create_new_and_max_5_limit(): void
    {
        $existingTag = Tag::create(['name' => 'Architecture', 'slug' => 'architecture']);

        // Submit 3 tags (1 existing ID, 2 new strings)
        $response = $this->actingAs($this->user)->post(route('posts.store'), [
            'title' => 'Tag Test Story',
            'status' => 'draft',
            'content_html' => '<p>Draft</p>',
            'tags' => [$existingTag->id, 'Vue.js', 'Inertia'],
        ]);
        $response->assertSessionHasNoErrors();

        $post = Post::where('title', 'Tag Test Story')->firstOrFail();
        $this->assertCount(3, $post->tags);
        $this->assertTrue($post->tags->contains('name', 'Architecture'));
        $this->assertTrue($post->tags->contains('name', 'Vue.js'));
        $this->assertTrue($post->tags->contains('name', 'Inertia'));

        // Reject > 5 tags
        $overLimitResponse = $this->actingAs($this->user)->post(route('posts.store'), [
            'title' => 'Over Limit Tags',
            'status' => 'draft',
            'content_html' => '<p>Draft</p>',
            'tags' => ['Tag1', 'Tag2', 'Tag3', 'Tag4', 'Tag5', 'Tag6'],
        ]);
        $overLimitResponse->assertSessionHasErrors('tags');
    }

    /**
     * Policy test: Another user cannot view or edit my drafts.
     */
    public function test_another_user_cannot_view_or_edit_my_drafts(): void
    {
        $draft = Post::factory()->draft()->create([
            'user_id' => $this->user->id,
            'title' => 'Private Draft',
        ]);

        // Other regular user cannot view draft
        $viewResponse = $this->actingAs($this->otherUser)->get(route('posts.show', $draft->slug));
        $viewResponse->assertStatus(403);

        // Other regular user cannot view edit page
        $editPageResponse = $this->actingAs($this->otherUser)->get(route('posts.edit', $draft->id));
        $editPageResponse->assertStatus(403);

        // Other regular user cannot update draft
        $updateResponse = $this->actingAs($this->otherUser)->put(route('posts.update', $draft->id), [
            'title' => 'Hacked Draft',
            'content_html' => '<p>Bad update</p>',
        ]);
        $updateResponse->assertStatus(403);

        // Author can view and edit
        $authorView = $this->actingAs($this->user)->get(route('posts.show', $draft->slug));
        $authorView->assertOk();

        $authorEdit = $this->actingAs($this->user)->get(route('posts.edit', $draft->id));
        $authorEdit->assertOk();
    }

    /**
     * Test post is editable after publishing and is_updated is set.
     */
    public function test_post_editable_after_publishing_and_shows_updated(): void
    {
        $publishedTime = now()->subHours(2);
        $post = Post::factory()->published()->create([
            'user_id' => $this->user->id,
            'title' => 'Original Published Title',
            'content_html' => '<p>' . $this->generateWords(120) . '</p>',
            'published_at' => $publishedTime,
            'created_at' => $publishedTime,
            'updated_at' => $publishedTime,
        ]);

        $this->assertFalse($post->is_updated);

        // Update published post
        $response = $this->actingAs($this->user)->put(route('posts.update', $post->id), [
            'title' => 'Edited Published Title',
            'status' => 'published',
            'content_html' => '<p>' . $this->generateWords(130) . '</p>',
        ]);
        $response->assertSessionHasNoErrors();

        $post->refresh();
        $this->assertEquals('Edited Published Title', $post->title);

        // Simulate updated_at being later than published_at
        $post->update(['updated_at' => now()]);
        $this->assertTrue($post->is_updated);

        // Public show page displays updated tag
        $showResponse = $this->get(route('posts.show', $post->slug));
        $showResponse->assertOk()
            ->assertInertia(fn ($page) => $page->where('post.is_updated', true));
    }

    /**
     * Test writer dashboard tabs, search, sorting, stats, and bulk actions.
     */
    public function test_writer_dashboard_functionality(): void
    {
        // Create 2 published, 1 draft, 1 scheduled, 1 archived
        Post::factory()->published()->create([
            'user_id' => $this->user->id,
            'title' => 'Laravel 12 Insights',
            'views_count' => 150,
            'likes_count' => 20,
        ]);
        Post::factory()->published()->create([
            'user_id' => $this->user->id,
            'title' => 'Vue 3 Composition Guide',
            'views_count' => 50,
            'likes_count' => 5,
        ]);
        $draft = Post::factory()->draft()->create([
            'user_id' => $this->user->id,
            'title' => 'Unfinished Thoughts',
        ]);
        Post::create([
            'user_id' => $this->user->id,
            'title' => 'Future Post',
            'content_html' => '<p>' . $this->generateWords(110) . '</p>',
            'status' => PostStatus::SCHEDULED,
            'published_at' => now()->addDay(),
        ]);
        $archived = Post::create([
            'user_id' => $this->user->id,
            'title' => 'Old Deprecated Tech',
            'content_html' => '<p>' . $this->generateWords(110) . '</p>',
            'status' => PostStatus::ARCHIVED,
        ]);

        // 1. Check default published tab
        $response = $this->actingAs($this->user)->get(route('dashboard'));
        $response->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard')
                ->where('counts.published', 2)
                ->where('counts.drafts', 1)
                ->where('counts.scheduled', 1)
                ->where('counts.archived', 1)
                ->has('posts.data', 2)
            );

        // 2. Check drafts tab
        $draftsResponse = $this->actingAs($this->user)->get(route('dashboard', ['tab' => 'drafts']));
        $draftsResponse->assertOk()
            ->assertInertia(fn ($page) => $page->where('filters.tab', 'drafts')
                ->has('posts.data', 1)
                ->where('posts.data.0.title', 'Unfinished Thoughts')
            );

        // 3. Search filtering
        $searchResponse = $this->actingAs($this->user)->get(route('dashboard', ['search' => 'Vue 3']));
        $searchResponse->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts.data', 1)
                ->where('posts.data.0.title', 'Vue 3 Composition Guide')
            );

        // 4. Sorting by views
        $sortResponse = $this->actingAs($this->user)->get(route('dashboard', ['sort' => 'views']));
        $sortResponse->assertOk()
            ->assertInertia(fn ($page) => $page->where('posts.data.0.title', 'Laravel 12 Insights'));

        // 5. Bulk actions: archive published posts
        $publishedPosts = Post::where('user_id', $this->user->id)->where('status', PostStatus::PUBLISHED)->pluck('id')->toArray();
        $bulkResponse = $this->actingAs($this->user)->post(route('dashboard.bulk-action'), [
            'ids' => $publishedPosts,
            'action' => 'archive',
        ]);
        $bulkResponse->assertRedirect();

        $this->assertEquals(0, Post::where('user_id', $this->user->id)->where('status', PostStatus::PUBLISHED)->count());
        $this->assertEquals(3, Post::where('user_id', $this->user->id)->where('status', PostStatus::ARCHIVED)->count());
    }
}
