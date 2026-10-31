<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\SeoService;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(
        protected SeoService $seoService
    ) {}

    /**
     * Display a public category and its published posts.
     */
    public function show(Category $category): Response
    {
        $posts = $category->posts()
            ->published()
            ->withRelations()
            ->latest('published_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Categories/Show', [
            'category' => $category,
            'posts' => $posts,
            'seo' => $this->seoService->forCategory($category),
        ]);
    }
}
