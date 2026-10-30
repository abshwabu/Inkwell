<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\NewFollowerNotification;
use App\Services\FeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class FollowController extends Controller
{
    /**
     * Display the list of users the authenticated user is following.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $following = $user->following()
            ->withCount([
                'followers',
                'posts' => fn ($query) => $query->published(),
            ])
            ->orderByPivot('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Following/Index', [
            'following' => $following,
            'stats' => [
                'following_count' => $user->following()->count(),
                'followers_count' => $user->followers()->count(),
            ],
        ]);
    }

    /**
     * Toggle following a user.
     */
    public function toggle(Request $request, User $user, FeedService $feedService): RedirectResponse|JsonResponse
    {
        $follower = Auth::user();

        if ($follower->id === $user->id) {
            abort(400, 'You cannot follow yourself.');
        }

        $changes = $follower->following()->toggle($user->id);
        $isFollowing = in_array($user->id, $changes['attached'], true);

        if ($isFollowing) {
            $user->notify(new NewFollowerNotification($follower));
        }

        $feedService->invalidateUserFeedCache($follower);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_following' => $isFollowing,
                'followers_count' => $user->followers()->count(),
            ]);
        }

        return back();
    }
}
