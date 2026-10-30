<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List all tags with post counts.
     */
    public function index(Request $request): Response
    {
        $query = Tag::withCount('posts')->orderBy('name');

        if ($request->filled('q')) {
            $search = '%' . trim($request->q) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)->orWhere('slug', 'like', $search);
            });
        }

        $tags = $query->paginate(30)->withQueryString();

        return Inertia::render('Admin/Tags/Index', [
            'tags' => $tags,
            'filters' => $request->only('q'),
        ]);
    }

    /**
     * Create a new tag.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'slug' => 'nullable|string|max:50|unique:tags,slug',
        ]);

        $tag = Tag::create([
            'name' => $validated['name'],
            'slug' => ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']),
        ]);

        $this->auditService->log('tag.create', $tag, ['name' => $tag->name]);

        return back()->with('success', "Tag \"#{$tag->name}\" created.");
    }

    /**
     * Update an existing tag.
     */
    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'slug' => ['nullable', 'string', 'max:50', Rule::unique('tags', 'slug')->ignore($tag->id)],
        ]);

        $tag->update([
            'name' => $validated['name'],
            'slug' => ! empty($validated['slug']) ? Str::slug($validated['slug']) : $tag->slug,
        ]);

        $this->auditService->log('tag.update', $tag, ['name' => $tag->name]);

        return back()->with('success', "Tag \"#{$tag->name}\" updated.");
    }

    /**
     * Merge a tag into a target tag.
     */
    public function merge(Request $request, Tag $tag): RedirectResponse
    {
        $validated = $request->validate([
            'target_tag_id' => ['required', 'integer', 'exists:tags,id', 'different:tag'],
        ]);

        $targetTag = Tag::where('id', '!=', $tag->id)->findOrFail($validated['target_tag_id']);

        $postIds = $tag->posts()->pluck('posts.id');
        $count = $postIds->count();

        // Attach target tag to all posts having source tag
        if ($count > 0) {
            $targetTag->posts()->syncWithoutDetaching($postIds);
        }

        $sourceName = $tag->name;
        $tag->delete();

        $this->auditService->log('tag.merge', $targetTag, [
            'source_tag_name' => $sourceName,
            'target_tag_name' => $targetTag->name,
            'posts_migrated' => $count,
        ]);

        return back()->with('success', "Tag \"#{$sourceName}\" merged into \"#{$targetTag->name}\" across {$count} stories.");
    }

    /**
     * Delete tag.
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        $name = $tag->name;
        $tag->posts()->detach();
        $tag->delete();

        $this->auditService->log('tag.delete', null, ['deleted_tag_name' => $name]);

        return back()->with('success', "Tag \"#{$name}\" deleted.");
    }
}
