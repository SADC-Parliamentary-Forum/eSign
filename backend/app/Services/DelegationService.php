<?php

namespace App\Services;

use App\Models\Delegation;
use App\Models\User;

class DelegationService
{
    /**
     * Check if a user (or their delegate) has permission to act as $targetUserId.
     * Returns true if $actorId IS $targetUserId OR if $actorId is a DELEGATE for $targetUserId.
     */
    public function canActOnBehalfOf($actorId, $targetUserId): bool
    {
        if ($actorId === $targetUserId) {
            return true;
        }

        return Delegation::where('user_id', $targetUserId)
            ->where('delegate_user_id', $actorId)
            ->active()
            ->exists();
    }

    /**
     * Get active delegations for a user (where they are the delegator).
     */
    public function getMyDelegations($userId)
    {
        return Delegation::where('user_id', $userId)
            ->with('delegate')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get active delegations where the user IS the delegate.
     */
    public function getDelegationsToMe($userId)
    {
        return Delegation::where('delegate_user_id', $userId)
            ->active()
            ->with('user')
            ->get();
    }

    /**
     * Create a delegation.
     */
    public function createDelegation($userId, $data)
    {
        return Delegation::create([
            'user_id' => $userId,
            'delegate_user_id' => $data['delegate_user_id'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'reason' => $data['reason'] ?? null,
            'is_active' => true,
        ]);
    }
}
