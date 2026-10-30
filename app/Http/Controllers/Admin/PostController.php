<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List all posts with filtering and search.
     */
    public function index(Request $request): Response
    {
        $query = Post::withTrashed()->with(['user', 'category', 'tags'])->latest();

        if ($request->filled('q')) {
            $search = '%' . trim($request->q) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('slug', 'like', $search)
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', $search)->orWhere('username', 'like', $search));
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'trashed') {
                $query->onlyTrashed();
            } else {
                $query->where('status', $request->status)->whereNull('deleted_at');
            }
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        if ($request->has('featured')) {
            $query->where('is_featured', filter_var($request->featured, FILTER_VALIDATE_BOOLEAN));
        }

        $posts = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Posts/Index', [
            'posts' => $posts,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['q', 'status', 'category', 'featured']),
        ]);
    }

    /**
     * Toggle featured status (Editor's pick).
     */
    public function feature(Post $post): RedirectResponse
    {
        $post->is_featured = ! $post->is_featured;
        $post->save();

        $this->auditService->log(
            $post->is_featured ? 'post.feature' : 'post.unfeature',
            $post,
            ['is_featured' => $post->is_featured, 'title' => $post->title]
        );

        return back()->with('success', $post->is_featured ? 'Story marked as editor\'s pick.' : 'Removed from editor\'s picks.');
    }

    /**
     * Unpublish a published post back to draft.
     */
    public function unpublish(Post $post): RedirectResponse
    {
        $post->update([
            'status' => PostStatus::DRAFT,
            'published_at' => null,
        ]);

        $this->auditService->log('post.unpublish', $post, ['title' => $post->title]);

        return back()->with('success', 'Story has been unpublished.');
    }

    /**
     * Soft delete post.
     */
    public function destroy(Post $post): RedirectResponse
    {
        $title = $post->title;
        $post->delete();

        $this->auditService->log('post.delete', $post, ['title' => $title]);

        return back()->with('success', 'Story has been moved to trash.');
    }

    /**
     * Restore a soft-deleted post.
     */
    public function restore(int $id): RedirectResponse
    {
        $post = Post::onlyTrashed()->findOrFail($id);
        $post->restore();

        $this->auditService->log('post.restore', $post, ['title' => $post->title]);

        return back()->with('success', 'Story has been restored.');
    }
}
