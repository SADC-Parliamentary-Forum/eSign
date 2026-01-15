<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowLog;
use Illuminate\Support\Facades\Notification;
use App\Notifications\DocumentPendingNotification;

class WorkflowService
{
    /**
     * Advance the workflow based on current state and rules
     */
    public function advance(Document $document, User $actor, $action = 'approved')
    {
        // 1. Log Action
        WorkflowLog::create([
            'document_id' => $document->id,
            'user_id' => $actor->id,
            'action' => $action,
            'previous_status' => $document->status,
            'new_status' => 'processing', // Temporary
        ]);

        // 2. Determine Next Step
        $nextRoleName = $this->determineNextApproverRole($document);

        if ($nextRoleName) {
            // Document is pending next approver
            // Update document metadata/status to reflect pending role
            // Since our status is simple (pending), we might need accurate "pending_role" field
            // For now, we update status to 'pending' and notify valid users.

            $document->update(['status' => 'pending']);

            // Notify users with that role
            $role = Role::where('name', $nextRoleName)->first();
            if ($role) {
                // Logic to find users (e.g. all Finance Managers)
                // In production might assign to specific user load balancing or round robin.
                // For now, notify all in role.
                $this->notifyRole($role, $document);
            }

            return 'pending_' . $nextRoleName;
        } else {
            // No more approvers -> Finalize
            $document->update(['status' => 'signed']);
            return 'completed';
        }
    }

    /**
     * Logic from Spec 12.1 Approval Thresholds
     */
    public function determineNextApproverRole(Document $document)
    {
        // This relies on knowing WHO just signed/approved to find NEXT.
        // For simple MVP implementation, we can look at existing Signatures vs Requirements.

        $value = $document->metadata['value'] ?? 0;
        $type = $document->metadata['type'] ?? 'invoice'; // invoice or contract

        // Check if Finance Manager has signed
        $hasFMSigned = $this->hasRoleSigned($document, 'finance_manager');
        $hasSGSigned = $this->hasRoleSigned($document, 'secretary_general');
        $hasExCoSigned = $this->hasRoleSigned($document, 'exco');

        // Invoice Logic
        if ($type === 'invoice') {
            if (!$hasFMSigned)
                return 'finance_manager';

            if ($value > 5000 && !$hasSGSigned)
                return 'secretary_general';

            if ($value > 20000 && !$hasExCoSigned)
                return 'exco';
        }

        // Contract Logic
        if ($type === 'contract') {
            if (!$hasSGSigned)
                return 'secretary_general';

            if ($value > 50000 && !$hasExCoSigned)
                return 'exco';
        }

        return null; // No more steps
    }

    protected function hasRoleSigned(Document $document, $roleName)
    {
        return $document->signatures()
            ->whereHas('user.role', function ($q) use ($roleName) {
                $q->where('name', $roleName);
            })
            ->exists();
    }

    protected function notifyRole(Role $role, Document $document)
    {
        $users = $role->users;
        Notification::send($users, new DocumentPendingNotification($document));
    }
}
