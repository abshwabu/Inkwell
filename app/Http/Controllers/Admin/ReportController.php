<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display reports and moderation queue.
     */
    public function index(Request $request): Response
    {
        $status = $request->input('status', 'pending');

        $query = Report::with(['user', 'resolver'])
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $reports = $query->paginate(20)->withQueryString();

        // Eager load polymorphic relations with their authors
        $reports->getCollection()->transform(function ($report) {
            if ($report->reportable_type === Post::class || $report->reportable_type === 'post') {
                $report->load(['reportable' => fn ($q) => $q->withTrashed()->with('user')]);
            } elseif ($report->reportable_type === Comment::class || $report->reportable_type === 'comment') {
                $report->load(['reportable' => fn ($q) => $q->withTrashed()->with(['user', 'post'])]);
            }
            return $report;
        });

        return Inertia::render('Admin/Reports/Index', [
            'reports' => $reports,
            'currentStatus' => $status,
            'counts' => [
                'pending' => Report::where('status', 'pending')->count(),
                'reviewed' => Report::where('status', 'reviewed')->count(),
                'dismissed' => Report::where('status', 'dismissed')->count(),
            ],
        ]);
    }

    /**
     * Dismiss a report without action.
     */
    public function dismiss(Report $report): RedirectResponse
    {
        $report->update([
            'status' => 'dismissed',
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        $this->auditService->log('report.dismiss', $report, [
            'reason' => $report->reason,
            'target_type' => $report->reportable_type,
            'target_id' => $report->reportable_id,
        ]);

        return back()->with('success', 'Report dismissed.');
    }

    /**
     * Hide or remove the reported content.
     */
    public function hideContent(Request $request, Report $report): RedirectResponse
    {
        $target = $report->reportable;

        if ($target instanceof Comment) {
            $target->update(['status' => CommentStatus::HIDDEN->value]);
            if ($target->post && $target->post->comments_count > 0) {
                $target->post->decrement('comments_count');
            }
        } elseif ($target instanceof Post) {
            $target->update([
                'status' => PostStatus::DRAFT->value,
                'published_at' => null,
            ]);
        }

        $report->update([
            'status' => 'reviewed',
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        $this->auditService->log('content.hide', $target, [
            'report_id' => $report->id,
            'action_note' => $request->input('notes', 'Content hidden by moderator'),
        ]);

        return back()->with('success', 'Reported content has been hidden.');
    }

    /**
     * Warn the author of the reported content.
     */
    public function warnUser(Request $request, Report $report): RedirectResponse
    {
        $validated = $request->validate([
            'warning_reason' => 'required|string|max:500',
        ]);

        $author = $report->reportable?->user;

        $report->update([
            'status' => 'reviewed',
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        $this->auditService->log('user.warn', $author, [
            'report_id' => $report->id,
            'warning_reason' => $validated['warning_reason'],
        ]);

        return back()->with('success', 'Warning logged against user.');
    }

    /**
     * Ban the author of the reported content.
     */
    public function banUser(Request $request, Report $report): RedirectResponse
    {
        $validated = $request->validate([
            'ban_reason' => 'required|string|max:500',
        ]);

        $author = $report->reportable?->user;

        if ($author) {
            if ($author->id === Auth::id()) {
                return back()->withErrors(['ban' => 'You cannot ban yourself.']);
            }

            $author->update([
                'is_banned' => true,
                'ban_reason' => $validated['ban_reason'],
            ]);
        }

        $report->update([
            'status' => 'reviewed',
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        $this->auditService->log('user.ban', $author, [
            'report_id' => $report->id,
            'ban_reason' => $validated['ban_reason'],
        ]);

        return back()->with('success', 'User has been banned.');
    }
}
