<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    protected const CACHE_KEY = 'site_settings_all';

    protected array $defaults = [
        'site_name' => 'Inkwell',
        'tagline' => 'Where ideas take root',
        'logo_path' => null,
        'default_meta_title' => 'Inkwell — A home for thoughtful writing',
        'default_meta_description' => 'Read, write, and explore in-depth stories across technology, design, culture, and ideas.',
        'signups_enabled' => true,
        'require_email_verification' => false,
        'comment_moderation_mode' => 'open', // 'open' or 'hold_first_comment'
        'blocked_words' => 'casino,viagra,free-crypto,loan-scam,payday',
        'daily_post_limit_new_users' => 3,
        'link_limit_new_users_comments' => 1,
    ];

    /**
     * Retrieve a setting value or default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return $all[$key] ?? $default ?? ($this->defaults[$key] ?? null);
    }

    /**
     * Store or update a setting.
     */
    public function set(string $key, mixed $value): void
    {
        SiteSetting::updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (is_array($value) ? json_encode($value) : (string) $value)]
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Set multiple settings at once.
     */
    public function setMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Get all settings merged with defaults.
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            $dbSettings = SiteSetting::all()->pluck('value', 'key')->toArray();

            $merged = $this->defaults;
            foreach ($dbSettings as $k => $v) {
                // Cast booleans
                if (in_array($k, ['signups_enabled', 'require_email_verification'], true)) {
                    $merged[$k] = filter_var($v, FILTER_VALIDATE_BOOLEAN);
                } elseif (in_array($k, ['daily_post_limit_new_users', 'link_limit_new_users_comments'], true)) {
                    $merged[$k] = (int) $v;
                } else {
                    $merged[$k] = $v;
                }
            }

            return $merged;
        });
    }
}
