<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class SitemapService
{
    /**
     * Generate the complete XML sitemap content.
     */
    public function generateXml(): string
    {
        $urls = [];

        // 1. Core static pages
        $urls[] = [
            'loc' => url('/'),
            'lastmod' => Carbon::now()->toIso8601String(),
            'changefreq' => 'daily',
            'priority' => '1.0',
        ];

        $urls[] = [
            'loc' => url('/search'),
            'lastmod' => Carbon::now()->toIso8601String(),
            'changefreq' => 'weekly',
            'priority' => '0.6',
        ];

        $urls[] = [
            'loc' => url('/archive'),
            'lastmod' => Carbon::now()->toIso8601String(),
            'changefreq' => 'weekly',
            'priority' => '0.6',
        ];

        // 2. Categories
        $categories = Category::orderBy('name')->get();
        foreach ($categories as $category) {
            $urls[] = [
                'loc' => route('categories.show', ['category' => $category->slug]),
                'lastmod' => $category->updated_at?->toIso8601String() ?? Carbon::now()->toIso8601String(),
                'changefreq' => 'daily',
                'priority' => '0.8',
            ];
        }

        // 3. Tags that have at least one published post
        $tags = Tag::whereHas('posts', fn ($q) => $q->published())->get();
        foreach ($tags as $tag) {
            $urls[] = [
                'loc' => route('tags.show', ['tag' => $tag->slug]),
                'lastmod' => $tag->updated_at?->toIso8601String() ?? Carbon::now()->toIso8601String(),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ];
        }

        // 4. Authors with at least one published post
        $authors = User::whereHas('posts', fn ($q) => $q->published())->get();
        foreach ($authors as $author) {
            $urls[] = [
                'loc' => route('author.show', ['username' => $author->username]),
                'lastmod' => $author->updated_at?->toIso8601String() ?? Carbon::now()->toIso8601String(),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
        }

        // 5. Published posts
        $posts = Post::published()
            ->with('category')
            ->orderByDesc('published_at')
            ->get(['id', 'title', 'slug', 'category_id', 'published_at', 'updated_at']);

        foreach ($posts as $post) {
            $categorySlug = $post->category?->slug ?? 'uncategorized';
            $urls[] = [
                'loc' => route('posts.show', ['category' => $categorySlug, 'post' => $post->slug]),
                'lastmod' => ($post->updated_at ?? $post->published_at ?? Carbon::now())->toIso8601String(),
                'changefreq' => 'weekly',
                'priority' => '0.9',
            ];
        }

        // Build XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= "    <url>\n";
            $xml .= '        <loc>' . htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
            $xml .= '        <lastmod>' . htmlspecialchars($url['lastmod'], ENT_XML1, 'UTF-8') . "</lastmod>\n";
            $xml .= '        <changefreq>' . htmlspecialchars($url['changefreq'], ENT_XML1, 'UTF-8') . "</changefreq>\n";
            $xml .= '        <priority>' . htmlspecialchars($url['priority'], ENT_XML1, 'UTF-8') . "</priority>\n";
            $xml .= "    </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Generate and write the sitemap.xml file directly to public directory.
     */
    public function generateFile(?string $path = null): string
    {
        $filePath = $path ?: public_path('sitemap.xml');
        $xml = $this->generateXml();
        File::put($filePath, $xml);

        return $filePath;
    }
}
