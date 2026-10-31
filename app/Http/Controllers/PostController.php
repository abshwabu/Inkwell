<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Jobs\IncrementPostViewJob;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostView;
use App\Models\Series;
use App\Models\Tag;
use App\Services\BotDetectorService;
use App\Services\ContentSanitizerService;
use App\Services\SeoService;
use App\Services\SeriesService;
use App\Services\SpamProtectionService;
use App\Services\TableOfContentsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function __construct(
        protected ContentSanitizerService $sanitizer,
        protected BotDetectorService $botDetector,
        protected TableOfContentsService $tocService,
        protected SpamProtectionService $spamProtectionService,
        protected SeoService $seoService,
        protected SeriesService $seriesService
    ) {}

    /**
     * Display a published post publicly at canonical URL /{category-slug}/{post-slug}.
     */
    public function showCanonical(Request $request, string $category_slug, string $post_slug): Response|RedirectResponse
    {
        $post = Post::withTrashed()->where('slug', $post_slug)->first();
        if (! $post && is_numeric($post_slug)) {
            $post = Post::withTrashed()->find((int) $post_slug);
        }

        if ($post && $post->trashed()) {
            abort(410, 'This story has been permanently removed.');
        }

        if (! $post) {
            abort(404, 'Story not found.');
        }

        $canonicalCategory = $post->category ? $post->category->slug : 'story';

        // 301 Redirect if category slug in URL doesn't match canonical
        if ($category_slug !== $canonicalCategory) {
            return redirect()->route('posts.show.canonical', [
                'category_slug' => $canonicalCategory,
                'post_slug' => $post->slug,
            ], 301);
        }

        return $this->renderPostView($request, $post);
    }

    /**
     * Short post route /p/{slug} with 301 redirect to canonical /{category-slug}/{post-slug}.
     */
    public function showShort(Request $request, string $post_slug): RedirectResponse
    {
        $post = Post::withTrashed()->where('slug', $post_slug)->first();
        if (! $post && is_numeric($post_slug)) {
            $post = Post::withTrashed()->find((int) $post_slug);
        }

        if ($post && $post->trashed()) {
            abort(410, 'This story has been permanently removed.');
        }

        if (! $post) {
            abort(404, 'Story not found.');
        }

        $categorySlug = $post->category ? $post->category->slug : 'story';

        return redirect()->route('posts.show.canonical', [
            'category_slug' => $categorySlug,
            'post_slug' => $post->slug,
        ], 301);
    }

    /**
     * Legacy route /posts/{post:slug}.
     */
    public function show(Request $request, Post $post): Response
    {
        return $this->renderPostView($request, $post);
    }

    /**
     * Common rendering pipeline for public story reading.
     */
    protected function renderPostView(Request $request, Post $post): Response
    {
        Gate::authorize('view', $post);

        // Deduplicated bot-filtered view count tracking
        if (! $this->botDetector->isBot($request)) {
            $userId = Auth::id();
            $sessionHash = hash('sha256', ($request->session()->getId() ?? 'guest') . '|' . ($request->ip() ?? '') . '|' . ($request->header('User-Agent') ?? ''));

            $alreadyViewed = PostView::where('post_id', $post->id)
                ->where(function ($q) use ($sessionHash, $userId) {
                    $q->where('session_hash', $sessionHash);
                    if ($userId) {
                        $q->orWhere('user_id', $userId);
                    }
                })
                ->where('viewed_at', '>=', now()->subHours(24))
                ->exists();

            if (! $alreadyViewed) {
                IncrementPostViewJob::dispatch($post->id, $userId, $sessionHash);
            }
        }

        $post->load([
            'user',
            'category',
            'tags',
            'series',
            'comments' => fn ($query) => $query->withTrashed()
                ->where('status', \App\Enums\CommentStatus::VISIBLE)
                ->whereNull('parent_id')
                ->with([
                    'user',
                    'replies' => fn ($rq) => $rq->withTrashed()->where('status', \App\Enums\CommentStatus::VISIBLE)->with('user')->oldest(),
                ])
                ->latest(),
        ]);

        // Record reading progress and build series context
        $this->seriesService->recordProgress(Auth::user(), $post);
        $seriesContext = $this->seriesService->getSeriesContextForPost($post, Auth::user());

        // Auto-generate Table of Contents from headings
        $tocResult = $this->tocService->extractAndInject($post->content_html ?? '');
        $post->content_html = $tocResult['html'];
        $toc = $tocResult['toc'];

        // Related posts (same category or sharing tags, excluding current post)
        $relatedPosts = Post::published()
            ->where('id', '!=', $post->id)
            ->where(function ($q) use ($post) {
                if ($post->category_id) {
                    $q->where('category_id', $post->category_id);
                }
                if ($post->tags->isNotEmpty()) {
                    $q->orWhereHas('tags', fn ($tq) => $tq->whereIn('tags.id', $post->tags->pluck('id')));
                }
            })
            ->with(['user', 'category'])
            ->popular()
            ->take(3)
            ->get(['id', 'title', 'slug', 'excerpt', 'cover_image_path', 'category_id', 'user_id', 'reading_time_minutes', 'published_at']);

        // Previous and Next stories from same author
        $previousPost = null;
        $nextPost = null;
        if ($post->published_at) {
            $previousPost = Post::published()
                ->where('user_id', $post->user_id)
                ->where('id', '!=', $post->id)
                ->where('published_at', '<', $post->published_at)
                ->latest('published_at')
                ->with('category')
                ->first(['id', 'title', 'slug', 'category_id', 'published_at']);

            $nextPost = Post::published()
                ->where('user_id', $post->user_id)
                ->where('id', '!=', $post->id)
                ->where('published_at', '>', $post->published_at)
                ->oldest('published_at')
                ->with('category')
                ->first(['id', 'title', 'slug', 'category_id', 'published_at']);
        }

        $authorFollowersCount = $post->user->followers()->count();
        $isFollowingAuthor = Auth::check() ? Auth::user()->isFollowing($post->user) : false;
        $isLiked = Auth::check() ? Auth::user()->likedPosts()->where('post_id', $post->id)->exists() : false;
        $isBookmarked = Auth::check() ? Auth::user()->bookmarkedPosts()->where('post_id', $post->id)->exists() : false;

        return Inertia::render('Posts/Show', [
            'post' => $post,
            'toc' => $toc,
            'relatedPosts' => $relatedPosts,
            'previousPost' => $previousPost,
            'nextPost' => $nextPost,
            'authorStats' => [
                'followers_count' => $authorFollowersCount,
                'is_following' => $isFollowingAuthor,
            ],
            'isLiked' => $isLiked,
            'isBookmarked' => $isBookmarked,
            'seriesContext' => $seriesContext,
            'canEdit' => Auth::check() && Auth::user()->can('update', $post),
            'canDelete' => Auth::check() && Auth::user()->can('delete', $post),
            'canPublish' => Auth::check() && Auth::user()->can('publish', $post),
            'seo' => $this->seoService->forPost($post),
        ]);
    }

    /**
     * Show distraction-free form to write a new post (Auth required).
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Post::class);

        $initialSeries = null;
        if ($request->has('series')) {
            $seriesSlug = $request->input('series');
            $series = Series::where('slug', $seriesSlug)->firstOrFail();

            if ($series->user_id !== Auth::id()) {
                abort(403, 'Unauthorized to add stories to this series.');
            }

            $nextOrder = ($series->posts()->max('series_order') ?? 0) + 1;
            $initialSeries = [
                'id' => $series->id,
                'title' => $series->title,
                'slug' => $series->slug,
                'next_order' => $nextOrder,
            ];
        }

        $userSeries = Auth::user()->series()
            ->withCount('posts')
            ->latest()
            ->get(['id', 'title', 'slug', 'status']);

        return Inertia::render('Posts/Create', [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'availableTags' => Tag::orderBy('name')->get(['id', 'name', 'slug']),
            'userSeries' => $userSeries,
            'initialSeries' => $initialSeries,
        ]);
    }

    /**
     * Store a newly created post (or finalize an autosaved draft).
     */
    public function store(StorePostRequest $request): RedirectResponse
    {
        $this->spamProtectionService->checkHoneypot($request);

        $validated = $request->validated();

        $rawHtml = $validated['content_html'] ?? '';
        if (empty($rawHtml) && ! empty($validated['content_json'])) {
            $rawHtml = $this->sanitizer->renderFromJSON($validated['content_json']);
        }
        $sanitizedHtml = $this->sanitizer->sanitize($rawHtml);

        $statusStr = $validated['status'] ?? 'draft';
        $status = match ($statusStr) {
            'published' => PostStatus::PUBLISHED,
            'scheduled' => PostStatus::SCHEDULED,
            'archived' => PostStatus::ARCHIVED,
            default => PostStatus::DRAFT,
        };

        $publishedAt = null;
        if ($status === PostStatus::PUBLISHED) {
            $publishedAt = now();
        } elseif ($status === PostStatus::SCHEDULED && ! empty($validated['published_at'])) {
            $publishedAt = Carbon::parse($validated['published_at']);
        }

        $title = ! empty(trim($validated['title'] ?? '')) ? trim($validated['title']) : 'Untitled Draft';
        $excerpt = $validated['excerpt'] ?? Str::limit(strip_tags($sanitizedHtml), 160);

        $this->spamProtectionService->validateBlockedWords($title, $rawHtml);

        $seriesId = $validated['series_id'] ?? null;
        $seriesOrder = null;
        if ($seriesId) {
            $series = Series::where('id', $seriesId)->where('user_id', Auth::id())->firstOrFail();
            $seriesOrder = ! empty($validated['series_order'])
                ? (int) $validated['series_order']
                : (($series->posts()->max('series_order') ?? 0) + 1);
        }

        // If finalizing an existing autosaved draft
        if (! empty($validated['id'])) {
            $post = Post::findOrFail($validated['id']);
            Gate::authorize('update', $post);

            $updateData = [
                'title' => $title,
                'category_id' => $validated['category_id'] ?? null,
                'series_id' => $seriesId,
                'series_order' => $seriesOrder,
                'excerpt' => $excerpt,
                'cover_image_path' => $validated['cover_image_path'] ?? null,
                'seo_title' => $validated['seo_title'] ?? null,
                'seo_description' => $validated['seo_description'] ?? null,
                'content_json' => $validated['content_json'] ?? null,
                'content_html' => $sanitizedHtml,
                'status' => $status,
                'published_at' => $publishedAt ?? $post->published_at,
            ];

            if (! empty($validated['slug'])) {
                $updateData['slug'] = Str::slug($validated['slug']);
            }

            $post->update($updateData);
            $this->syncTags($post, $validated['tags'] ?? []);

            if ($status === PostStatus::PUBLISHED && $post->series_id) {
                $this->seriesService->notifyFollowers($post);
            }

            return redirect()->route($status === PostStatus::PUBLISHED ? 'posts.show' : 'dashboard', $status === PostStatus::PUBLISHED ? $post->slug : []);
        }

        $this->spamProtectionService->checkDailyPostLimit(Auth::user());

        $createData = [
            'user_id' => Auth::id(),
            'category_id' => $validated['category_id'] ?? null,
            'series_id' => $seriesId,
            'series_order' => $seriesOrder,
            'title' => $title,
            'excerpt' => $excerpt,
            'cover_image_path' => $validated['cover_image_path'] ?? null,
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
            'content_json' => $validated['content_json'] ?? null,
            'content_html' => $sanitizedHtml,
            'status' => $status,
            'published_at' => $publishedAt,
        ];

        if (! empty($validated['slug'])) {
            $createData['slug'] = Str::slug($validated['slug']);
        }

        $post = Post::create($createData);
        $this->syncTags($post, $validated['tags'] ?? []);

        if ($status === PostStatus::PUBLISHED && $post->series_id) {
            $this->seriesService->notifyFollowers($post);
        }

        return redirect()->route($status === PostStatus::PUBLISHED ? 'posts.show' : 'dashboard', $status === PostStatus::PUBLISHED ? $post->slug : []);
    }

    /**
     * Show distraction-free edit form (Auth required).
     */
    public function edit(Post $post): Response
    {
        Gate::authorize('update', $post);

        $post->load(['tags', 'category', 'series']);

        $userSeries = Auth::user()->series()
            ->withCount('posts')
            ->latest()
            ->get(['id', 'title', 'slug', 'status']);

        return Inertia::render('Posts/Edit', [
            'post' => $post,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'availableTags' => Tag::orderBy('name')->get(['id', 'name', 'slug']),
            'userSeries' => $userSeries,
        ]);
    }

    /**
     * Update post (Auth required).
     */
    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        Gate::authorize('update', $post);

        $validated = $request->validated();

        $rawHtml = $validated['content_html'] ?? $post->content_html;
        if (empty($rawHtml) && ! empty($validated['content_json'])) {
            $rawHtml = $this->sanitizer->renderFromJSON($validated['content_json']);
        }
        $sanitizedHtml = $this->sanitizer->sanitize($rawHtml);

        $statusStr = $validated['status'] ?? $post->status->value;
        $status = match ($statusStr) {
            'published' => PostStatus::PUBLISHED,
            'scheduled' => PostStatus::SCHEDULED,
            'archived' => PostStatus::ARCHIVED,
            default => PostStatus::DRAFT,
        };

        $publishedAt = $post->published_at;
        if ($status === PostStatus::PUBLISHED && ! $post->published_at) {
            $publishedAt = now();
        } elseif ($status === PostStatus::SCHEDULED && ! empty($validated['published_at'])) {
            $publishedAt = Carbon::parse($validated['published_at']);
        } elseif ($status === PostStatus::DRAFT) {
            $publishedAt = null;
        }

        $title = ! empty(trim($validated['title'] ?? '')) ? trim($validated['title']) : $post->title;
        $excerpt = $validated['excerpt'] ?? Str::limit(strip_tags($sanitizedHtml), 160);

        $this->spamProtectionService->validateBlockedWords($title, $rawHtml);

        $seriesId = array_key_exists('series_id', $validated) ? $validated['series_id'] : $post->series_id;
        $seriesOrder = $post->series_order;

        if ($seriesId) {
            $series = Series::where('id', $seriesId)->where('user_id', Auth::id())->firstOrFail();
            if ($seriesId != $post->series_id || empty($seriesOrder)) {
                $seriesOrder = ! empty($validated['series_order'])
                    ? (int) $validated['series_order']
                    : (($series->posts()->max('series_order') ?? 0) + 1);
            } elseif (! empty($validated['series_order'])) {
                $seriesOrder = (int) $validated['series_order'];
            }
        } else {
            $seriesId = null;
            $seriesOrder = null;
        }

        $updateData = [
            'title' => $title,
            'category_id' => $validated['category_id'] ?? null,
            'series_id' => $seriesId,
            'series_order' => $seriesOrder,
            'excerpt' => $excerpt,
            'cover_image_path' => $validated['cover_image_path'] ?? null,
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
            'content_json' => $validated['content_json'] ?? $post->content_json,
            'content_html' => $sanitizedHtml,
            'status' => $status,
            'published_at' => $publishedAt,
        ];

        if (! empty($validated['slug']) && $validated['slug'] !== $post->slug) {
            $updateData['slug'] = Str::slug($validated['slug']);
        }

        $post->update($updateData);

        if (isset($validated['tags'])) {
            $this->syncTags($post, $validated['tags']);
        }

        if ($status === PostStatus::PUBLISHED && $post->series_id) {
            $this->seriesService->notifyFollowers($post);
        }

        return redirect()->route($status === PostStatus::PUBLISHED ? 'posts.show' : 'dashboard', $status === PostStatus::PUBLISHED ? $post->slug : []);
    }

    /**
     * Autosave post draft every 20 seconds.
     */
    public function autosave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:posts,id',
            'title' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'series_id' => 'nullable|exists:series,id',
            'series_order' => 'nullable|integer',
            'excerpt' => 'nullable|string|max:500',
            'cover_image_path' => 'nullable|string|max:1000',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
            'content_json' => 'nullable|array',
            'content_html' => 'nullable|string',
            'tags' => 'nullable|array|max:5',
            'tags.*' => 'string|max:50',
        ]);

        $rawHtml = $validated['content_html'] ?? '';
        if (empty($rawHtml) && ! empty($validated['content_json'])) {
            $rawHtml = $this->sanitizer->renderFromJSON($validated['content_json']);
        }
        $sanitizedHtml = $this->sanitizer->sanitize($rawHtml);

        $title = ! empty(trim($validated['title'] ?? '')) ? trim($validated['title']) : 'Untitled Draft';

        if (! empty($validated['id'])) {
            $post = Post::findOrFail($validated['id']);
            Gate::authorize('update', $post);

            $updateData = [
                'title' => $title,
                'category_id' => $validated['category_id'] ?? $post->category_id,
                'excerpt' => $validated['excerpt'] ?? $post->excerpt,
                'cover_image_path' => $validated['cover_image_path'] ?? $post->cover_image_path,
                'seo_title' => $validated['seo_title'] ?? $post->seo_title,
                'seo_description' => $validated['seo_description'] ?? $post->seo_description,
                'content_json' => $validated['content_json'] ?? $post->content_json,
                'content_html' => $sanitizedHtml,
            ];

            if (array_key_exists('series_id', $validated)) {
                $updateData['series_id'] = $validated['series_id'];
                $updateData['series_order'] = $validated['series_order'] ?? $post->series_order;
            }

            if (! empty($validated['slug']) && $validated['slug'] !== $post->slug) {
                $slugCandidate = Str::slug($validated['slug']);
                if (! Post::where('slug', $slugCandidate)->where('id', '!=', $post->id)->exists()) {
                    $updateData['slug'] = $slugCandidate;
                }
            }

            $post->update($updateData);

            if (isset($validated['tags'])) {
                $this->syncTags($post, $validated['tags']);
            }
        } else {
            Gate::authorize('create', Post::class);

            $createData = [
                'user_id' => Auth::id(),
                'title' => $title,
                'category_id' => $validated['category_id'] ?? null,
                'series_id' => $validated['series_id'] ?? null,
                'series_order' => $validated['series_order'] ?? null,
                'excerpt' => $validated['excerpt'] ?? null,
                'cover_image_path' => $validated['cover_image_path'] ?? null,
                'seo_title' => $validated['seo_title'] ?? null,
                'seo_description' => $validated['seo_description'] ?? null,
                'content_json' => $validated['content_json'] ?? null,
                'content_html' => $sanitizedHtml,
                'status' => PostStatus::DRAFT,
                'published_at' => null,
            ];

            if (! empty($validated['slug'])) {
                $createData['slug'] = Str::slug($validated['slug']);
            }

            $post = Post::create($createData);

            if (! empty($validated['tags'])) {
                $this->syncTags($post, $validated['tags']);
            }
        }

        return response()->json([
            'id' => $post->id,
            'slug' => $post->slug,
            'saved_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Toggle or set published state (Auth required, author or admin).
     */
    public function publish(Post $post): RedirectResponse
    {
        Gate::authorize('publish', $post);

        if ($post->status === PostStatus::PUBLISHED) {
            $post->update([
                'status' => PostStatus::DRAFT,
                'published_at' => null,
            ]);
        } else {
            $wordCount = str_word_count(strip_tags((string) $post->content_html));
            if (empty(trim($post->title)) || $wordCount < 100) {
                return back()->withErrors([
                    'publish' => 'A title and at least 100 words are required to publish.',
                ]);
            }

            $post->update([
                'status' => PostStatus::PUBLISHED,
                'published_at' => now(),
            ]);

            if ($post->series_id) {
                $this->seriesService->notifyFollowers($post);
            }
        }

        return back();
    }

    /**
     * Unpublish post back to draft (Auth required).
     */
    public function unpublish(Post $post): RedirectResponse
    {
        Gate::authorize('publish', $post);

        $post->update([
            'status' => PostStatus::DRAFT,
            'published_at' => null,
        ]);

        return back();
    }

    /**
     * Archive post (Auth required).
     */
    public function archive(Post $post): RedirectResponse
    {
        Gate::authorize('update', $post);

        $post->update([
            'status' => PostStatus::ARCHIVED,
        ]);

        return back();
    }

    /**
     * Schedule post for future publication.
     */
    public function schedule(Request $request, Post $post): RedirectResponse
    {
        Gate::authorize('publish', $post);

        $validated = $request->validate([
            'published_at' => ['required', 'date', 'after:now'],
        ]);

        $wordCount = str_word_count(strip_tags((string) $post->content_html));
        if (empty(trim($post->title)) || $wordCount < 100) {
            return back()->withErrors([
                'schedule' => 'A title and at least 100 words are required to schedule a story.',
            ]);
        }

        $post->update([
            'status' => PostStatus::SCHEDULED,
            'published_at' => Carbon::parse($validated['published_at']),
        ]);

        return back();
    }

    /**
     * Soft delete post (Auth required).
     */
    public function destroy(Post $post): RedirectResponse
    {
        Gate::authorize('delete', $post);

        $post->delete();

        return redirect()->route('dashboard');
    }

    /**
     * Synchronize tags with autocomplete support and on-the-fly creation up to 5 tags.
     */
    protected function syncTags(Post $post, array $tagInputs): void
    {
        $tagIds = [];

        foreach (array_slice($tagInputs, 0, 5) as $input) {
            if (is_numeric($input)) {
                $existing = Tag::find((int) $input);
                if ($existing) {
                    $tagIds[] = $existing->id;
                    continue;
                }
            }

            if (is_string($input) && ! empty(trim($input))) {
                $name = trim($input);
                $slug = Str::slug($name);
                if (! empty($slug)) {
                    $tag = Tag::firstOrCreate(
                        ['slug' => $slug],
                        ['name' => $name]
                    );
                    $tagIds[] = $tag->id;
                }
            }
        }

        $post->tags()->sync(array_unique(array_slice($tagIds, 0, 5)));
    }
}
