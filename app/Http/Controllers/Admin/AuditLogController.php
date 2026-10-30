<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Display audit logs of all moderation and administrative actions.
     */
    public function index(Request $request): Response
    {
        $query = AuditLog::with(['user', 'auditable'])->latest();

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('q')) {
            $search = '%' . trim($request->q) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', $search)
                    ->orWhere('details', 'like', $search)
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', $search)->orWhere('username', 'like', $search));
            });
        }

        $logs = $query->paginate(30)->withQueryString();

        $distinctActions = AuditLog::select('action')->distinct()->pluck('action');

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'distinctActions' => $distinctActions,
            'filters' => $request->only(['action', 'user_id', 'q']),
        ]);
    }
}
