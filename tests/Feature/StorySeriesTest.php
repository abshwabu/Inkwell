<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\SeriesStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Post;
use App\Models\Series;
use App\Models\SeriesProgress;
use App\Models\User;
use App\Notifications\NewSeriesPartNotification;
use App\Services\FeedService;
use App\Services\SeoService;
use App\Services\SeriesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StorySeriesTest extends TestCase
{
    use RefreshDatabase;

    protected User $author;
    protected User $reader;
    protected User $otherUser;
    protected Category $category;
    protected Series $series;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create([
            'name' => 'Alice Author',
            'username' => 'aliceauthor',
            'role' => UserRole::USER,
        ]);

        $this->reader = User::factory()->create([
            'name' => 'Bob Reader',
            'username' => 'bobreader',
            'role' => UserRole::USER,
        ]);

        $this->otherUser = User::factory()->create([
            'name' => 'Eve Attacker',
            'username' => 'eveattacker',
            'role' => UserRole::USER,
        ]);

        $this->category = Category::factory()->create([
            'name' => 'Engineering',
            'slug' => 'engineering',
        ]);

        $this->series = Series::create([
            'user_id' => $this->author->id,
            'title' => 'Building Scalable Systems',
            'slug' => 'building-scalable-systems',
            'description' => 'A comprehensive series on distributed architecture.',
            'status' => SeriesStatus::ONGOING,
        ]);
    }

    /**
     * Helper to create a valid published post in series with 100+ words.
     */
    protected function createPart(Series $series, int $order, string $title = 'Story Part', PostStatus $status = PostStatus::PUBLISHED): Post
    {
        return Post::create([
            'user_id' => $series->user_id,
            'category_id' => $this->category->id,
            'series_id' => $series->id,
            'series_order' => $order,
            'title' => $title,
            'slug' => strtolower(str_replace(' ', '-', $title)) . '-' . $order . '-' . uniqid(),
            'excerpt' => 'Short excerpt for ' . $title,
            'content_html' => '<p>' . str_repeat('Inkwell is an open platform where developers and creators share deep technical knowledge and thoughts. ', 15) . '</p>',
            'status' => $status,
            'published_at' => $status === PostStatus::PUBLISHED ? now()->subMinutes(10 * $order) : null,
            'reading_time_minutes' => 3,
        ]);
    }

    public function test_only_owner_can_access_write_with_prefilled_series(): void
    {
        // 1. Series owner accessing /write?series=slug succeeds (200)
        $response = $this->actingAs($this->author)->get(route('posts.create', ['series' => $this->series->slug]));
        $response->assertStatus(200);

        // 2. Another user forging ?series=slug gets 403 Forbidden
        $response = $this->actingAs($this->otherUser)->get(route('posts.create', ['series' => $this->series->slug]));
        $response->assertStatus(403);
    }

    public function test_user_cannot_store_post_attaching_someone_elses_series(): void
    {
        $payload = [
            'title' => 'Forged Part',
            'content_html' => '<p>' . str_repeat('Valid content with more than one hundred words for publishing stories smoothly on Inkwell platform. ', 15) . '</p>',
            'series_id' => $this->series->id,
            'status' => 'draft',
        ];

        // Other user attempts to attach to author's series
        $response = $this->actingAs($this->otherUser)->post(route('posts.store'), $payload);
        $response->assertSessionHasErrors(['series_id']);
    }

    public function test_part_numbers_are_automatically_assigned_on_add(): void
    {
        $post1 = $this->createPart($this->series, 1, 'First Chapter');
        $this->assertEquals(1, $post1->series_order);

        // Store post 2 via endpoint without explicitly specifying series_order
        $response = $this->actingAs($this->author)->post(route('posts.store'), [
            'title' => 'Second Chapter',
            'content_html' => '<p>' . str_repeat('Writing another hundred words for chapter two to test automatic order assignment in story series. ', 15) . '</p>',
            'category_id' => $this->category->id,
            'series_id' => $this->series->id,
            'status' => 'published',
        ]);

        $post2 = Post::where('title', 'Second Chapter')->first();
        $this->assertNotNull($post2);
        $this->assertEquals(2, $post2->series_order);
    }

    public function test_reordering_parts_in_one_transaction_without_unique_constraint_violation(): void
    {
        $post1 = $this->createPart($this->series, 1, 'Part One');
        $post2 = $this->createPart($this->series, 2, 'Part Two');
        $post3 = $this->createPart($this->series, 3, 'Part Three');

        // Reverse the order: [3, 2, 1]
        // In a direct single-step update this would collide with (series_id, series_order) unique index.
        $response = $this->actingAs($this->author)->post(route('series.reorder', $this->series->id), [
            'ordered_ids' => [$post3->id, $post2->id, $post1->id],
        ]);

        $response->assertSessionHasNoErrors();

        $post3->refresh();
        $post2->refresh();
        $post1->refresh();

        $this->assertEquals(1, $post3->series_order);
        $this->assertEquals(2, $post2->series_order);
        $this->assertEquals(3, $post1->series_order);
    }

    public function test_other_user_cannot_reorder_series(): void
    {
        $post1 = $this->createPart($this->series, 1, 'Part One');
        $post2 = $this->createPart($this->series, 2, 'Part Two');

        $response = $this->actingAs($this->otherUser)->post(route('series.reorder', $this->series->id), [
            'ordered_ids' => [$post2->id, $post1->id],
        ]);

        $response->assertStatus(403);
    }

    public function test_unpublishing_middle_part_renumbers_on_the_fly_without_gaps_for_readers(): void
    {
        $part1 = $this->createPart($this->series, 1, 'Part 1: The Intro');
        $part2 = $this->createPart($this->series, 2, 'Part 2: The Middle');
        $part3 = $this->createPart($this->series, 3, 'Part 3: The Conclusion');

        $seriesService = app(SeriesService::class);

        // Before unpublishing: Guest sees 3 published parts
        $context = $seriesService->getSeriesContextForPost($part1, null);
        $this->assertEquals(3, $context['total_published_parts']);
        $this->assertEquals(1, $context['current_part_number']);

        // Now unpublish Part 2
        $part2->update(['status' => PostStatus::DRAFT, 'published_at' => null]);

        // Guest reading Part 3 should see "Part 2 of 2" (no gap)
        $guestContextPart3 = $seriesService->getSeriesContextForPost($part3, null);
        $this->assertEquals(2, $guestContextPart3['total_published_parts']);
        $this->assertEquals(2, $guestContextPart3['current_part_number']);
        $this->assertCount(2, $guestContextPart3['parts']); // draft hidden from guest

        // Author reading Part 3 sees all 3 parts with Part 2 flagged as draft
        $authorContext = $seriesService->getSeriesContextForPost($part3, $this->author);
        $this->assertCount(3, $authorContext['parts']);
        $draftPart = collect($authorContext['parts'])->firstWhere('id', $part2->id);
        $this->assertFalse($draftPart['is_published']);
    }

    public function test_deleting_series_detaches_posts_and_keeps_them_published(): void
    {
        $part1 = $this->createPart($this->series, 1, 'Part 1');
        $part2 = $this->createPart($this->series, 2, 'Part 2');

        $this->actingAs($this->author)->delete(route('series.destroy', $this->series->id));

        $this->assertSoftDeleted('series', ['id' => $this->series->id]);

        $part1->refresh();
        $part2->refresh();

        // Posts are still alive and published, but detached from series
        $this->assertNull($part1->series_id);
        $this->assertNull($part1->series_order);
        $this->assertEquals(PostStatus::PUBLISHED, $part1->status);

        $this->assertNull($part2->series_id);
        $this->assertNull($part2->series_order);
        $this->assertEquals(PostStatus::PUBLISHED, $part2->status);
    }

    public function test_authenticated_user_can_follow_and_unfollow_series(): void
    {
        // 1. Guest redirected to login
        $guestRes = $this->post(route('series.follow', $this->series->slug));
        $guestRes->assertRedirect(route('login'));

        // 2. Reader follows series
        $followRes = $this->actingAs($this->reader)->post(route('series.follow', $this->series->slug), [], [
            'Accept' => 'application/json',
        ]);
        $followRes->assertStatus(200)
            ->assertJson([
                'is_following' => true,
                'followers_count' => 1,
            ]);

        $this->assertTrue($this->series->isFollowedBy($this->reader));

        // 3. Reader unfollows series
        $unfollowRes = $this->actingAs($this->reader)->post(route('series.follow', $this->series->slug), [], [
            'Accept' => 'application/json',
        ]);
        $unfollowRes->assertStatus(200)
            ->assertJson([
                'is_following' => false,
                'followers_count' => 0,
            ]);

        $this->assertFalse($this->series->isFollowedBy($this->reader));
    }

    public function test_followers_receive_notification_exactly_once_when_new_part_is_published(): void
    {
        Notification::fake();

        // Bob follows series
        $this->series->followers()->attach($this->reader->id);

        // Author publishes new part
        $this->actingAs($this->author)->post(route('posts.store'), [
            'title' => 'A Brand New Part',
            'content_html' => '<p>' . str_repeat('Inkwell is an open platform where developers and creators share deep technical knowledge and thoughts. ', 15) . '</p>',
            'category_id' => $this->category->id,
            'series_id' => $this->series->id,
            'status' => 'published',
        ]);

        $post = Post::where('title', 'A Brand New Part')->firstOrFail();

        Notification::assertSentTo(
            $this->reader,
            NewSeriesPartNotification::class,
            function ($notification) use ($post) {
                return $notification->post->id === $post->id;
            }
        );

        // Author should NOT be notified of their own publication
        Notification::assertNotSentTo($this->author, NewSeriesPartNotification::class);
    }

    public function test_reading_progress_records_last_post_for_user(): void
    {
        $part1 = $this->createPart($this->series, 1, 'Part 1');
        $part2 = $this->createPart($this->series, 2, 'Part 2');

        // Reader views part 1
        $this->actingAs($this->reader)->get(route('posts.show.canonical', [
            'category_slug' => $this->category->slug,
            'post_slug' => $part1->slug,
        ]));

        $progress = SeriesProgress::where('user_id', $this->reader->id)
            ->where('series_id', $this->series->id)
            ->first();

        $this->assertNotNull($progress);
        $this->assertEquals($part1->id, $progress->last_post_id);

        // Check Continue Reading service helper
        $seriesService = app(SeriesService::class);
        $continueList = $seriesService->getContinueReadingList($this->reader);
        $this->assertCount(1, $continueList);
        $this->assertEquals('Part 2', $continueList[0]['next_part']['title']);
        $this->assertTrue($continueList[0]['has_unread_next']);
    }

    public function test_previous_and_next_in_series_helpers(): void
    {
        $part1 = $this->createPart($this->series, 1, 'Part 1');
        $part2 = $this->createPart($this->series, 2, 'Part 2');
        $part3 = $this->createPart($this->series, 3, 'Part 3');

        $this->assertNull($part1->previousInSeries());
        $this->assertEquals($part2->id, $part1->nextInSeries()?->id);

        $this->assertEquals($part1->id, $part2->previousInSeries()?->id);
        $this->assertEquals($part3->id, $part2->nextInSeries()?->id);

        $this->assertEquals($part2->id, $part3->previousInSeries()?->id);
        $this->assertNull($part3->nextInSeries());
    }

    public function test_feed_service_boosts_followed_series_and_includes_in_following(): void
    {
        $part = $this->createPart($this->series, 1, 'Part 1');

        $feedService = app(FeedService::class);

        // Bob follows series
        $this->series->followers()->attach($this->reader->id);

        $signals = $feedService->getUserSignals($this->reader);
        $this->assertContains($this->series->id, $signals['followed_series_ids']);

        // Score with series boost
        $score = $feedService->calculatePostScore($part, $signals);
        $this->assertGreaterThan(30.0, $score);

        // Following feed includes followed series posts even if author is not individually followed
        $followingFeed = $feedService->getFeed($this->reader, 'following');
        $this->assertContains($part->id, $followingFeed->pluck('id')->toArray());
    }

    public function test_series_rss_feed_returns_valid_xml(): void
    {
        $this->createPart($this->series, 1, 'Part One');
        $this->createPart($this->series, 2, 'Part Two');

        $response = $this->get(route('feed.series', $this->series->slug));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8');
        $this->assertStringContainsString('<rss version="2.0"', $response->getContent());
        $this->assertStringContainsString('Part One', $response->getContent());
        $this->assertStringContainsString('Part Two', $response->getContent());
    }

    public function test_seo_service_generates_series_and_article_is_part_of_schema(): void
    {
        $part = $this->createPart($this->series, 1, 'Part 1');

        $seoService = app(SeoService::class);

        // Article SEO
        $postSeo = $seoService->forPost($part);
        $this->assertArrayHasKey('isPartOf', $postSeo['json_ld']);
        $this->assertEquals('CreativeWorkSeries', $postSeo['json_ld']['isPartOf']['@type']);
        $this->assertEquals($this->series->title, $postSeo['json_ld']['isPartOf']['name']);

        // Series page SEO
        $seriesSeo = $seoService->forSeries($this->series, 1);
        $this->assertEquals('CreativeWorkSeries', $seriesSeo['json_ld']['@type']);
        $this->assertEquals($this->series->title, $seriesSeo['json_ld']['name']);
    }

    public function test_no_n_plus_one_queries_when_viewing_post_in_series(): void
    {
        $this->createPart($this->series, 1, 'Part 1');
        $part2 = $this->createPart($this->series, 2, 'Part 2');
        $this->createPart($this->series, 3, 'Part 3');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->get(route('posts.show.canonical', [
            'category_slug' => $this->category->slug,
            'post_slug' => $part2->slug,
        ]));

        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        // Ensure no explosive N+1 queries (should be well below 25 total queries)
        $this->assertLessThan(25, count($queries));
    }
}
