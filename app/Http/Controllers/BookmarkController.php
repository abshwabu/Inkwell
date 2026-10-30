<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Models\Post;
use App\Services\FeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BookmarkController extends Controller
{
    /**
     * Display the authenticated user's reading list.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $bookmarkedPosts = $user->bookmarkedPosts()
            ->published()
            ->withRelations()
            ->orderByPivot('created_at', 'desc')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Bookmarks/Index', [
            'posts' => $bookmarkedPosts,
        ]);
    }

    /**
     * Toggle bookmark on a post.
     */
    public function toggle(Request $request, Post $post, FeedService $feedService): RedirectResponse|JsonResponse
    {
        $user = Auth::user();

        $existing = Bookmark::where('user_id', $user->id)
            ->where('post_id', $post->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $bookmarked = false;
        } else {
            Bookmark::create([
                'user_id' => $user->id,
                'post_id' => $post->id,
            ]);
            $bookmarked = true;
        }

        $feedService->invalidateUserFeedCache($user);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'bookmarked' => $bookmarked,
            ]);
        }

        return back();
    }
}
