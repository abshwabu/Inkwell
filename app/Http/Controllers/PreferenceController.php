<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Tag;
use App\Services\FeedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PreferenceController extends Controller
{
    public function __construct(
        protected FeedService $feedService
    ) {}

    /**
     * Show onboarding screen to choose >= 3 categories and optional tags.
     */
    public function onboarding(): Response|RedirectResponse
    {
        $user = Auth::user();

        return Inertia::render('Onboarding/Index', [
            'categories' => Category::orderBy('name')->get(['id', 'name', 'slug', 'description', 'color']),
            'tags' => Tag::withCount('posts')->orderByDesc('posts_count')->take(30)->get(['id', 'name', 'slug']),
            'userCategories' => $user->preferredCategories()->pluck('categories.id')->toArray(),
            'userTags' => $user->preferredTags()->pluck('tags.id')->toArray(),
        ]);
    }

    /**
     * Save onboarding selections.
     */
    public function storeOnboarding(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // If user skipped
        if ($request->boolean('skip')) {
            $request->session()->put('skipped_onboarding', true);
            return redirect()->route('home')->with('success', 'Welcome to Inkwell!');
        }

        $validated = $request->validate([
            'categories' => ['required', 'array', 'min:3'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ], [
            'categories.min' => 'Please select at least 3 topics to personalize your feed.',
        ]);

        $user->preferredCategories()->sync($validated['categories']);

        if (! empty($validated['tags'])) {
            $user->preferredTags()->sync($validated['tags']);
        }

        $this->feedService->invalidateUserFeedCache($user);

        return redirect()->route('home')->with('success', 'Your feed has been personalized!');
    }

    /**
     * Display the reading preferences settings page.
     */
    public function edit(): Response
    {
        $user = Auth::user();

        return Inertia::render('Profile/Preferences', [
            'categories' => Category::orderBy('name')->get(['id', 'name', 'slug', 'description', 'color']),
            'tags' => Tag::orderBy('name')->get(['id', 'name', 'slug']),
            'preferredCategoryIds' => $user->preferredCategories()->pluck('categories.id')->toArray(),
            'preferredTagIds' => $user->preferredTags()->pluck('tags.id')->toArray(),
        ]);
    }

    /**
     * Update user reading preferences.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ]);

        $user->preferredCategories()->sync($validated['categories'] ?? []);
        $user->preferredTags()->sync($validated['tags'] ?? []);

        $this->feedService->invalidateUserFeedCache($user);

        return back()->with('success', 'Reading preferences updated.');
    }
}
