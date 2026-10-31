<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Inkwell') }}</title>

        @php
            $seo = $page['props']['seo'] ?? null;
            $siteName = config('app.name', 'Inkwell');
            $metaTitle = $seo['title'] ?? ($siteName . ' — Where ideas take root');
            $metaDesc = $seo['description'] ?? 'Inkwell is an open blogging platform where thoughtful writing finds its audience.';
            $canonicalUrl = $seo['canonical'] ?? url()->current();
            $metaImage = $seo['image'] ?? asset('images/og-default.png');
            $ogType = $seo['type'] ?? 'website';
        @endphp

        <!-- SEO Meta Tags -->
        <meta name="description" content="{{ $metaDesc }}">
        <link rel="canonical" href="{{ $canonicalUrl }}">
        <link rel="alternate" type="application/rss+xml" title="{{ $siteName }} RSS Feed" href="{{ url('/feed') }}">

        <!-- Open Graph / Facebook -->
        <meta property="og:site_name" content="{{ $seo['site_name'] ?? $siteName }}">
        <meta property="og:type" content="{{ $ogType }}">
        <meta property="og:title" content="{{ $metaTitle }}">
        <meta property="og:description" content="{{ $metaDesc }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:image" content="{{ $metaImage }}">
        @if (!empty($seo['published_time']))
            <meta property="article:published_time" content="{{ $seo['published_time'] }}">
        @endif
        @if (!empty($seo['author_name']))
            <meta property="article:author" content="{{ $seo['author_name'] }}">
        @endif

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $metaTitle }}">
        <meta name="twitter:description" content="{{ $metaDesc }}">
        <meta name="twitter:image" content="{{ $metaImage }}">

        <!-- Structured Data JSON-LD -->
        @if (!empty($seo['json_ld']))
            <script type="application/ld+json">
                {!! json_encode($seo['json_ld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
            </script>
        @endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Prevent flash of unstyled theme (class strategy persisted in localStorage) -->
        <script>
            try {
                const storedTheme = localStorage.getItem('theme');
                const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (storedTheme === 'dark' || (!storedTheme && systemPrefersDark)) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            } catch (_) {}
        </script>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100 min-h-screen">
        @inertia
    </body>
</html>
