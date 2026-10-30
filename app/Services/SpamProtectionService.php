<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SpamProtectionService
{
    public function __construct(
        protected SettingService $settingService
    ) {}

    /**
     * Check honeypot field. If filled, spam is detected.
     */
    public function checkHoneypot(Request $request, string $field = 'hp_field'): void
    {
        if ($request->filled($field)) {
            throw ValidationException::withMessages([
                $field => 'Spam bot detected.',
            ]);
        }
    }

    /**
     * Check if text contains any forbidden blocked words.
     */
    public function validateBlockedWords(string ...$texts): void
    {
        $rawBlocked = $this->settingService->get('blocked_words', '');
        if (empty(trim((string) $rawBlocked))) {
            return;
        }

        $blockedWords = array_filter(array_map('trim', explode(',', (string) $rawBlocked)));

        foreach ($texts as $text) {
            foreach ($blockedWords as $word) {
                if (empty($word)) {
                    continue;
                }
                // Word boundary case-insensitive match
                if (preg_match('/(?:\b|_)' . preg_quote($word, '/') . '(?:\b|_)/i', $text)) {
                    throw ValidationException::withMessages([
                        'content' => "Your submission contains prohibited content or keywords (\"{$word}\").",
                    ]);
                }
            }
        }
    }

    /**
     * Enforce daily post limit for brand-new accounts (created within 3 days).
     */
    public function checkDailyPostLimit(User $user): void
    {
        // Only applies to regular users created in the last 3 days
        if ($user->role !== \App\Enums\UserRole::USER) {
            return;
        }

        if ($user->created_at && $user->created_at->diffInDays(now()) <= 3) {
            $limit = (int) $this->settingService->get('daily_post_limit_new_users', 3);
            $todayCount = $user->posts()->whereDate('created_at', today())->count();

            if ($todayCount >= $limit) {
                throw ValidationException::withMessages([
                    'title' => "New accounts are limited to publishing {$limit} stories per day to prevent spam.",
                ]);
            }
        }
    }

    /**
     * Enforce link limit on comments for new users.
     */
    public function checkCommentLinkLimit(User $user, string $body): void
    {
        if ($user->role !== \App\Enums\UserRole::USER) {
            return;
        }

        if ($user->created_at && $user->created_at->diffInDays(now()) <= 3) {
            $limit = (int) $this->settingService->get('link_limit_new_users_comments', 1);

            preg_match_all('/https?:\/\/[^\s\)\"\']+/i', $body, $matches);
            $linkCount = count($matches[0] ?? []);

            if ($linkCount > $limit) {
                throw ValidationException::withMessages([
                    'body' => "New accounts are limited to at most {$limit} link(s) per comment to prevent spam.",
                ]);
            }
        }
    }
}
