<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheResponseForGuests
{
    /**
     * Cache duration in seconds (5 minutes).
     */
    protected int $ttl = 300;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // In testing, bypass cache unless explicitly requested by test
        if (app()->environment('testing') && ! $request->hasHeader('X-Test-Guest-Cache')) {
            return $next($request);
        }

        // Only cache safe GET / HEAD requests
        if (! $request->isMethodSafe()) {
            return $next($request);
        }

        // Never cache responses for authenticated users
        if ($request->user()) {
            return $next($request);
        }

        // Never cache private / write / internal paths
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $guestCookie = $request->cookie('inkwell_guest_prefs');
        $cookieSuffix = $guestCookie ? '_' . md5(is_array($guestCookie) ? json_encode($guestCookie) : (string) $guestCookie) : '';
        $isInertia = $request->header('X-Inertia') ? 'inertia' : 'html';
        $cacheKey = 'guest_page_cache_' . $isInertia . '_' . md5($request->fullUrl()) . $cookieSuffix;

        // Check cache
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached) && isset($cached['content'], $cached['status'])) {
                $clientEtag = $request->header('If-None-Match');
                if ($clientEtag && trim($clientEtag) === trim($cached['etag'])) {
                    return response('', 304, [
                        'ETag' => $cached['etag'],
                        'Cache-Control' => 'public, max-age=' . $this->ttl,
                        'X-Cache' => 'HIT-304',
                    ]);
                }

                $headers = $cached['headers'] ?? [];
                $headers['X-Cache'] = 'HIT';
                $headers['ETag'] = $cached['etag'];
                $headers['Cache-Control'] = 'public, max-age=' . $this->ttl . ', stale-while-revalidate=60';

                return response($cached['content'], $cached['status'], $headers);
            }
        }

        $response = $next($request);

        // Only cache successful 200 responses
        if ($response->getStatusCode() === 200) {
            $content = $response->getContent();
            $etag = '"' . md5($content) . '"';

            $headers = [];
            foreach ($response->headers->all() as $name => $values) {
                // Don't cache Set-Cookie header for guests
                if (strtolower($name) !== 'set-cookie') {
                    $headers[$name] = $values[0] ?? '';
                }
            }

            Cache::put($cacheKey, [
                'content' => $content,
                'status' => $response->getStatusCode(),
                'headers' => $headers,
                'etag' => $etag,
            ], $this->ttl);

            $response->headers->set('ETag', $etag);
            $response->headers->set('Cache-Control', 'public, max-age=' . $this->ttl . ', stale-while-revalidate=60');
            $response->headers->set('Vary', 'X-Inertia, Accept');
            $response->headers->set('X-Cache', 'MISS');
        }

        return $response;
    }

    /**
     * Determine if caching should be bypassed for this route.
     */
    protected function shouldSkip(Request $request): bool
    {
        $path = $request->path();

        $skipPrefixes = [
            'admin',
            'write',
            'dashboard',
            'settings',
            'login',
            'register',
            'password',
            'email',
            'health',
            'up',
            'api',
            'sanctum',
            'notifications',
            'bookmarks',
            'following',
            'onboarding',
        ];

        foreach ($skipPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
