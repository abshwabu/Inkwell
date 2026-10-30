<?php

namespace App\Services;

use Illuminate\Http\Request;

class BotDetectorService
{
    /**
     * List of common bot, scraper, and crawler user-agent tokens.
     */
    protected array $botKeywords = [
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'facebookexternalhit',
        'whatsapp', 'twitterbot', 'telegrambot', 'linkedinbot', 'slackbot',
        'discordbot', 'googlebot', 'bingbot', 'yandex', 'duckduckbot',
        'baiduspider', 'curl', 'wget', 'python-requests', 'headlesschrome',
        'lighthouse', 'phantomjs', 'postman', 'archiver', 'feedfetcher',
    ];

    /**
     * Determine whether the incoming request originates from an automated bot or crawler.
     */
    public function isBot(Request $request): bool
    {
        $userAgent = strtolower((string) $request->header('User-Agent'));

        if (empty($userAgent)) {
            return true;
        }

        foreach ($this->botKeywords as $keyword) {
            if (str_contains($userAgent, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
