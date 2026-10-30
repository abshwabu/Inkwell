<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    /**
     * Submit a report against a post or comment.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'reportable_type' => ['required', 'string', 'in:post,comment,' . Post::class . ',' . Comment::class],
            'reportable_id' => ['required', 'integer'],
            'reason' => ['required', 'string', Rule::in(array_keys(Report::REASONS))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $type = match ($validated['reportable_type']) {
            'post', Post::class => Post::class,
            'comment', Comment::class => Comment::class,
        };

        // Ensure target exists
        $model = $type::findOrFail($validated['reportable_id']);

        $report = Report::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'reportable_type' => $type,
                'reportable_id' => $model->id,
            ],
            [
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Thank you for reporting this. Our moderation team will review it.',
                'report' => $report,
            ]);
        }

        return back()->with('success', 'Thank you for reporting this. Our moderation team will review it.');
    }
}
