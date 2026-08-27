<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Intentionally exposes only index(): there is no store/update/destroy
 * action anywhere in this controller, and none is routed in routes/api.php
 * either. The audit trail is written exclusively by AuditService from
 * inside the other services -- see Section 17/44.
 */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::with('user')
            ->when($request->query('module'), fn ($q, $v) => $q->where('module', $v))
            ->when($request->query('role'), fn ($q, $v) => $q->where('role', $v))
            ->when($request->query('search'), function ($q, $v) {
                $q->where(function ($sub) use ($v) {
                    $sub->where('action', 'like', "%{$v}%")
                        ->orWhere('description', 'like', "%{$v}%")
                        ->orWhere('record_id', 'like', "%{$v}%");
                });
            })
            ->latest('created_at')
            ->paginate(100);

        return response()->json($logs);
    }
}
