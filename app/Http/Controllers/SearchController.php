<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(
        protected SeoService $seoService
    ) {}

    /**
     * Search published stories publicly using SQLite FTS5 table with bm25 ranking and snippets.
     */
    public function index(Request $request): Response
    {
        $rawQuery = trim((string) $request->input('q', $request->input('search', '')));
        $categorySlug = $request->input('category');
        $tagSlug = $request->input('tag');
        $dateFrom = $request->input('date_from', $request->input('from'));
        $dateTo = $request->input('date_to', $request->input('to'));

        $ftsQuery = $this->formatFtsQuery($rawQuery);

        if ($ftsQuery) {
            $postsQuery = Post::query()
                ->select('posts.*', 'fts.rank as fts_rank', 'fts.search_snippet')
                ->joinSub(
                    DB::table('posts_fts')
                        ->selectRaw("post_id, bm25(posts_fts) as rank, snippet(posts_fts, -1, '<mark class=\"bg-amber-200 dark:bg-amber-900/60 font-semibold text-amber-900 dark:text-amber-100 rounded px-1\">', '</mark>', '...', 25) as search_snippet")
                        ->whereRaw('posts_fts MATCH ?', [$ftsQuery]),
                    'fts',
                    'fts.post_id',
                    '=',
                    'posts.id'
                )
                ->published()
                ->withRelations()
                ->orderBy('fts.rank', 'asc');
        } else {
            $postsQuery = Post::published()
                ->withRelations()
                ->latest('published_at');
        }

        // Apply filters
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

        if ($dateFrom) {
            $postsQuery->whereDate('posts.published_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $postsQuery->whereDate('posts.published_at', '<=', $dateTo);
        }

        $posts = $postsQuery->paginate(12)->withQueryString();

        return Inertia::render('Search/Index', [
            'query' => $rawQuery,
            'category' => $categorySlug,
            'tag' => $tagSlug,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'posts' => $posts,
            'categories' => Category::orderBy('name')->get(['id', 'name', 'slug']),
            'tags' => Tag::orderBy('name')->get(['id', 'name', 'slug']),
            'seo' => $this->seoService->forPage($rawQuery ? "Search: {$rawQuery}" : 'Search Stories'),
        ]);
    }

    /**
     * Safely format raw user search terms into SQLite FTS5 MATCH expression.
     */
    protected function formatFtsQuery(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Extract quoted phrases or whitespace-delimited tokens
        preg_match_all('/"([^"]+)"|(\S+)/u', $raw, $matches);
        $tokens = [];

        foreach ($matches[0] as $match) {
            // Remove punctuation that has special FTS5 meaning (: * ^ ( ) { } etc.)
            $cleaned = trim(preg_replace('/[^\p{L}\p{N}_\-]/u', ' ', $match));
            if ($cleaned !== '') {
                $tokens[] = '"' . $cleaned . '"*';
            }
        }

        return ! empty($tokens) ? implode(' ', $tokens) : null;
    }
}
