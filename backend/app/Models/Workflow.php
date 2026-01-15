<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Workflow extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'document_id',
        'status',
        'type',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Get the document this workflow belongs to.
     */
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get all steps in this workflow.
     */
    public function steps()
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('signing_order');
    }

    /**
     * Get pending steps.
     */
    public function pendingSteps()
    {
        return $this->hasMany(WorkflowStep::class)->where('status', 'PENDING');
    }

    /**
     * Get current steps that can be signed (based on workflow type).
     */
    public function currentSteps()
    {
        if ($this->type === 'SEQUENTIAL') {
            $minOrder = $this->steps()->where('status', 'PENDING')->min('signing_order');
            return $this->steps()->where('signing_order', $minOrder)->where('status', 'PENDING');
        }

        // For PARALLEL and MIXED, all pending steps are current
        return $this->pendingSteps();
    }

    /**
     * Check if workflow is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED' ||
            $this->steps()->where('status', 'PENDING')->count() === 0;
    }

    /**
     * Check if workflow is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'CANCELLED';
    }

    /**
     * Check if any step has been declined.
     */
    public function hasDeclinedSteps(): bool
    {
        return $this->steps()->where('status', 'DECLINED')->exists();
    }

    /**
     * Mark workflow as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);
    }

    /**
     * Cancel the workflow.
     */
    public function cancel(string $reason): void
    {
        $this->update([
            'status' => 'CANCELLED',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }
}
