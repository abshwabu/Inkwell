<?php

namespace App\Http\Controllers;

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    /**
     * Store a comment on a post (Auth required).
     */
    public function store(Request $request, Post $post): RedirectResponse
    {
        Gate::authorize('create', Comment::class);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
            'parent_id' => 'nullable|exists:comments,id',
        ]);

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => Auth::id(),
            'parent_id' => $validated['parent_id'] ?? null,
            'body' => $validated['body'],
            'status' => CommentStatus::VISIBLE,
        ]);

        $post->increment('comments_count');

        return back();
    }

    /**
     * Update a comment (Auth required).
     */
    public function update(Request $request, Comment $comment): RedirectResponse
    {
        Gate::authorize('update', $comment);

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $comment->update([
            'body' => $validated['body'],
        ]);

        return back();
    }

    /**
     * Delete a comment (Auth required).
     */
    public function destroy(Comment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $post = $comment->post;
        $comment->delete();

        if ($post && $post->comments_count > 0) {
            $post->decrement('comments_count');
        }

        return back();
    }
}
