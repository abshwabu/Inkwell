<?php

namespace App\Http\Controllers;

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Notifications\CommentReplyNotification;
use App\Notifications\NewCommentNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    /**
     * Store a comment on a post (Auth required).
     */
    public function store(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Comment::class);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        $parentComment = null;
        $actualParentId = null;

        if (! empty($validated['parent_id'])) {
            $parent = Comment::withTrashed()->findOrFail($validated['parent_id']);
            $parentComment = $parent;
            // Threaded depth: max 2 levels deep. If parent is already a reply, attach to its parent.
            $actualParentId = $parent->parent_id ?: $parent->id;
        }

        $comment = DB::transaction(function () use ($post, $actualParentId, $validated) {
            $created = Comment::create([
                'post_id' => $post->id,
                'user_id' => Auth::id(),
                'parent_id' => $actualParentId,
                'body' => $validated['body'],
                'status' => CommentStatus::VISIBLE,
            ]);

            Post::where('id', $post->id)->increment('comments_count');

            return $created;
        });

        // Trigger Notifications
        $user = Auth::user();

        if ($actualParentId && $parentComment) {
            // Reply to existing comment
            if ($parentComment->user_id && $parentComment->user_id !== $user->id) {
                $parentComment->user->notify(new CommentReplyNotification($post, $parentComment, $comment, $user));
            }

            // Also notify post author if they are not the replier and not the parent comment author
            if ($post->user_id && $post->user_id !== $user->id && $post->user_id !== $parentComment->user_id) {
                $post->user->notify(new NewCommentNotification($post, $comment, $user));
            }
        } else {
            // Root comment
            if ($post->user_id && $post->user_id !== $user->id) {
                $post->user->notify(new NewCommentNotification($post, $comment, $user));
            }
        }

        app(\App\Services\FeedService::class)->invalidateUserFeedCache($user);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'comment' => $comment->load('user'),
                'comments_count' => $post->fresh()->comments_count,
            ]);
        }

        return back();
    }

    /**
     * Update a comment (Auth required).
     */
    public function update(Request $request, Comment $comment): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $comment);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $comment->update([
            'body' => $validated['body'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'comment' => $comment->fresh('user'),
            ]);
        }

        return back();
    }

    /**
     * Delete a comment (Auth required).
     */
    public function destroy(Request $request, Comment $comment): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $comment);

        $post = $comment->post;

        DB::transaction(function () use ($comment, $post) {
            $comment->delete();

            if ($post) {
                Post::where('id', $post->id)
                    ->where('comments_count', '>', 0)
                    ->decrement('comments_count');
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'comments_count' => $post ? $post->fresh()->comments_count : 0,
            ]);
        }

        return back();
    }
}
