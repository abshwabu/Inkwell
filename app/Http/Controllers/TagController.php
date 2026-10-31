<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Services\SeoService;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function __construct(
        protected SeoService $seoService
    ) {}

    /**
     * Display a public tag and its published posts.
     */
    public function show(Tag $tag): Response
    {
        $posts = $tag->posts()
            ->published()
            ->withRelations()
            ->latest('published_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Tags/Show', [
            'tag' => $tag,
            'posts' => $posts,
            'seo' => $this->seoService->forTag($tag),
        ]);
    }
}
