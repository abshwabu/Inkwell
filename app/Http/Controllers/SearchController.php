<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    /**
     * Search published posts publicly.
     */
    public function index(Request $request): Response
    {
        $query = trim((string) $request->input('q', $request->input('search', '')));
        $categorySlug = $request->input('category');
        $tagSlug = $request->input('tag');

        $postsQuery = Post::published()->withRelations();

        if ($query !== '') {
            $postsQuery->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('excerpt', 'like', "%{$query}%")
                    ->orWhere('content_html', 'like', "%{$query}%")
                    ->orWhereHas('user', function ($uq) use ($query) {
                        $uq->where('name', 'like', "%{$query}%")
                            ->orWhere('username', 'like', "%{$query}%");
                    })
                    ->orWhereHas('tags', function ($tq) use ($query) {
                        $tq->where('name', 'like', "%{$query}%");
                    });
            });
        }

        if ($categorySlug) {
            $postsQuery->whereHas('category', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
        }

        if ($tagSlug) {
            $postsQuery->whereHas('tags', function ($q) use ($tagSlug) {
                $q->where('slug', $tagSlug);
            });
        }

        $posts = $postsQuery->latest('published_at')->paginate(12)->withQueryString();

        return Inertia::render('Search/Index', [
            'query' => $query,
            'category' => $categorySlug,
            'tag' => $tagSlug,
            'posts' => $posts,
            'categories' => Category::orderBy('name')->get(['id', 'name', 'slug']),
            'tags' => Tag::orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }
}
