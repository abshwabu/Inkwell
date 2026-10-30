<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List all categories with counts and sorting order.
     */
    public function index(): Response
    {
        $categories = Category::withCount('posts')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Create a new category.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:100|unique:categories,slug',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:50',
        ]);

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? '#6366f1',
            'sort_order' => (int) Category::max('sort_order') + 1,
        ]);

        $this->auditService->log('category.create', $category, ['name' => $category->name]);

        return back()->with('success', "Category \"{$category->name}\" created.");
    }

    /**
     * Update an existing category.
     */
    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('categories', 'slug')->ignore($category->id)],
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:50',
        ]);

        $category->update([
            'name' => $validated['name'],
            'slug' => ! empty($validated['slug']) ? Str::slug($validated['slug']) : $category->slug,
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? $category->color,
        ]);

        $this->auditService->log('category.update', $category, ['name' => $category->name]);

        return back()->with('success', "Category \"{$category->name}\" updated.");
    }

    /**
     * Reorder categories by sort_order.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:categories,id',
        ]);

        foreach ($validated['order'] as $index => $id) {
            Category::where('id', $id)->update(['sort_order' => $index]);
        }

        $this->auditService->log('category.reorder', null, ['order' => $validated['order']]);

        return back()->with('success', 'Categories reordered.');
    }

    /**
     * Delete category (prevent deletion if posts exist without reassignment).
     */
    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $postsCount = $category->posts()->count();

        if ($postsCount > 0) {
            $reassignToId = $request->input('reassign_to_id');

            if (empty($reassignToId)) {
                return back()->withErrors([
                    'category' => "Category \"{$category->name}\" has {$postsCount} stories. You must select another category to reassign them to before deleting.",
                ]);
            }

            $targetCategory = Category::where('id', '!=', $category->id)->findOrFail($reassignToId);

            // Reassign posts
            Post::where('category_id', $category->id)->update([
                'category_id' => $targetCategory->id,
            ]);

            $this->auditService->log('category.reassign', $category, [
                'target_category_id' => $targetCategory->id,
                'target_category_name' => $targetCategory->name,
                'reassigned_posts' => $postsCount,
            ]);
        }

        $name = $category->name;
        $category->delete();

        $this->auditService->log('category.delete', null, [
            'deleted_category_name' => $name,
            'reassigned_posts_count' => $postsCount,
        ]);

        return back()->with('success', "Category \"{$name}\" deleted.");
    }
}
