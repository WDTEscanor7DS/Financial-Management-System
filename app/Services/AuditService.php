<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Section 45/56: audit records are created server-side only, from inside
 * the service layer methods that perform the actual mutation -- never from
 * a client-supplied "log this" request, and never left to a model observer
 * guessing at intent from a generic "updated" event (which tends to
 * produce noisy, half-meaningful entries). Each service explicitly calls
 * AuditService::log() with a human-readable action/description once its
 * database transaction has succeeded.
 */
class AuditService
{
    public static function log(
        string $action,
        string $module,
        ?string $recordId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $status = 'Success',
    ): AuditLog {
        $user = Auth::user();
        $request = request();

        return AuditLog::create([
            'user_id' => $user?->id,
            'role' => $user?->role?->name ?? 'System',
            'action' => $action,
            'module' => $module,
            'record_type' => null,
            'record_id' => $recordId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request ? substr((string) $request->userAgent(), 0, 255) : null,
            'description' => $description ?? ($action.' on '.$module),
            'status' => $status,
        ]);
    }
}
