<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Services\ContentSanitizerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function __construct(
        protected ContentSanitizerService $sanitizer
    ) {}

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
     * Store a newly created post (or finalize an autosaved draft).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:posts,id',
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

        $sanitizedHtml = $this->sanitizer->sanitize($validated['content_html']);
        if (empty($sanitizedHtml) && ! empty($validated['content_json'])) {
            $sanitizedHtml = $this->sanitizer->sanitize($this->sanitizer->renderFromJSON($validated['content_json']));
        }

        $status = ($validated['status'] ?? 'draft') === 'published' ? PostStatus::PUBLISHED : PostStatus::DRAFT;
        $publishedAt = $status === PostStatus::PUBLISHED ? now() : null;

        // If updating an existing autosaved draft
        if (! empty($validated['id'])) {
            $post = Post::findOrFail($validated['id']);
            Gate::authorize('update', $post);

            $post->update([
                'title' => $validated['title'],
                'category_id' => $validated['category_id'] ?? null,
                'excerpt' => $validated['excerpt'] ?? Str::limit(strip_tags($sanitizedHtml), 160),
                'cover_image_path' => $validated['cover_image_path'] ?? null,
                'content_json' => $validated['content_json'] ?? null,
                'content_html' => $sanitizedHtml,
                'status' => $status,
                'published_at' => $publishedAt ?? $post->published_at,
            ]);

            if (isset($validated['tags'])) {
                $post->tags()->sync($validated['tags']);
            }

            return redirect()->route('posts.show', $post->slug);
        }

        Gate::authorize('create', Post::class);

        $post = Post::create([
            'user_id' => Auth::id(),
            'category_id' => $validated['category_id'] ?? null,
            'title' => $validated['title'],
            'excerpt' => $validated['excerpt'] ?? Str::limit(strip_tags($sanitizedHtml), 160),
            'cover_image_path' => $validated['cover_image_path'] ?? null,
            'content_json' => $validated['content_json'] ?? null,
            'content_html' => $sanitizedHtml,
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
            'status' => 'nullable|string|in:draft,published',
        ]);

        $sanitizedHtml = $this->sanitizer->sanitize($validated['content_html']);
        if (empty($sanitizedHtml) && ! empty($validated['content_json'])) {
            $sanitizedHtml = $this->sanitizer->sanitize($this->sanitizer->renderFromJSON($validated['content_json']));
        }

        $updateData = [
            'title' => $validated['title'],
            'category_id' => $validated['category_id'] ?? null,
            'excerpt' => $validated['excerpt'] ?? Str::limit(strip_tags($sanitizedHtml), 160),
            'cover_image_path' => $validated['cover_image_path'] ?? null,
            'content_json' => $validated['content_json'] ?? null,
            'content_html' => $sanitizedHtml,
        ];

        if (isset($validated['status'])) {
            $newStatus = $validated['status'] === 'published' ? PostStatus::PUBLISHED : PostStatus::DRAFT;
            $updateData['status'] = $newStatus;
            if ($newStatus === PostStatus::PUBLISHED && ! $post->published_at) {
                $updateData['published_at'] = now();
            }
        }

        $post->update($updateData);

        if (isset($validated['tags'])) {
            $post->tags()->sync($validated['tags']);
        }

        return redirect()->route('posts.show', $post->slug);
    }

    /**
     * Autosave post draft every 20 seconds.
     */
    public function autosave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:posts,id',
            'title' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'excerpt' => 'nullable|string|max:500',
            'cover_image_path' => 'nullable|string|max:1000',
            'content_json' => 'nullable|array',
            'content_html' => 'nullable|string',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
        ]);

        $rawHtml = $validated['content_html'] ?? '';
        if (empty($rawHtml) && ! empty($validated['content_json'])) {
            $rawHtml = $this->sanitizer->renderFromJSON($validated['content_json']);
        }
        $sanitizedHtml = $this->sanitizer->sanitize($rawHtml);

        $title = ! empty(trim($validated['title'] ?? '')) ? trim($validated['title']) : 'Untitled Draft';

        if (! empty($validated['id'])) {
            $post = Post::findOrFail($validated['id']);
            Gate::authorize('update', $post);

            $post->update([
                'title' => $title,
                'category_id' => $validated['category_id'] ?? $post->category_id,
                'excerpt' => $validated['excerpt'] ?? $post->excerpt,
                'cover_image_path' => $validated['cover_image_path'] ?? $post->cover_image_path,
                'content_json' => $validated['content_json'] ?? $post->content_json,
                'content_html' => $sanitizedHtml,
            ]);

            if (isset($validated['tags'])) {
                $post->tags()->sync($validated['tags']);
            }
        } else {
            Gate::authorize('create', Post::class);

            $post = Post::create([
                'user_id' => Auth::id(),
                'title' => $title,
                'category_id' => $validated['category_id'] ?? null,
                'excerpt' => $validated['excerpt'] ?? null,
                'cover_image_path' => $validated['cover_image_path'] ?? null,
                'content_json' => $validated['content_json'] ?? null,
                'content_html' => $sanitizedHtml,
                'status' => PostStatus::DRAFT,
                'published_at' => null,
            ]);

            if (! empty($validated['tags'])) {
                $post->tags()->sync($validated['tags']);
            }
        }

        return response()->json([
            'id' => $post->id,
            'slug' => $post->slug,
            'saved_at' => now()->toIso8601String(),
        ]);
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
