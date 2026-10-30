<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\FeedService;
use Carbon\Carbon;
use Tests\TestCase;

class FeedScoringTest extends TestCase
{
    protected FeedService $feedService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feedService = new FeedService();
    }

    /**
     * Test recency decay: newer stories receive higher score than older stories.
     */
    public function test_recency_decay_formula(): void
    {
        $baseSignals = [
            'preferred_category_ids' => [],
            'preferred_tag_ids' => [],
            'followed_author_ids' => [],
            'viewed_post_ids' => [],
            'dismissed_post_ids' => [],
            'dismissed_category_ids' => [],
            'category_affinity' => [],
            'tag_affinity' => [],
        ];

        // 1 hour old post
        $freshPost = (new Post([
            'user_id' => 10,
            'category_id' => null,
            'published_at' => Carbon::now()->subHours(1),
            'likes_count' => 0,
            'comments_count' => 0,
            'views_count' => 0,
        ]))->forceFill(['id' => 1]);

        // 24 hours old post
        $dayOldPost = (new Post([
            'user_id' => 10,
            'category_id' => null,
            'published_at' => Carbon::now()->subHours(24),
            'likes_count' => 0,
            'comments_count' => 0,
            'views_count' => 0,
        ]))->forceFill(['id' => 2]);

        // 7 days old post
        $weekOldPost = (new Post([
            'user_id' => 10,
            'category_id' => null,
            'published_at' => Carbon::now()->subDays(7),
            'likes_count' => 0,
            'comments_count' => 0,
            'views_count' => 0,
        ]))->forceFill(['id' => 3]);

        $scoreFresh = $this->feedService->calculatePostScore($freshPost, $baseSignals);
        $scoreDay = $this->feedService->calculatePostScore($dayOldPost, $baseSignals);
        $scoreWeek = $this->feedService->calculatePostScore($weekOldPost, $baseSignals);

        $this->assertGreaterThan($scoreDay, $scoreFresh, '1-hour old post must score higher than 24-hour old post');
        $this->assertGreaterThan($scoreWeek, $scoreDay, '24-hour old post must score higher than 7-day old post');
    }

    /**
     * Test preferred category adds expected +35 boost.
     */
    public function test_category_match_boost(): void
    {
        $now = Carbon::now();
        $postMatching = new Post([
            'id' => 1,
            'user_id' => 10,
            'category_id' => 5,
            'published_at' => $now,
            'likes_count' => 0,
            'comments_count' => 0,
            'views_count' => 0,
        ]);

        $postOther = new Post([
            'id' => 2,
            'user_id' => 10,
            'category_id' => 99,
            'published_at' => $now,
            'likes_count' => 0,
            'comments_count' => 0,
            'views_count' => 0,
        ]);

        $signals = [
            'preferred_category_ids' => [5],
            'preferred_tag_ids' => [],
            'followed_author_ids' => [],
            'viewed_post_ids' => [],
            'dismissed_post_ids' => [],
            'dismissed_category_ids' => [],
            'category_affinity' => [],
            'tag_affinity' => [],
        ];

        $scoreMatching = $this->feedService->calculatePostScore($postMatching, $signals);
        $scoreOther = $this->feedService->calculatePostScore($postOther, $signals);

        $diff = $scoreMatching - $scoreOther;
        $this->assertEqualsWithDelta(35.0, $diff, 0.01, 'Matching category should give exactly 35 point boost');
    }

    /**
     * Test followed author adds +40 boost.
     */
    public function test_followed_author_boost(): void
    {
        $now = Carbon::now();
        $followedPost = new Post([
            'id' => 1,
            'user_id' => 42,
            'category_id' => null,
            'published_at' => $now,
            'likes_count' => 0,
            'comments_count' => 0,
            'views_count' => 0,
        ]);

        $unfollowedPost = new Post([
            'id' => 2,
            'user_id' => 99,
            'category_id' => null,
            'published_at' => $now,
            'likes_count' => 0,
            'comments_count' => 0,
            'views_count' => 0,
        ]);

        $signals = [
            'preferred_category_ids' => [],
            'preferred_tag_ids' => [],
            'followed_author_ids' => [42],
            'viewed_post_ids' => [],
            'dismissed_post_ids' => [],
            'dismissed_category_ids' => [],
            'category_affinity' => [],
            'tag_affinity' => [],
        ];

        $scoreFollowed = $this->feedService->calculatePostScore($followedPost, $signals);
        $scoreUnfollowed = $this->feedService->calculatePostScore($unfollowedPost, $signals);

        $this->assertEqualsWithDelta(40.0, $scoreFollowed - $scoreUnfollowed, 0.01, 'Followed author must give 40 point boost');
    }

    /**
     * Test engagement log-scaling increases score monotonically.
     */
    public function test_engagement_log_scaling(): void
    {
        $now = Carbon::now();

        $lowEngagementPost = new Post([
            'id' => 1,
            'user_id' => 10,
            'published_at' => $now,
            'likes_count' => 1,
            'comments_count' => 0,
            'views_count' => 10,
        ]);

        $highEngagementPost = new Post([
            'id' => 2,
            'user_id' => 10,
            'published_at' => $now,
            'likes_count' => 100,
            'comments_count' => 25,
            'views_count' => 1500,
        ]);

        $signals = [
            'preferred_category_ids' => [],
            'preferred_tag_ids' => [],
            'followed_author_ids' => [],
            'viewed_post_ids' => [],
            'dismissed_post_ids' => [],
            'dismissed_category_ids' => [],
            'category_affinity' => [],
            'tag_affinity' => [],
        ];

        $scoreLow = $this->feedService->calculatePostScore($lowEngagementPost, $signals);
        $scoreHigh = $this->feedService->calculatePostScore($highEngagementPost, $signals);

        $this->assertGreaterThan($scoreLow, $scoreHigh);
    }

    /**
     * Test viewed posts receive a -50 penalty.
     */
    public function test_viewed_post_penalty(): void
    {
        $now = Carbon::now();
        $unviewedPost = (new Post([
            'user_id' => 10,
            'published_at' => $now,
            'likes_count' => 0,
            'comments_count' => 0,
            'views_count' => 0,
        ]))->forceFill(['id' => 10]);

        $viewedPost = (new Post([
            'user_id' => 10,
            'published_at' => $now,
            'likes_count' => 0,
            'comments_count' => 0,
            'views_count' => 0,
        ]))->forceFill(['id' => 20]);

        $signals = [
            'preferred_category_ids' => [],
            'preferred_tag_ids' => [],
            'followed_author_ids' => [],
            'viewed_post_ids' => [20],
            'dismissed_post_ids' => [],
            'dismissed_category_ids' => [],
            'category_affinity' => [],
            'tag_affinity' => [],
        ];

        $scoreUnviewed = $this->feedService->calculatePostScore($unviewedPost, $signals);
        $scoreViewed = $this->feedService->calculatePostScore($viewedPost, $signals);

        $this->assertEqualsWithDelta(50.0, $scoreUnviewed - $scoreViewed, 0.01, 'Viewed post must be penalized by 50 points');
    }

    /**
     * Test dismissed category penalty.
     */
    public function test_dismissed_category_penalty(): void
    {
        $now = Carbon::now();
        $normalPost = new Post([
            'id' => 1,
            'user_id' => 10,
            'category_id' => 1,
            'published_at' => $now,
            'likes_count' => 0,
            'comments_count' => 0,
            'views_count' => 0,
        ]);

        $dismissedCatPost = new Post([
            'id' => 2,
            'user_id' => 10,
            'category_id' => 2,
            'published_at' => $now,
            'likes_count' => 0,
            'comments_count' => 0,
            'views_count' => 0,
        ]);

        $signals = [
            'preferred_category_ids' => [],
            'preferred_tag_ids' => [],
            'followed_author_ids' => [],
            'viewed_post_ids' => [],
            'dismissed_post_ids' => [],
            'dismissed_category_ids' => [2],
            'category_affinity' => [],
            'tag_affinity' => [],
        ];

        $scoreNormal = $this->feedService->calculatePostScore($normalPost, $signals);
        $scoreDismissed = $this->feedService->calculatePostScore($dismissedCatPost, $signals);

        $this->assertEqualsWithDelta(30.0, $scoreNormal - $scoreDismissed, 0.01, 'Dismissed category must receive 30 point penalty');
    }
}
