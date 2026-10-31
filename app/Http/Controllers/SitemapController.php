<?php

namespace App\Http\Controllers;

use App\Services\SitemapService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Render the sitemap.xml.
     */
    public function index(SitemapService $sitemapService): Response
    {
        $filePath = public_path('sitemap.xml');

        if (file_exists($filePath) && ! app()->environment('testing') && ! request()->has('fresh')) {
            $content = file_get_contents($filePath);
        } else {
            $content = $sitemapService->generateXml();
        }

        return response($content, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
