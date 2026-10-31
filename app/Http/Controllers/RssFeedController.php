<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\Series;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Http\Response;

class RssFeedController extends Controller
{
    public function __construct(
        protected SettingService $settingService
    ) {}

    /**
     * Main site RSS feed.
     */
    public function index(): Response
    {
        $siteName = $this->settingService->get('site_name', config('app.name', 'Inkwell'));
        $siteDescription = $this->settingService->get('default_meta_description', 'Where ideas take root.');

        $posts = Post::published()
            ->withRelations()
            ->latest('published_at')
            ->take(50)
            ->get();

        $xml = $this->buildRssFeed(
            title: $siteName,
            description: $siteDescription,
            link: url('/'),
            feedUrl: url('/feed'),
            posts: $posts
        );

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=1800',
        ]);
    }

    /**
     * Category-specific RSS feed.
     */
    public function category(Category $category): Response
    {
        $siteName = $this->settingService->get('site_name', config('app.name', 'Inkwell'));
        $title = "{$siteName} — {$category->name}";
        $description = $category->description ?: "Latest stories published under {$category->name} on {$siteName}.";

        $posts = $category->posts()
            ->published()
            ->withRelations()
            ->latest('published_at')
            ->take(50)
            ->get();

        $xml = $this->buildRssFeed(
            title: $title,
            description: $description,
            link: route('categories.show', ['category' => $category->slug]),
            feedUrl: route('feed.category', ['category' => $category->slug]),
            posts: $posts
        );

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=1800',
        ]);
    }

    /**
     * Author-specific RSS feed.
     */
    public function author(string $username): Response
    {
        $author = User::where('username', $username)->firstOrFail();
        $siteName = $this->settingService->get('site_name', config('app.name', 'Inkwell'));
        $title = "{$siteName} — Stories by {$author->name}";
        $description = $author->bio ?: "Latest essays and stories written by {$author->name} on {$siteName}.";

        $posts = $author->posts()
            ->published()
            ->withRelations()
            ->latest('published_at')
            ->take(50)
            ->get();

        $xml = $this->buildRssFeed(
            title: $title,
            description: $description,
            link: route('author.show', ['username' => $author->username]),
            feedUrl: route('feed.author', ['username' => $author->username]),
            posts: $posts
        );

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=1800',
        ]);
    }

    /**
     * Series-specific RSS feed.
     */
    public function series(string $slug): Response
    {
        $series = Series::where('slug', $slug)->firstOrFail();
        $siteName = $this->settingService->get('site_name', config('app.name', 'Inkwell'));
        $title = "{$siteName} — {$series->title}";
        $description = $series->description ?: "Parts published in the series {$series->title} on {$siteName}.";

        $posts = $series->posts()
            ->published()
            ->withRelations()
            ->orderBy('series_order', 'asc')
            ->get();

        $xml = $this->buildRssFeed(
            title: $title,
            description: $description,
            link: url("/series/{$series->slug}"),
            feedUrl: url("/series/{$series->slug}/feed"),
            posts: $posts
        );

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=1800',
        ]);
    }

    /**
     * Assemble RSS 2.0 XML with Atom and Content namespaces.
     */
    protected function buildRssFeed(string $title, string $description, string $link, string $feedUrl, $posts): string
    {
        $lastBuildDate = $posts->first()?->published_at?->toRssString() ?? now()->toRssString();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n";
        $xml .= "    <channel>\n";
        $xml .= '        <title><![CDATA[' . $title . "]]></title>\n";
        $xml .= '        <link>' . htmlspecialchars($link, ENT_XML1, 'UTF-8') . "</link>\n";
        $xml .= '        <description><![CDATA[' . $description . "]]></description>\n";
        $xml .= "        <language>en-US</language>\n";
        $xml .= '        <lastBuildDate>' . $lastBuildDate . "</lastBuildDate>\n";
        $xml .= '        <atom:link href="' . htmlspecialchars($feedUrl, ENT_XML1, 'UTF-8') . "\" rel=\"self\" type=\"application/rss+xml\" />\n";

        foreach ($posts as $post) {
            $postUrl = route('posts.show', [
                'category' => $post->category?->slug ?? 'uncategorized',
                'post' => $post->slug,
            ]);
            $postDate = $post->published_at?->toRssString() ?? $post->created_at->toRssString();
            $authorName = $post->user?->name ?? 'Inkwell Writer';
            $categoryName = $post->category?->name;

            $xml .= "        <item>\n";
            $xml .= '            <title><![CDATA[' . $post->title . "]]></title>\n";
            $xml .= '            <link>' . htmlspecialchars($postUrl, ENT_XML1, 'UTF-8') . "</link>\n";
            $xml .= '            <guid isPermaLink="true">' . htmlspecialchars($postUrl, ENT_XML1, 'UTF-8') . "</guid>\n";
            $xml .= '            <pubDate>' . $postDate . "</pubDate>\n";
            $xml .= '            <dc:creator><![CDATA[' . $authorName . "]]></dc:creator>\n";

            if ($categoryName) {
                $xml .= '            <category><![CDATA[' . $categoryName . "]]></category>\n";
            }

            if (! empty($post->excerpt)) {
                $xml .= '            <description><![CDATA[' . $post->excerpt . "]]></description>\n";
            }

            if (! empty($post->content_html)) {
                $xml .= "            <content:encoded><![CDATA[\n" . $post->content_html . "\n            ]]></content:encoded>\n";
            }

            $xml .= "        </item>\n";
        }

        $xml .= "    </channel>\n";
        $xml .= '</rss>';

        return $xml;
    }
}
