<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Record an audit log entry for a moderator or admin action.
     */
    public function log(string $action, ?Model $target = null, array $details = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id() ?? 1,
            'action' => $action,
            'auditable_type' => $target ? get_class($target) : null,
            'auditable_id' => $target ? $target->getKey() : null,
            'details' => $details,
            'ip_address' => request()->ip(),
        ]);
    }
}
