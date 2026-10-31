<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Services\FeedService;
use App\Services\SeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Inertia\Response;

class FeedController extends Controller
{
    public function __construct(
        protected FeedService $feedService,
        protected SeoService $seoService
    ) {}

    /**
     * Render the front page feed.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        // Default tab: 'for-you' for logged in, 'trending' for guests
        $defaultTab = $user ? 'for-you' : 'trending';
        $tab = $request->input('tab', $defaultTab);

        // Allowed tabs
        $allowedTabs = $user
            ? ['for-you', 'following', 'latest', 'trending']
            : ['trending', 'latest', 'for-you'];

        if (! in_array($tab, $allowedTabs)) {
            $tab = $defaultTab;
        }

        // Decode guest cookie preferences if guest
        $guestPrefs = [];
        $guestPrefsCookie = $request->cookie('inkwell_guest_prefs');
        if (! $user && $guestPrefsCookie) {
            $decoded = json_decode($guestPrefsCookie, true);
            if (is_array($decoded)) {
                $guestPrefs = $decoded;
            }
        }

        // If guest selected a category filter query param, add it to guest preferences
        $selectedCategorySlug = $request->input('category');
        if ($selectedCategorySlug) {
            $category = Category::where('slug', $selectedCategorySlug)->first();
            if ($category) {
                $guestPrefs['categories'] = array_unique(array_merge($guestPrefs['categories'] ?? [], [$category->id]));
            }
        }

        $page = (int) $request->input('page', 1);
        $perPage = 10;

        $posts = $this->feedService->getFeed($user, $tab, $page, $perPage, $guestPrefs);
        $heroPost = $this->feedService->getHeroPost();
        $sidebarData = $this->feedService->getSidebarData($user);

        $categories = Category::orderBy('name')->get(['id', 'name', 'slug', 'color']);
        $popularTags = Tag::withCount('posts')->orderByDesc('posts_count')->take(12)->get(['id', 'name', 'slug']);

        return Inertia::render('Welcome', [
            'canLogin' => \Illuminate\Support\Facades\Route::has('login'),
            'canRegister' => \Illuminate\Support\Facades\Route::has('register'),
            'posts' => $posts,
            'tab' => $tab,
            'heroPost' => $heroPost,
            'sidebarData' => $sidebarData,
            'categories' => $categories,
            'popularTags' => $popularTags,
            'guestPreferences' => $guestPrefs,
            'selectedCategory' => $selectedCategorySlug,
            'seo' => $this->seoService->forHome(),
        ]);
    }

    /**
     * Handle "Not interested" card action.
     */
    public function dismiss(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        $user = Auth::user();

        if ($user) {
            $this->feedService->recordDismissal($user, $post);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Story hidden. We will show less content like this.',
                ]);
            }

            return back()->with('success', 'Story hidden.');
        }

        // For guests, store dismissal in guest cookie
        $guestPrefs = [];
        $guestPrefsCookie = $request->cookie('inkwell_guest_prefs');
        if ($guestPrefsCookie) {
            $decoded = json_decode($guestPrefsCookie, true);
            if (is_array($decoded)) {
                $guestPrefs = $decoded;
            }
        }

        $guestPrefs['dismissed'] = array_unique(array_merge($guestPrefs['dismissed'] ?? [], [$post->id]));
        if ($post->category_id) {
            $guestPrefs['dismissed_categories'] = array_unique(array_merge($guestPrefs['dismissed_categories'] ?? [], [$post->category_id]));
        }

        $cookie = cookie('inkwell_guest_prefs', json_encode($guestPrefs), 60 * 24 * 30);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Story hidden.',
            ])->withCookie($cookie);
        }

        return back()->withCookie($cookie);
    }

    /**
     * Undo "Not interested" dismissal.
     */
    public function undoDismiss(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        $user = Auth::user();

        if ($user) {
            $this->feedService->undoDismissal($user, $post);

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Restored story.']);
            }

            return back()->with('success', 'Restored story.');
        }

        // Guest undo
        $guestPrefs = [];
        $guestPrefsCookie = $request->cookie('inkwell_guest_prefs');
        if ($guestPrefsCookie) {
            $decoded = json_decode($guestPrefsCookie, true);
            if (is_array($decoded)) {
                $guestPrefs = $decoded;
            }
        }

        $guestPrefs['dismissed'] = array_values(array_diff($guestPrefs['dismissed'] ?? [], [$post->id]));
        $cookie = cookie('inkwell_guest_prefs', json_encode($guestPrefs), 60 * 24 * 30);

        if ($request->wantsJson()) {
            return response()->json(['success' => true])->withCookie($cookie);
        }

        return back()->withCookie($cookie);
    }

    /**
     * Admin/Moderator toggle for is_featured flag (Editor's Pick).
     */
    public function toggleFeatured(Request $request, Post $post): RedirectResponse
    {
        $user = Auth::user();

        if (! $user || ! in_array($user->role, [UserRole::ADMIN, UserRole::MODERATOR])) {
            abort(403, 'Unauthorized to toggle editor pick status.');
        }

        $post->update([
            'is_featured' => ! $post->is_featured,
        ]);

        return back()->with('success', $post->is_featured ? 'Marked as Editor’s Pick.' : 'Removed from Editor’s Pick.');
    }
}
