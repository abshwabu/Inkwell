<?php

namespace App\Policies;

use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Determine whether the user can view any posts.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the post.
     */
    public function view(?User $user, Post $post): bool
    {
        if ($post->status === PostStatus::PUBLISHED && $post->published_at && $post->published_at <= now()) {
            return true;
        }

        if (! $user || $user->is_banned) {
            return false;
        }

        return $user->id === $post->user_id
            || $user->role === UserRole::ADMIN
            || $user->role === UserRole::MODERATOR;
    }

    /**
     * Determine whether the user can create posts.
     */
    public function create(User $user): bool
    {
        return ! $user->is_banned;
    }

    /**
     * Determine whether the user can update the post.
     * Author or admin/moderator can edit.
     */
    public function update(User $user, Post $post): bool
    {
        if ($user->is_banned) {
            return false;
        }

        return $user->id === $post->user_id
            || $user->role === UserRole::ADMIN
            || $user->role === UserRole::MODERATOR;
    }

    /**
     * Determine whether the user can delete the post.
     * Author or admin/moderator can delete.
     */
    public function delete(User $user, Post $post): bool
    {
        if ($user->is_banned) {
            return false;
        }

        return $user->id === $post->user_id
            || $user->role === UserRole::ADMIN
            || $user->role === UserRole::MODERATOR;
    }

    /**
     * Determine whether the user can publish or unpublish the post.
     * Only author or admin can publish/unpublish own or posts.
     */
    public function publish(User $user, Post $post): bool
    {
        if ($user->is_banned) {
            return false;
        }

        return $user->id === $post->user_id || $user->role === UserRole::ADMIN;
    }

    /**
     * Determine whether the user can restore the post.
     */
    public function restore(User $user, Post $post): bool
    {
        if ($user->is_banned) {
            return false;
        }

        return $user->id === $post->user_id || $user->role === UserRole::ADMIN;
    }

    /**
     * Determine whether the user can permanently delete the post.
     */
    public function forceDelete(User $user, Post $post): bool
    {
        if ($user->is_banned) {
            return false;
        }

        return $user->role === UserRole::ADMIN;
    }
}
