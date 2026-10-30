<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    /**
     * Display a published post publicly.
     */
    public function show(Post $post): Response
    {
        Gate::authorize('view', $post);

        $post->load([
            'user',
            'category',
            'tags',
            'comments' => fn ($query) => $query->visible()->whereNull('parent_id')->with(['user', 'replies.user'])->latest(),
        ]);

        $isLiked = Auth::check() ? Auth::user()->likedPosts()->where('post_id', $post->id)->exists() : false;
        $isBookmarked = Auth::check() ? Auth::user()->bookmarkedPosts()->where('post_id', $post->id)->exists() : false;

        return Inertia::render('Posts/Show', [
            'post' => $post,
            'isLiked' => $isLiked,
            'isBookmarked' => $isBookmarked,
            'canEdit' => Auth::check() && Auth::user()->can('update', $post),
            'canDelete' => Auth::check() && Auth::user()->can('delete', $post),
            'canPublish' => Auth::check() && Auth::user()->can('publish', $post),
        ]);
    }

    /**
     * Show form to write a new post (Auth required).
     */
    public function create(): Response
    {
        Gate::authorize('create', Post::class);

        return Inertia::render('Posts/Create', [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'tags' => Tag::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created post (Auth required).
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Post::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'excerpt' => 'nullable|string|max:500',
            'cover_image_path' => 'nullable|string|max:1000',
            'content_json' => 'nullable|array',
            'content_html' => 'required|string',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
            'status' => 'nullable|string|in:draft,published',
        ]);

        $status = ($validated['status'] ?? 'draft') === 'published' ? PostStatus::PUBLISHED : PostStatus::DRAFT;
        $publishedAt = $status === PostStatus::PUBLISHED ? now() : null;

        $post = Post::create([
            'user_id' => Auth::id(),
            'category_id' => $validated['category_id'] ?? null,
            'title' => $validated['title'],
            'excerpt' => $validated['excerpt'] ?? Str::limit(strip_tags($validated['content_html']), 160),
            'cover_image_path' => $validated['cover_image_path'] ?? null,
            'content_json' => $validated['content_json'] ?? null,
            'content_html' => $validated['content_html'],
            'status' => $status,
            'published_at' => $publishedAt,
        ]);

        if (! empty($validated['tags'])) {
            $post->tags()->sync($validated['tags']);
        }

        return redirect()->route('posts.show', $post->slug);
    }

    /**
     * Show post edit form (Auth required).
     */
    public function edit(Post $post): Response
    {
        Gate::authorize('update', $post);

        $post->load('tags');

        return Inertia::render('Posts/Edit', [
            'post' => $post,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'tags' => Tag::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Update post (Auth required).
     */
    public function update(Request $request, Post $post): RedirectResponse
    {
        Gate::authorize('update', $post);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'excerpt' => 'nullable|string|max:500',
            'cover_image_path' => 'nullable|string|max:1000',
            'content_json' => 'nullable|array',
            'content_html' => 'required|string',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
        ]);

        $post->update([
            'title' => $validated['title'],
            'category_id' => $validated['category_id'] ?? null,
            'excerpt' => $validated['excerpt'] ?? Str::limit(strip_tags($validated['content_html']), 160),
            'cover_image_path' => $validated['cover_image_path'] ?? null,
            'content_json' => $validated['content_json'] ?? null,
            'content_html' => $validated['content_html'],
        ]);

        if (isset($validated['tags'])) {
            $post->tags()->sync($validated['tags']);
        }

        return redirect()->route('posts.show', $post->slug);
    }

    /**
     * Toggle published state (Auth required, author or admin).
     */
    public function publish(Post $post): RedirectResponse
    {
        Gate::authorize('publish', $post);

        if ($post->status === PostStatus::PUBLISHED) {
            $post->update([
                'status' => PostStatus::DRAFT,
                'published_at' => null,
            ]);
        } else {
            $post->update([
                'status' => PostStatus::PUBLISHED,
                'published_at' => now(),
            ]);
        }

        return back();
    }

    /**
     * Soft delete post (Auth required).
     */
    public function destroy(Post $post): RedirectResponse
    {
        Gate::authorize('delete', $post);

        $post->delete();

        return redirect()->route('dashboard');
    }
}
