<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Str;

class SeoService
{
    public function __construct(
        protected SettingService $settingService
    ) {}

    /**
     * Build SEO metadata array for a single published post.
     */
    public function forPost(Post $post): array
    {
        $siteName = $this->settingService->get('site_name', config('app.name', 'Inkwell'));
        $title = "{$post->title} — {$siteName}";
        $description = $post->excerpt ?: Str::limit(strip_tags($post->content_html ?? ''), 160);
        $canonical = route('posts.show', [
            'category' => $post->category?->slug ?? 'uncategorized',
            'post' => $post->slug,
        ]);
        if ($post->cover_image_path) {
            if (str_starts_with($post->cover_image_path, 'http://') || str_starts_with($post->cover_image_path, 'https://')) {
                $image = $post->cover_image_path;
            } else {
                $image = url($post->cover_image_path);
            }
        } else {
            $image = asset('images/og-default.png');
        }
        $authorUrl = route('author.show', ['username' => $post->user->username]);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'image' => $image,
            'type' => 'article',
            'site_name' => $siteName,
            'published_time' => $post->published_at?->toIso8601String(),
            'modified_time' => $post->updated_at?->toIso8601String(),
            'author_name' => $post->user->name,
            'author_url' => $authorUrl,
            'section' => $post->category?->name,
            'tags' => $post->tags->pluck('name')->all(),
            'json_ld' => [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $post->title,
                'description' => $description,
                'image' => $image,
                'datePublished' => $post->published_at?->toIso8601String(),
                'dateModified' => $post->updated_at?->toIso8601String(),
                'author' => [
                    '@type' => 'Person',
                    'name' => $post->user->name,
                    'url' => $authorUrl,
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => $siteName,
                    'url' => url('/'),
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => asset('images/logo.png'),
                    ],
                ],
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id' => $canonical,
                ],
            ],
        ];
    }

    /**
     * Build SEO metadata for a category page.
     */
    public function forCategory(Category $category): array
    {
        $siteName = $this->settingService->get('site_name', config('app.name', 'Inkwell'));
        $title = "{$category->name} Stories & Ideas — {$siteName}";
        $description = $category->description ?: "Read in-depth stories, analysis, and ideas filed under {$category->name} on {$siteName}.";
        $canonical = route('categories.show', ['category' => $category->slug]);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'image' => asset('images/og-default.png'),
            'type' => 'website',
            'site_name' => $siteName,
        ];
    }

    /**
     * Build SEO metadata for a tag page.
     */
    public function forTag(Tag $tag): array
    {
        $siteName = $this->settingService->get('site_name', config('app.name', 'Inkwell'));
        $title = "#{$tag->name} Stories — {$siteName}";
        $description = "Articles, reflections, and insights tagged #{$tag->name} on {$siteName}.";
        $canonical = route('tags.show', ['tag' => $tag->slug]);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'image' => asset('images/og-default.png'),
            'type' => 'website',
            'site_name' => $siteName,
        ];
    }

    /**
     * Build SEO metadata for an author profile page.
     */
    public function forAuthor(User $user): array
    {
        $siteName = $this->settingService->get('site_name', config('app.name', 'Inkwell'));
        $title = "{$user->name} (@{$user->username}) — {$siteName}";
        $description = $user->bio ?: "Read articles and essays published by {$user->name} on {$siteName}.";
        $canonical = route('author.show', ['username' => $user->username]);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'image' => $user->avatar_url ?: asset('images/og-default.png'),
            'type' => 'profile',
            'site_name' => $siteName,
        ];
    }

    /**
     * Build SEO metadata for the home page.
     */
    public function forHome(): array
    {
        $siteName = $this->settingService->get('site_name', config('app.name', 'Inkwell'));
        $title = $this->settingService->get('default_meta_title', "{$siteName} — Where ideas take root");
        $description = $this->settingService->get('default_meta_description', 'Read, write, and explore in-depth stories across technology, design, culture, and ideas.');
        $canonical = url('/');

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'image' => asset('images/og-default.png'),
            'type' => 'website',
            'site_name' => $siteName,
            'json_ld' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => url('/'),
                'description' => $description,
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => url('/search?q={search_term_string}'),
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];
    }

    /**
     * Build generic page SEO metadata.
     */
    public function forPage(string $title, ?string $description = null, ?string $canonical = null): array
    {
        $siteName = $this->settingService->get('site_name', config('app.name', 'Inkwell'));
        $fullTitle = "{$title} — {$siteName}";
        $desc = $description ?: $this->settingService->get('default_meta_description', 'Read, write, and share in-depth stories on Inkwell.');

        return [
            'title' => $fullTitle,
            'description' => $desc,
            'canonical' => $canonical ?: url()->current(),
            'image' => asset('images/og-default.png'),
            'type' => 'website',
            'site_name' => $siteName,
        ];
    }
}
