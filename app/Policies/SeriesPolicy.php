<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Series;
use App\Models\User;

class SeriesPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Series $series): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return ! $user->is_banned;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Series $series): bool
    {
        if ($user->is_banned) {
            return false;
        }

        return $user->id === $series->user_id || $user->role === UserRole::ADMIN;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Series $series): bool
    {
        if ($user->is_banned) {
            return false;
        }

        return $user->id === $series->user_id || $user->role === UserRole::ADMIN;
    }

    /**
     * Determine whether the user can reorder parts of the series.
     */
    public function reorder(User $user, Series $series): bool
    {
        if ($user->is_banned) {
            return false;
        }

        return $user->id === $series->user_id || $user->role === UserRole::ADMIN;
    }
}
