<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\Post;
use App\Services\FeedService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LikeController extends Controller
{
    /**
     * Toggle like on a post.
     */
    public function toggle(Request $request, Post $post, NotificationService $notificationService, FeedService $feedService): RedirectResponse|JsonResponse
    {
        $user = Auth::user();

        $result = DB::transaction(function () use ($user, $post) {
            $existingLike = Like::where('user_id', $user->id)
                ->where('post_id', $post->id)
                ->first();

            if ($existingLike) {
                $existingLike->delete();
                Post::where('id', $post->id)
                    ->where('likes_count', '>', 0)
                    ->decrement('likes_count');

                return ['liked' => false];
            } else {
                Like::create([
                    'user_id' => $user->id,
                    'post_id' => $post->id,
                ]);
                Post::where('id', $post->id)->increment('likes_count');

                return ['liked' => true];
            }
        });

        if ($result['liked']) {
            $notificationService->notifyPostLiked($post, $user);
        }

        $feedService->invalidateUserFeedCache($user);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'liked' => $result['liked'],
                'likes_count' => $post->fresh()->likes_count,
            ]);
        }

        return back();
    }
}
