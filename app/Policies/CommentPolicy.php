<?php

namespace App\Policies;

use App\Enums\CommentStatus;
use App\Enums\UserRole;
use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    /**
     * Determine whether the user can view any comments.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the comment.
     */
    public function view(?User $user, Comment $comment): bool
    {
        if ($comment->status === CommentStatus::VISIBLE) {
            return true;
        }

        if (! $user || $user->is_banned) {
            return false;
        }

        return $user->id === $comment->user_id
            || $user->role === UserRole::ADMIN
            || $user->role === UserRole::MODERATOR;
    }

    /**
     * Determine whether the user can create comments.
     */
    public function create(User $user): bool
    {
        return ! $user->is_banned;
    }

    /**
     * Determine whether the user can update the comment.
     * Only the author of the comment or an admin can edit.
     */
    public function update(User $user, Comment $comment): bool
    {
        if ($user->is_banned) {
            return false;
        }

        return $user->id === $comment->user_id || $user->role === UserRole::ADMIN;
    }

    /**
     * Determine whether the user can delete the comment.
     * Author of comment, author of post, or admin/moderator can delete.
     */
    public function delete(User $user, Comment $comment): bool
    {
        if ($user->is_banned) {
            return false;
        }

        return $user->id === $comment->user_id
            || $user->id === $comment->post->user_id
            || $user->role === UserRole::ADMIN
            || $user->role === UserRole::MODERATOR;
    }
}
