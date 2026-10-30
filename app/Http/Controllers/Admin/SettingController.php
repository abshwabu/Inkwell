<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(
        protected SettingService $settingService,
        protected AuditService $auditService
    ) {}

    /**
     * Display the site settings management page.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => $this->settingService->all(),
        ]);
    }

    /**
     * Update site settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => 'required|string|max:100',
            'tagline' => 'nullable|string|max:255',
            'logo_path' => 'nullable|string|max:500',
            'default_meta_title' => 'nullable|string|max:255',
            'default_meta_description' => 'nullable|string|max:500',
            'signups_enabled' => 'required|boolean',
            'require_email_verification' => 'required|boolean',
            'comment_moderation_mode' => 'required|in:open,hold_first_comment',
            'blocked_words' => 'nullable|string|max:2000',
            'daily_post_limit_new_users' => 'required|integer|min:1|max:50',
            'link_limit_new_users_comments' => 'required|integer|min:0|max:20',
        ]);

        $this->settingService->setMany($validated);

        $this->auditService->log('settings.update', null, [
            'settings_updated' => array_keys($validated),
        ]);

        return back()->with('success', 'Site settings updated successfully.');
    }
}
