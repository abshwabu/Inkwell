<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostDismissal;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FeedService
{
    /**
     * Retrieve paginated feed stories for a user or guest.
     */
    public function getFeed(
        ?User $user,
        string $tab = 'for-you',
        int $page = 1,
        int $perPage = 10,
        ?array $guestPreferences = null,
        ?string $categorySlug = null
    ): LengthAwarePaginator {
        $tab = strtolower($tab);
        $userId = $user ? $user->id : 0;
        $version = $user ? (int) Cache::get("feed_version_{$userId}", 1) : 1;

        $category = null;
        $categoryId = null;
        if (! empty($categorySlug)) {
            $category = Category::where('slug', $categorySlug)->first();
            $categoryId = $category?->id;
        }

        // For guests, hash guest preferences into cache key
        $guestKey = (! $user && ! empty($guestPreferences))
            ? substr(md5(json_encode($guestPreferences)), 0, 8)
            : 'default';

        $categoryKey = $categorySlug ?: 'all';
        $cacheKey = "feed_u{$userId}_{$guestKey}_{$categoryKey}_{$tab}_p{$page}_v{$version}";

        return Cache::remember($cacheKey, 300, function () use ($user, $tab, $page, $perPage, $guestPreferences, $categoryId) {
            return match ($tab) {
                'following' => $this->getFollowingFeed($user, $page, $perPage, $categoryId),
                'latest' => $this->getLatestFeed($user, $page, $perPage, $guestPreferences, $categoryId),
                'trending' => $this->getTrendingFeed($user, $page, $perPage, $guestPreferences, 7, $categoryId),
                default => $this->getPersonalizedFeed($user, $page, $perPage, $guestPreferences, $categoryId),
            };
        });
    }

    /**
     * Build personalized scored feed ("For you").
     */
    protected function getPersonalizedFeed(
        ?User $user,
        int $page,
        int $perPage,
        ?array $guestPreferences,
        ?int $categoryId = null
    ): LengthAwarePaginator {
        $signals = $this->getUserSignals($user, $guestPreferences);

        $userId = $user ? $user->id : null;
        $dismissedPostIds = $signals['dismissed_post_ids'];

        // If a specific topic/category is selected, filter strictly to this category
        if ($categoryId) {
            $query = Post::published()
                ->withRelations()
                ->where('posts.category_id', $categoryId)
                ->whereNull('posts.deleted_at');

            if ($userId) {
                $query->where('posts.user_id', '!=', $userId);
            }

            if (! empty($dismissedPostIds)) {
                $query->whereNotIn('posts.id', $dismissedPostIds);
            }

            $scoreExpression = $this->buildSqlScoreExpression($signals);
            return $query->select('posts.*')
                ->selectRaw("{$scoreExpression} as feed_score")
                ->orderByDesc('feed_score')
                ->orderByDesc('posts.published_at')
                ->paginate($perPage, ['*'], 'page', $page)
                ->withQueryString();
        }

        // Cold-start fallback: when no signals, fallback to trending in last 7 days
        if (! $signals['has_signals']) {
            return $this->getTrendingFeed($user, $page, $perPage, $guestPreferences, 7, null);
        }

        $userId = $user ? $user->id : null;
        $dismissedPostIds = $signals['dismissed_post_ids'];

        // Reserve 1 slot per page (10%) for exploration outside the user's preferences
        $explorationCount = max(1, (int) round($perPage * 0.10));
        $mainCount = $perPage - $explorationCount;

        // Base query for personalized scored posts
        $query = Post::published()
            ->withRelations()
            ->whereNull('posts.deleted_at');

        if ($userId) {
            $query->where('posts.user_id', '!=', $userId);
        }

        if (! empty($dismissedPostIds)) {
            $query->whereNotIn('posts.id', $dismissedPostIds);
        }

        // Build SQL score expression
        $scoreExpression = $this->buildSqlScoreExpression($signals);
        $query->select('posts.*')
            ->selectRaw("{$scoreExpression} as feed_score")
            ->orderByDesc('feed_score')
            ->orderByDesc('posts.published_at');

        // Total matching count
        $total = $query->count();

        // Fetch main scored items for current page offset
        $offset = ($page - 1) * $perPage;
        $mainPosts = (clone $query)->skip($offset)->take($mainCount)->get();

        // 10% Exploration item: a high-trending post outside user preferences
        $excludePostIds = array_merge(
            $mainPosts->pluck('id')->toArray(),
            $signals['viewed_post_ids'],
            $dismissedPostIds
        );

        $explorationPost = $this->getExplorationPost($signals, $userId, $excludePostIds);

        $mergedPosts = $mainPosts;
        if ($explorationPost) {
            $mergedPosts->push($explorationPost);
        } else {
            // If no exploration post available, fetch the next candidate
            $fallbackItem = (clone $query)->skip($offset + $mainCount)->take(1)->first();
            if ($fallbackItem) {
                $mergedPosts->push($fallbackItem);
            }
        }

        return (new LengthAwarePaginator(
            $mergedPosts,
            $total,
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        ))->withQueryString();
    }

    /**
     * Retrieve feed of posts by followed authors.
     */
    protected function getFollowingFeed(?User $user, int $page, int $perPage, ?int $categoryId = null): LengthAwarePaginator
    {
        if (! $user) {
            // Guests have no followings, fallback to trending
            return $this->getTrendingFeed(null, $page, $perPage, null, 7, $categoryId);
        }

        $followingIds = $user->following()->pluck('users.id')->toArray();
        if (empty($followingIds)) {
            // Cold-start fallback if user follows no one
            return $this->getTrendingFeed($user, $page, $perPage, null, 7, $categoryId);
        }

        $dismissedPostIds = $user->dismissedPosts()->pluck('post_id')->toArray();

        $query = Post::published()
            ->withRelations()
            ->whereIn('posts.user_id', $followingIds)
            ->where('posts.user_id', '!=', $user->id)
            ->whereNull('posts.deleted_at');

        if ($categoryId) {
            $query->where('posts.category_id', $categoryId);
        }

        if (! empty($dismissedPostIds)) {
            $query->whereNotIn('posts.id', $dismissedPostIds);
        }

        return $query->latest('posts.published_at')->paginate($perPage, ['*'], 'page', $page)->withQueryString();
    }

    /**
     * Retrieve latest published feed.
     */
    protected function getLatestFeed(
        ?User $user,
        int $page,
        int $perPage,
        ?array $guestPreferences = null,
        ?int $categoryId = null
    ): LengthAwarePaginator {
        $dismissedPostIds = $this->getDismissedPostIds($user, $guestPreferences);

        $query = Post::published()
            ->withRelations()
            ->whereNull('posts.deleted_at');

        if ($categoryId) {
            $query->where('posts.category_id', $categoryId);
        }

        if ($user) {
            $query->where('posts.user_id', '!=', $user->id);
        }

        if (! empty($dismissedPostIds)) {
            $query->whereNotIn('posts.id', $dismissedPostIds);
        }

        return $query->latest('posts.published_at')->paginate($perPage, ['*'], 'page', $page)->withQueryString();
    }

    /**
     * Retrieve trending feed (engagement log-scaled + recency in last N days).
     */
    public function getTrendingFeed(
        ?User $user,
        int $page = 1,
        int $perPage = 10,
        ?array $guestPreferences = null,
        int $days = 7,
        ?int $categoryId = null
    ): LengthAwarePaginator {
        $dismissedPostIds = $this->getDismissedPostIds($user, $guestPreferences);

        $query = Post::published()
            ->withRelations()
            ->where('posts.published_at', '>=', now()->subDays($days))
            ->whereNull('posts.deleted_at');

        if ($categoryId) {
            $query->where('posts.category_id', $categoryId);
        }

        if ($user) {
            $query->where('posts.user_id', '!=', $user->id);
        }

        if (! empty($dismissedPostIds)) {
            $query->whereNotIn('posts.id', $dismissedPostIds);
        }

        // If not enough posts in the last 7 days, broaden time window
        if ((clone $query)->count() < 5 && $days < 60) {
            return $this->getTrendingFeed($user, $page, $perPage, $guestPreferences, 60, $categoryId);
        }

        // Trending scoring formula:
        // (likes * 3 + comments * 2 + views * 0.2 + 1) / (age_in_hours + 2)^1.2
        $trendingScoreSql = '(((posts.likes_count * 3.0) + (posts.comments_count * 2.0) + (posts.views_count * 0.2) + 1.0) / '
            .'pow((((julianday(\'now\') - julianday(posts.published_at)) * 24.0) + 2.0), 1.2))';

        return $query
            ->select('posts.*')
            ->selectRaw("{$trendingScoreSql} as trending_score")
            ->orderByDesc('trending_score')
            ->orderByDesc('posts.published_at')
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * Find 1 exploration post: high engagement from an unvisited category outside user preferences.
     */
    public function getExplorationPost(array $signals, ?int $excludeUserId, array $excludePostIds): ?Post
    {
        $excludedCategories = array_unique(array_merge(
            $signals['preferred_category_ids'],
            array_keys($signals['category_affinity']),
            $signals['dismissed_category_ids']
        ));

        $query = Post::published()
            ->withRelations()
            ->whereNull('posts.deleted_at');

        if ($excludeUserId) {
            $query->where('posts.user_id', '!=', $excludeUserId);
        }

        if (! empty($excludePostIds)) {
            $query->whereNotIn('posts.id', $excludePostIds);
        }

        if (! empty($excludedCategories)) {
            $query->whereNotIn('posts.category_id', $excludedCategories);
        }

        // Rank exploration post by engagement and recency
        $query->orderByRaw('(views_count + (likes_count * 3) + (comments_count * 2)) DESC')
            ->latest('posts.published_at');

        return $query->first();
    }

    /**
     * Build the raw SQL mathematical expression for personalized scoring.
     */
    public function buildSqlScoreExpression(array $signals): string
    {
        // 1. Recency decay: 100 / (1 + age_hours * 0.05)
        $recencySql = '(100.0 / (1.0 + (((julianday(\'now\') - julianday(posts.published_at)) * 24.0) * 0.05)))';

        // 2. Category match: +35 if preferred
        $categorySql = '0.0';
        if (! empty($signals['preferred_category_ids'])) {
            $catIds = implode(',', array_map('intval', $signals['preferred_category_ids']));
            $categorySql = "(CASE WHEN posts.category_id IN ({$catIds}) THEN 35.0 ELSE 0.0 END)";
        }

        // 3. Category affinity from last 50 views/likes: up to +25
        $affinitySql = '0.0';
        if (! empty($signals['category_affinity'])) {
            $cases = [];
            foreach ($signals['category_affinity'] as $catId => $frequency) {
                $boost = min(25.0, round($frequency * 2.5, 2));
                $catIdInt = (int) $catId;
                $cases[] = "WHEN posts.category_id = {$catIdInt} THEN {$boost}";
            }
            $affinitySql = '(CASE '.implode(' ', $cases).' ELSE 0.0 END)';
        }

        // 4. Tag matches (+15 per tag) & tag affinity (+1.5 per tag frequency)
        $tagSql = '0.0';
        if (! empty($signals['preferred_tag_ids'])) {
            $tagIds = implode(',', array_map('intval', $signals['preferred_tag_ids']));
            $tagSql = "((SELECT COUNT(*) FROM post_tag WHERE post_tag.post_id = posts.id AND post_tag.tag_id IN ({$tagIds})) * 15.0)";
        }

        // 5. Followed author boost: +40
        $followedSql = '0.0';
        if (! empty($signals['followed_author_ids'])) {
            $authorIds = implode(',', array_map('intval', $signals['followed_author_ids']));
            $followedSql = "(CASE WHEN posts.user_id IN ({$authorIds}) THEN 40.0 ELSE 0.0 END)";
        }

        // 6. Engagement log-scaled
        $engagementSql = '((5.0 * log(1.0 + posts.likes_count)) + (4.0 * log(1.0 + posts.comments_count)) + (1.0 * log(1.0 + posts.views_count)))';

        // 7. Viewed penalty: -50
        $viewedSql = '0.0';
        if (! empty($signals['viewed_post_ids'])) {
            $viewedIds = implode(',', array_map('intval', array_slice($signals['viewed_post_ids'], 0, 100)));
            $viewedSql = "(CASE WHEN posts.id IN ({$viewedIds}) THEN -50.0 ELSE 0.0 END)";
        }

        // 8. Dismissed category penalty: -30
        $dismissedCatSql = '0.0';
        if (! empty($signals['dismissed_category_ids'])) {
            $dismissedCatIds = implode(',', array_map('intval', $signals['dismissed_category_ids']));
            $dismissedCatSql = "(CASE WHEN posts.category_id IN ({$dismissedCatIds}) THEN -30.0 ELSE 0.0 END)";
        }

        return "({$recencySql} + {$categorySql} + {$affinitySql} + {$tagSql} + {$followedSql} + {$engagementSql} + {$viewedSql} + {$dismissedCatSql})";
    }

    /**
     * Compute post score in PHP (for deterministic unit tests and offline scoring).
     */
    public function calculatePostScore(Post $post, array $signals): float
    {
        $score = 0.0;

        // 1. Recency decay
        $now = Carbon::now();
        $publishedAt = $post->published_at ? Carbon::parse($post->published_at) : $now;
        $ageHours = abs($now->diffInMinutes($publishedAt)) / 60.0;
        $score += (100.0 / (1.0 + $ageHours * 0.05));

        // 2. Category match (+35)
        if ($post->category_id && in_array((int) $post->category_id, $signals['preferred_category_ids'] ?? [])) {
            $score += 35.0;
        }

        // 3. Category affinity
        if ($post->category_id && isset($signals['category_affinity'][$post->category_id])) {
            $score += min(25.0, $signals['category_affinity'][$post->category_id] * 2.5);
        }

        // 4. Tag matches & affinity
        $postTagIds = $post->relationLoaded('tags') ? $post->tags->pluck('id')->map(fn ($id) => (int) $id)->toArray() : [];
        foreach ($postTagIds as $tagId) {
            if (in_array($tagId, $signals['preferred_tag_ids'] ?? [])) {
                $score += 15.0;
            }
            if (isset($signals['tag_affinity'][$tagId])) {
                $score += min(10.0, $signals['tag_affinity'][$tagId] * 1.5);
            }
        }

        // 5. Followed author boost (+40)
        if (in_array((int) $post->user_id, $signals['followed_author_ids'] ?? [])) {
            $score += 40.0;
        }

        // 6. Engagement (log-scaled)
        $likes = (float) ($post->likes_count ?? 0);
        $comments = (float) ($post->comments_count ?? 0);
        $views = (float) ($post->views_count ?? 0);
        $score += (5.0 * log10(1.0 + $likes)) + (4.0 * log10(1.0 + $comments)) + (1.0 * log10(1.0 + $views));

        // 7. Viewed penalty (-50)
        if (in_array((int) $post->id, $signals['viewed_post_ids'] ?? [])) {
            $score -= 50.0;
        }

        // 8. Dismissed category penalty (-30)
        if ($post->category_id && in_array((int) $post->category_id, $signals['dismissed_category_ids'] ?? [])) {
            $score -= 30.0;
        }

        return round($score, 4);
    }

    /**
     * Extract user engagement signals and preferences.
     */
    public function getUserSignals(?User $user, ?array $guestPreferences = null): array
    {
        if ($user) {
            $preferredCategoryIds = $user->preferredCategories()->pluck('categories.id')->map(fn ($id) => (int) $id)->toArray();
            $preferredTagIds = $user->preferredTags()->pluck('tags.id')->map(fn ($id) => (int) $id)->toArray();
            $followedAuthorIds = $user->following()->pluck('users.id')->map(fn ($id) => (int) $id)->toArray();
            $viewedPostIds = $user->postViews()->latest('viewed_at')->take(200)->pluck('post_id')->map(fn ($id) => (int) $id)->toArray();
            $dismissedPostIds = $user->dismissedPosts()->pluck('post_id')->map(fn ($id) => (int) $id)->toArray();
            $dismissedCategoryIds = $user->dismissedPosts()->whereNotNull('category_id')->pluck('category_id')->unique()->map(fn ($id) => (int) $id)->toArray();

            // Extract affinity from user's last 50 views and likes
            $engagedPostIds = DB::table('post_views')
                ->where('user_id', $user->id)
                ->select('post_id', 'viewed_at as engaged_at')
                ->union(
                    DB::table('likes')
                        ->where('user_id', $user->id)
                        ->select('post_id', 'created_at as engaged_at')
                )
                ->orderByDesc('engaged_at')
                ->limit(50)
                ->pluck('post_id')
                ->toArray();

            $categoryAffinity = [];
            $tagAffinity = [];

            if (! empty($engagedPostIds)) {
                $engagedPosts = Post::whereIn('id', $engagedPostIds)->with('tags')->get(['id', 'category_id']);
                foreach ($engagedPosts as $ep) {
                    if ($ep->category_id) {
                        $categoryAffinity[$ep->category_id] = ($categoryAffinity[$ep->category_id] ?? 0) + 1;
                    }
                    foreach ($ep->tags as $t) {
                        $tagAffinity[$t->id] = ($tagAffinity[$t->id] ?? 0) + 1;
                    }
                }
            }

            $hasSignals = ! empty($preferredCategoryIds)
                || ! empty($preferredTagIds)
                || ! empty($followedAuthorIds)
                || ! empty($categoryAffinity)
                || ! empty($tagAffinity);

            return [
                'has_signals' => $hasSignals,
                'preferred_category_ids' => $preferredCategoryIds,
                'preferred_tag_ids' => $preferredTagIds,
                'followed_author_ids' => $followedAuthorIds,
                'viewed_post_ids' => $viewedPostIds,
                'dismissed_post_ids' => $dismissedPostIds,
                'dismissed_category_ids' => $dismissedCategoryIds,
                'category_affinity' => $categoryAffinity,
                'tag_affinity' => $tagAffinity,
            ];
        }

        // Guest preferences fallback
        $guestCategories = ! empty($guestPreferences['categories']) ? array_map('intval', $guestPreferences['categories']) : [];
        $guestTags = ! empty($guestPreferences['tags']) ? array_map('intval', $guestPreferences['tags']) : [];
        $guestViewed = ! empty($guestPreferences['viewed']) ? array_map('intval', $guestPreferences['viewed']) : [];
        $guestDismissed = ! empty($guestPreferences['dismissed']) ? array_map('intval', $guestPreferences['dismissed']) : [];
        $guestDismissedCategories = ! empty($guestPreferences['dismissed_categories']) ? array_map('intval', $guestPreferences['dismissed_categories']) : [];

        $hasSignals = ! empty($guestCategories) || ! empty($guestTags) || ! empty($guestViewed);

        return [
            'has_signals' => $hasSignals,
            'preferred_category_ids' => $guestCategories,
            'preferred_tag_ids' => $guestTags,
            'followed_author_ids' => [],
            'viewed_post_ids' => $guestViewed,
            'dismissed_post_ids' => $guestDismissed,
            'dismissed_category_ids' => $guestDismissedCategories,
            'category_affinity' => [],
            'tag_affinity' => [],
        ];
    }

    /**
     * Get hero/featured post for front page (admin-set is_featured or top trending).
     */
    public function getHeroPost(): ?Post
    {
        $featured = Post::published()
            ->featured()
            ->withRelations()
            ->latest('published_at')
            ->first();

        if ($featured) {
            return $featured;
        }

        // Fallback: top story in last 14 days
        return Post::published()
            ->withRelations()
            ->where('published_at', '>=', now()->subDays(14))
            ->popular()
            ->first();
    }

    /**
     * Get sidebar data (popular categories, trending tags, top writers).
     */
    public function getSidebarData(?User $user): array
    {
        return Cache::remember('feed_sidebar_data', 600, function () {
            // Popular categories with post count
            $categories = Category::whereHas('posts', fn ($q) => $q->published())
                ->withCount(['posts' => fn ($q) => $q->published()])
                ->orderByDesc('posts_count')
                ->take(6)
                ->get();

            // Trending tags with post count
            $tags = Tag::whereHas('posts', fn ($q) => $q->published())
                ->withCount(['posts' => fn ($q) => $q->published()])
                ->orderByDesc('posts_count')
                ->take(10)
                ->get();

            // Top writers (users with published stories and followers count)
            $topWriters = User::whereHas('posts', fn ($q) => $q->published())
                ->withCount([
                    'posts' => fn ($q) => $q->published(),
                    'followers',
                ])
                ->orderByDesc('followers_count')
                ->orderByDesc('posts_count')
                ->take(5)
                ->get();

            return [
                'categories' => $categories,
                'tags' => $tags,
                'top_writers' => $topWriters,
            ];
        });
    }

    /**
     * Helper to get dismissed post IDs.
     */
    protected function getDismissedPostIds(?User $user, ?array $guestPreferences): array
    {
        if ($user) {
            return $user->dismissedPosts()->pluck('post_id')->toArray();
        }

        return ! empty($guestPreferences['dismissed']) ? array_map('intval', $guestPreferences['dismissed']) : [];
    }

    /**
     * Record a "Not interested" post dismissal.
     */
    public function recordDismissal(User $user, Post $post): void
    {
        PostDismissal::firstOrCreate([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ], [
            'category_id' => $post->category_id,
        ]);

        $this->invalidateUserFeedCache($user);
    }

    /**
     * Remove a "Not interested" dismissal (undo).
     */
    public function undoDismissal(User $user, Post $post): void
    {
        PostDismissal::where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->delete();

        $this->invalidateUserFeedCache($user);
    }

    /**
     * Invalidate user feed cache upon preference change, follow, like, or dismissal.
     */
    public function invalidateUserFeedCache(int|User $user): void
    {
        $userId = is_numeric($user) ? (int) $user : $user->id;
        Cache::increment("feed_version_{$userId}");
    }
}
