<?php

namespace App\Actions\Admin;

use App\Models\AdminActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Persist a compact audit event for sensitive administration actions. */
class WriteAdminAuditLog
{
    /** Store an audit record for the acting user and optional affected model. */
    public function execute(Request $request, string $action, ?Model $subject = null, array $properties = []): void
    {
        AdminActivityLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties_json' => $properties,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
    }
}
