<?php

namespace App\Services;


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
            \OwenIt\Auditing\Models\Audit::create([
                'user_id' => Auth::id(),
                'user_type' => Auth::check() ? get_class(Auth::user()) : null,
                'event' => $event,
                'auditable_type' => $resourceType,
                'auditable_id' => $resourceId,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'new_values' => $details,
                'url' => Request::fullUrl(),
            ]);
        } catch (\Exception $e) {
            // Audit logging should not break the app flow, but should be reported
            // Log::error('Audit Log Failed: ' . $e->getMessage());
        }
    }
}
