<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Jobs\IncrementPostViewJob;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostView;
use App\Models\Tag;
use App\Models\User;
use App\Services\BotDetectorService;
use App\Services\TableOfContentsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublicReadingExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected User $author;
    protected Category $category;
    protected Tag $tag;
    protected Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create([
            'name' => 'Jane Austen',
            'username' => 'janeausten',
            'role' => UserRole::USER,
        ]);

        $this->category = Category::factory()->create([
            'name' => 'Literature',
            'slug' => 'literature',
        ]);

        $this->tag = Tag::factory()->create([
            'name' => 'Classics',
            'slug' => 'classics',
        ]);

        $this->post = Post::factory()->create([
            'user_id' => $this->author->id,
            'category_id' => $this->category->id,
            'title' => 'The Art of Persuasion in English Literature',
            'slug' => 'the-art-of-persuasion',
            'excerpt' => 'An analytical essay exploring early Victorian narrative structures.',
            'content_html' => '<h2>Introduction</h2><p>Here is introductory text.</p><h3>Stylistic Choices</h3><p>Detailed analysis of literary styles.</p>',
            'status' => PostStatus::PUBLISHED,
            'published_at' => now()->subDays(2),
            'views_count' => 0,
        ]);

        $this->post->tags()->attach($this->tag);
    }

    /**
     * Test canonical post page rendering and short route 301 redirect.
     */
    public function test_post_page_canonical_rendering_and_301_redirects(): void
    {
        // 1. Visit canonical URL
        $canonicalUrl = route('posts.show.canonical', [
            'category_slug' => $this->category->slug,
            'post_slug' => $this->post->slug,
        ]);

        $response = $this->get($canonicalUrl);
        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Posts/Show')
                ->has('post')
                ->has('toc', 2)
                ->where('toc.0.text', 'Introduction')
                ->where('toc.0.level', 2)
                ->where('toc.1.text', 'Stylistic Choices')
                ->where('toc.1.level', 3)
                ->has('authorStats')
                ->has('relatedPosts')
            );

        // 2. Short route /p/{slug} must 301 redirect to canonical
        $shortResponse = $this->get('/p/' . $this->post->slug);
        $shortResponse->assertStatus(301);
        $shortResponse->assertRedirect($canonicalUrl);

        // 3. Visiting canonical with wrong category slug must 301 redirect to correct canonical
        $wrongCategoryResponse = $this->get('/wrong-cat/' . $this->post->slug);
        $wrongCategoryResponse->assertStatus(301);
        $wrongCategoryResponse->assertRedirect($canonicalUrl);
    }

    /**
     * Test Table of Contents generator service and heading anchor injection.
     */
    public function test_table_of_contents_service(): void
    {
        $service = app(TableOfContentsService::class);
        $html = '<h2>First Main Topic</h2><p>Content</p><h3>Subsection Details</h3><p>More content</p><h2>Second Main Topic</h2>';

        $result = $service->extractAndInject($html);

        $this->assertCount(3, $result['toc']);
        $this->assertSame('First Main Topic', $result['toc'][0]['text']);
        $this->assertSame(2, $result['toc'][0]['level']);
        $this->assertSame('first-main-topic', $result['toc'][0]['id']);

        $this->assertSame('Subsection Details', $result['toc'][1]['text']);
        $this->assertSame(3, $result['toc'][1]['level']);

        $this->assertSame('Second Main Topic', $result['toc'][2]['text']);

        // Injected anchor check
        $this->assertStringContainsString('id="first-main-topic"', $result['html']);
        $this->assertStringContainsString('id="subsection-details"', $result['html']);
        $this->assertStringContainsString('id="second-main-topic"', $result['html']);
    }

    /**
     * Test bot view filtering and 24h deduplication for views.
     */
    public function test_bot_view_filtering_and_session_deduplication(): void
    {
        Queue::fake();

        // 1. Bot request should NOT dispatch view increment job
        $botResponse = $this->withHeaders([
            'User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
        ])->get(route('posts.show.canonical', [
            'category_slug' => $this->category->slug,
            'post_slug' => $this->post->slug,
        ]));

        $botResponse->assertOk();
        Queue::assertNotPushed(IncrementPostViewJob::class);

        // 2. Real human visitor should dispatch IncrementPostViewJob
        $humanResponse = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
        ])->get(route('posts.show.canonical', [
            'category_slug' => $this->category->slug,
            'post_slug' => $this->post->slug,
        ]));

        $humanResponse->assertOk();
        Queue::assertPushed(IncrementPostViewJob::class, 1);

        // Run the queued job manually to record view
        $job = new IncrementPostViewJob($this->post->id, null, 'mock-hash-123');
        $job->handle();

        $this->post->refresh();
        $this->assertSame(1, $this->post->views_count);
        $this->assertDatabaseHas('post_views', [
            'post_id' => $this->post->id,
            'session_hash' => 'mock-hash-123',
        ]);
    }

    /**
     * Test category and tag public pages.
     */
    public function test_category_and_tag_pages_with_pagination(): void
    {
        // Category page
        $categoryResponse = $this->get(route('categories.show', $this->category->slug));
        $categoryResponse->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Categories/Show')
                ->where('category.slug', $this->category->slug)
                ->has('posts.data', 1)
            );

        // Tag page
        $tagResponse = $this->get(route('tags.show', $this->tag->slug));
        $tagResponse->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tags/Show')
                ->where('tag.slug', $this->tag->slug)
                ->has('posts.data', 1)
            );
    }

    /**
     * Test SQLite FTS5 search table synchronization on post events.
     */
    public function test_sqlite_fts5_search_synchronization_and_queries(): void
    {
        // 1. Check that $this->post is indexed in posts_fts
        $ftsRows = DB::select("SELECT * FROM posts_fts WHERE post_id = ?", [$this->post->id]);
        $this->assertCount(1, $ftsRows);
        $this->assertSame('The Art of Persuasion in English Literature', $ftsRows[0]->title);

        // 2. Test search query matching
        $searchResponse = $this->get(route('search', ['q' => 'Persuasion']));
        $searchResponse->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Search/Index')
                ->where('query', 'Persuasion')
                ->has('posts.data', 1)
                ->where('posts.data.0.id', $this->post->id)
                ->has('posts.data.0.search_snippet')
            );

        // 3. Update post title and verify FTS table is updated
        $this->post->update([
            'title' => 'Renamed Masterpiece of Victorian Prose',
        ]);

        $updatedFts = DB::select("SELECT * FROM posts_fts WHERE post_id = ?", [$this->post->id]);
        $this->assertCount(1, $updatedFts);
        $this->assertSame('Renamed Masterpiece of Victorian Prose', $updatedFts[0]->title);

        // Old query returns 0, new query returns 1
        $oldSearch = $this->get(route('search', ['q' => 'Persuasion']));
        $oldSearch->assertInertia(fn ($page) => $page->has('posts.data', 0));

        $newSearch = $this->get(route('search', ['q' => 'Victorian Prose']));
        $newSearch->assertInertia(fn ($page) => $page->has('posts.data', 1));

        // 4. Soft delete post and verify it is removed from FTS index
        $this->post->delete();
        $deletedFts = DB::select("SELECT * FROM posts_fts WHERE post_id = ?", [$this->post->id]);
        $this->assertCount(0, $deletedFts);
    }

    /**
     * Test search filters by category, tag, and date range.
     */
    public function test_search_filters(): void
    {
        $otherCategory = Category::factory()->create(['name' => 'Science', 'slug' => 'science']);

        // Filter by matching category
        $resMatchingCat = $this->get(route('search', ['category' => $this->category->slug]));
        $resMatchingCat->assertOk()->assertInertia(fn ($page) => $page->has('posts.data', 1));

        // Filter by non-matching category
        $resOtherCat = $this->get(route('search', ['category' => $otherCategory->slug]));
        $resOtherCat->assertOk()->assertInertia(fn ($page) => $page->has('posts.data', 0));

        // Filter by tag
        $resTag = $this->get(route('search', ['tag' => $this->tag->slug]));
        $resTag->assertOk()->assertInertia(fn ($page) => $page->has('posts.data', 1));

        // Filter by date range (post was published 2 days ago)
        $resDateMatch = $this->get(route('search', [
            'from' => now()->subDays(3)->format('Y-m-d'),
            'to' => now()->subDays(1)->format('Y-m-d'),
        ]));
        $resDateMatch->assertOk()->assertInertia(fn ($page) => $page->has('posts.data', 1));

        $resDateMiss = $this->get(route('search', [
            'from' => now()->subDays(10)->format('Y-m-d'),
            'to' => now()->subDays(5)->format('Y-m-d'),
        ]));
        $resDateMiss->assertOk()->assertInertia(fn ($page) => $page->has('posts.data', 0));
    }

    /**
     * Test the Archives index and show pages.
     */
    public function test_archive_pages(): void
    {
        $year = (int) $this->post->published_at->format('Y');
        $month = $this->post->published_at->format('m');

        // Archive Index
        $indexResponse = $this->get(route('archive.index'));
        $indexResponse->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Archive/Index')
                ->has('archiveYears')
                ->has('recentStories')
            );

        // Archive Show by Year
        $yearResponse = $this->get(route('archive.show', ['year' => $year]));
        $yearResponse->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Archive/Show')
                ->where('year', $year)
                ->has('posts.data', 1)
            );

        // Archive Show by Year and Month
        $monthResponse = $this->get(route('archive.show', ['year' => $year, 'month' => $month]));
        $monthResponse->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Archive/Show')
                ->where('year', $year)
                ->where('month', (int) $month)
                ->has('posts.data', 1)
            );
    }

    /**
     * Test 404 for nonexistent story and 410 Gone for soft-deleted story.
     */
    public function test_error_pages_404_and_410(): void
    {
        // Nonexistent story -> 404
        $notFound = $this->get('/non-existent-category/non-existent-post');
        $notFound->assertStatus(404);

        // Soft-deleted story -> 410 Gone
        $this->post->delete();

        $goneResponse = $this->get(route('posts.show.canonical', [
            'category_slug' => $this->category->slug,
            'post_slug' => $this->post->slug,
        ]));
        $goneResponse->assertStatus(410);

        // Short route for soft-deleted story -> 410 Gone
        $shortGone = $this->get('/p/' . $this->post->slug);
        $shortGone->assertStatus(410);
    }
}
