<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FollowController extends Controller
{
    /**
     * Toggle following a user.
     */
    public function toggle(User $user): RedirectResponse
    {
        $follower = Auth::user();

        if ($follower->id === $user->id) {
            abort(400, 'You cannot follow yourself.');
        }

        $follower->following()->toggle($user->id);

        return back();
    }
}
