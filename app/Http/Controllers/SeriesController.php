<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Http\Requests\StoreSeriesRequest;
use App\Http\Requests\UpdateSeriesRequest;
use App\Models\Post;
use App\Models\Series;
use App\Models\SeriesProgress;
use App\Services\SeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SeriesController extends Controller
{
    public function __construct(
        protected SeoService $seoService
    ) {}

    /**
     * Display a paginated list of public series.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $series = Series::with(['author'])
            ->withPublishedCount()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Series/Index', [
            'series' => $series,
            'filters' => [
                'search' => $search ?? '',
            ],
            'seo' => [
                'title' => 'Story Series - Inkwell',
                'description' => 'Browse multi-part stories, tutorials, and sagas written by our community.',
                'canonical_url' => url('/series'),
            ],
        ]);
    }

    /**
     * Display a single series page with its ordered parts.
     */
    public function show(Request $request, Series $series): Response
    {
        $viewer = Auth::user();
        $isAuthor = $viewer && $viewer->id === $series->user_id;

        $series->load(['author']);
        $series->loadCount('followers');

        // Fetch parts: author sees drafts too, readers only published
        $postsQuery = $series->posts()->orderBy('series_order', 'asc');
        if (! $isAuthor) {
            $postsQuery->published();
        }

        $parts = $postsQuery->get([
            'id',
            'user_id',
            'category_id',
            'title',
            'slug',
            'excerpt',
            'cover_image_path',
            'status',
            'published_at',
            'reading_time_minutes',
            'series_order',
        ])->load('category');

        $publishedCount = 0;
        $totalReadingTime = 0;
        $formattedParts = [];

        foreach ($parts as $part) {
            $isPublished = $part->status === PostStatus::PUBLISHED && $part->published_at && $part->published_at->lte(now());

            if ($isPublished) {
                $publishedCount++;
                $displayNumber = $publishedCount;
                $totalReadingTime += $part->reading_time_minutes ?? 1;
            } else {
                $displayNumber = null;
            }

            $formattedParts[] = [
                'id' => $part->id,
                'title' => $part->title,
                'slug' => $part->slug,
                'excerpt' => $part->excerpt,
                'cover_image_path' => $part->cover_image_path,
                'status' => $part->status->value,
                'is_published' => $isPublished,
                'display_number' => $displayNumber,
                'reading_time_minutes' => $part->reading_time_minutes,
                'published_at' => $part->published_at?->format('M j, Y'),
                'category' => $part->category ? [
                    'id' => $part->category->id,
                    'name' => $part->category->name,
                    'slug' => $part->category->slug,
                ] : null,
            ];
        }

        // Determine Start Reading and Continue Reading links
        $firstPublishedPart = collect($formattedParts)->firstWhere('is_published', true);

        $continuePart = null;
        if ($viewer) {
            $progress = SeriesProgress::where('user_id', $viewer->id)
                ->where('series_id', $series->id)
                ->first();

            if ($progress) {
                // Find next part after last_post_id among published parts
                $publishedList = collect($formattedParts)->where('is_published', true)->values();
                $lastIndex = $publishedList->search(fn ($p) => $p['id'] === $progress->last_post_id);

                if ($lastIndex !== false && $lastIndex + 1 < $publishedList->count()) {
                    $continuePart = $publishedList[$lastIndex + 1];
                } elseif ($lastIndex !== false) {
                    // Already at the latest part, continue links to that part
                    $continuePart = $publishedList[$lastIndex];
                }
            }
        }

        $isFollowing = $series->isFollowedBy($viewer);

        return Inertia::render('Series/Show', [
            'series' => [
                'id' => $series->id,
                'title' => $series->title,
                'slug' => $series->slug,
                'description' => $series->description,
                'cover_image_path' => $series->cover_image_path,
                'status' => $series->status->value,
                'status_label' => $series->status->label(),
                'followers_count' => $series->followers_count,
                'published_posts_count' => $publishedCount,
                'total_reading_time' => $totalReadingTime,
                'author' => [
                    'id' => $series->author->id,
                    'name' => $series->author->name,
                    'username' => $series->author->username,
                    'bio' => $series->author->bio,
                    'avatar_url' => $series->author->avatar_url,
                ],
                'created_at' => $series->created_at->format('M Y'),
            ],
            'parts' => $formattedParts,
            'firstPart' => $firstPublishedPart,
            'continuePart' => $continuePart,
            'isFollowing' => $isFollowing,
            'isAuthor' => $isAuthor,
            'seo' => $this->seoService->forSeries($series, $publishedCount),
        ]);
    }

    /**
     * Store a newly created series.
     */
    public function store(StoreSeriesRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $series = Auth::user()->series()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'cover_image_path' => $validated['cover_image_path'] ?? null,
            'status' => $validated['status'] ?? 'ongoing',
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'series' => [
                    'id' => $series->id,
                    'title' => $series->title,
                    'slug' => $series->slug,
                    'status' => $series->status->value,
                    'posts_count' => 0,
                ],
            ]);
        }

        return redirect()->route('series.show', $series->slug)->with('success', 'Series created successfully.');
    }

    /**
     * Update an existing series.
     */
    public function update(UpdateSeriesRequest $request, Series $series): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $series);

        $validated = $request->validated();

        $series->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'cover_image_path' => $validated['cover_image_path'] ?? null,
            'status' => $validated['status'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'series' => $series,
            ]);
        }

        return back()->with('success', 'Series updated successfully.');
    }

    /**
     * Delete an existing series (detaches posts without deleting them).
     */
    public function destroy(Series $series): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $series);

        $series->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('dashboard', ['tab' => 'series'])->with('success', 'Series deleted. Stories have been detached.');
    }

    /**
     * Reorder posts in this series.
     */
    public function reorder(Request $request, Series $series): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $series);

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['required', 'integer'],
        ]);

        $series->reorderParts($validated['ordered_ids']);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Parts reordered successfully.');
    }

    /**
     * Toggle following a series.
     */
    public function toggleFollow(Request $request, Series $series): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }
            return redirect()->route('login');
        }

        $exists = $series->followers()->where('user_id', $user->id)->exists();

        if ($exists) {
            $series->followers()->detach($user->id);
            $isFollowing = false;
        } else {
            $series->followers()->attach($user->id);
            $isFollowing = true;
        }

        $count = $series->followers()->count();

        if ($request->wantsJson()) {
            return response()->json([
                'is_following' => $isFollowing,
                'followers_count' => $count,
            ]);
        }

        return back();
    }
}
