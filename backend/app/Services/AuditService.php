<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log a security or business event.
     *
     * @param mixed $user The user model or ID
     * @param string $event The event name (e.g., 'login', 'signed', 'viewed')
     * @param string $resourceType The resource type (e.g., 'document', 'user')
     * @param string|int|null $resourceId The resource ID
     * @param array $details Additional details
     * @return AuditLog
     */
    public function log($user, string $event, string $resourceType, $resourceId = null, array $details = [])
    {
        $userId = $user instanceof \App\Models\User ? $user->id : $user;

        return AuditLog::create([
            'user_id' => $userId,
            'event' => $event,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}
