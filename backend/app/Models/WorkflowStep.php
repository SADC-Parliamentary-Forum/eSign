<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class WorkflowStep extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'workflow_id',
        'role',
        'assigned_user_id',
        'signing_order',
        'status',
        'signed_at',
        'declined_at',
        'decline_reason',
    ];

    protected $casts = [
        'signing_order' => 'integer',
        'signed_at' => 'datetime',
        'declined_at' => 'datetime',
    ];

    /**
     * Get the workflow this step belongs to.
     */
    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Get the user assigned to this step.
     */
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Check if this step is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    /**
     * Check if this step is signed.
     */
    public function isSigned(): bool
    {
        return $this->status === 'SIGNED';
    }

    /**
     * Check if this step is declined.
     */
    public function isDeclined(): bool
    {
        return $this->status === 'DECLINED';
    }

    /**
     * Mark step as signed.
     */
    public function markAsSigned(): void
    {
        $this->update([
            'status' => 'SIGNED',
            'signed_at' => now(),
        ]);
    }

    /**
     * Mark step as declined.
     */
    public function decline(string $reason): void
    {
        $this->update([
            'status' => 'DECLINED',
            'declined_at' => now(),
            'decline_reason' => $reason,
        ]);
    }

    /**
     * Scope query to pending steps.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    /**
     * Scope query to signed steps.
     */
    public function scopeSigned($query)
    {
        return $query->where('status', 'SIGNED');
    }

    /**
     * Scope query to declined steps.
     */
    public function scopeDeclined($query)
    {
        return $query->where('status', 'DECLINED');
    }
}
