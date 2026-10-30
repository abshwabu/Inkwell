<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthorController extends Controller
{
    /**
     * Display the public author profile page.
     */
    public function show(string $username): Response
    {
        $author = User::where('username', $username)->firstOrFail();

        $author->loadCount([
            'followers',
            'following',
            'posts' => fn ($query) => $query->published(),
        ]);

        $isFollowing = Auth::check()
            ? Auth::user()->following()->where('followed_id', $author->id)->exists()
            : false;

        $posts = $author->posts()
            ->published()
            ->withRelations()
            ->latest('published_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Author/Show', [
            'author' => [
                'id' => $author->id,
                'name' => $author->name,
                'username' => $author->username,
                'bio' => $author->bio,
                'avatar_url' => $author->avatar_url,
                'role' => $author->role->value,
                'followers_count' => $author->followers_count,
                'following_count' => $author->following_count,
                'published_posts_count' => $author->posts_count,
                'created_at' => $author->created_at->format('M Y'),
            ],
            'posts' => $posts,
            'isFollowing' => $isFollowing,
            'isSelf' => Auth::id() === $author->id,
        ]);
    }
}
