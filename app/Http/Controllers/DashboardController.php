<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display writer dashboard with tabs, filters, stats, and post list.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $tab = $request->input('tab', 'published');
        if (! in_array($tab, ['published', 'drafts', 'scheduled', 'archived'], true)) {
            $tab = 'published';
        }

        $search = $request->input('search');
        $sort = $request->input('sort', 'latest');

        // Status counts
        $counts = [
            'published' => $user->posts()->where('status', PostStatus::PUBLISHED)->count(),
            'drafts' => $user->posts()->where('status', PostStatus::DRAFT)->count(),
            'scheduled' => $user->posts()->where('status', PostStatus::SCHEDULED)->count(),
            'archived' => $user->posts()->where('status', PostStatus::ARCHIVED)->count(),
        ];

        // Overall stats
        $stats = [
            'total_views' => (int) $user->posts()->sum('views_count'),
            'total_likes' => (int) $user->posts()->sum('likes_count'),
            'total_comments' => (int) $user->posts()->sum('comments_count'),
            'total_stories' => (int) $user->posts()->count(),
        ];

        $targetStatus = match ($tab) {
            'drafts' => PostStatus::DRAFT,
            'scheduled' => PostStatus::SCHEDULED,
            'archived' => PostStatus::ARCHIVED,
            default => PostStatus::PUBLISHED,
        };

        $posts = $user->posts()
            ->where('status', $targetStatus)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%");
                });
            })
            ->with(['category', 'tags'])
            ->when($sort === 'oldest', fn ($q) => $q->oldest('created_at'))
            ->when($sort === 'views', fn ($q) => $q->orderByDesc('views_count'))
            ->when($sort === 'likes', fn ($q) => $q->orderByDesc('likes_count'))
            ->when($sort === 'comments', fn ($q) => $q->orderByDesc('comments_count'))
            ->when($sort === 'title', fn ($q) => $q->orderBy('title'))
            ->when(! in_array($sort, ['oldest', 'views', 'likes', 'comments', 'title'], true), function ($q) use ($tab) {
                if ($tab === 'scheduled') {
                    $q->orderBy('published_at');
                } elseif ($tab === 'published') {
                    $q->latest('published_at');
                } else {
                    $q->latest('updated_at');
                }
            })
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Dashboard', [
            'posts' => $posts,
            'counts' => $counts,
            'stats' => $stats,
            'filters' => [
                'tab' => $tab,
                'search' => $search ?? '',
                'sort' => $sort,
            ],
        ]);
    }

    /**
     * Perform bulk actions on selected stories.
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
            'action' => ['required', 'string', 'in:publish,unpublish,archive,delete'],
        ]);

        $user = Auth::user();
        $posts = $user->posts()->whereIn('id', $validated['ids'])->get();

        foreach ($posts as $post) {
            match ($validated['action']) {
                'delete' => $post->delete(),
                'archive' => $post->update(['status' => PostStatus::ARCHIVED]),
                'unpublish' => $post->update(['status' => PostStatus::DRAFT, 'published_at' => null]),
                'publish' => (function () use ($post) {
                    $wordCount = str_word_count(strip_tags((string) $post->content_html));
                    if (! empty(trim($post->title)) && $wordCount >= 100) {
                        $post->update([
                            'status' => PostStatus::PUBLISHED,
                            'published_at' => now(),
                        ]);
                    }
                })(),
            };
        }

        return back();
    }
}
