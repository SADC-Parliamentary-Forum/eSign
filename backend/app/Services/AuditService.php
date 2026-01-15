<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log a system event
     */
    public function log(string $event, ?string $resourceType = null, ?string $resourceId = null, array $details = [])
    {
        try {
            AuditLog::create([
                'user_id' => Auth::id(), // Might be null if login fail or system action
                'event' => $event,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'details' => $details,
            ]);
        } catch (\Exception $e) {
            // Audit logging should not break the app flow, but should be reported
            // Log::error('Audit Log Failed: ' . $e->getMessage());
        }
    }
}
