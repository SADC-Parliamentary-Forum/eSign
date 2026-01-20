<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'template_id',
        'workflow_id',
        'title',
        'document_type',
        'amount',
        'file_path',
        'original_file_path',
        'file_signed',
        'file_hash',
        'status',
        'sequential_signing',
        'current_signing_order',
        'expires_at',
        'sent_at',
        'completed_at',
        'voided_at',
        'voided_by',
        'void_reason',
        'mime_type',
        'size',
        'metadata',
        'jurisdiction',
        'retention_period_days',
        'is_legal_hold',
        'legal_hold_reason',
        'archived_at',
        'is_self_sign',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sequential_signing' => 'boolean',
        'current_signing_order' => 'integer',
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'voided_at' => 'datetime',
        'archived_at' => 'datetime',
        'is_legal_hold' => 'boolean',
        'is_self_sign' => 'boolean',
    ];

    /**
     * Get the user who created the document.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the template this document was created from.
     */
    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * Get all signatures on this document.
     */
    public function signatures()
    {
        return $this->hasMany(Signature::class);
    }

    /**
     * Get workflow logs for this document.
     */
    public function workflowLogs()
    {
        return $this->hasMany(WorkflowLog::class);
    }

    /**
     * Get all signers assigned to this document.
     */
    public function signers()
    {
        return $this->hasMany(DocumentSigner::class)->orderBy('signing_order');
    }

    /**
     * Get signers who are currently able to sign.
     */
    public function currentSigners()
    {
        if ($this->sequential_signing) {
            return $this->signers()->where('signing_order', $this->current_signing_order);
        }
        return $this->signers()->pending();
    }

    /**
     * Get fields for this document.
     */
    public function fields()
    {
        return $this->hasMany(DocumentField::class);
    }

    /**
     * Check if document is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if all signers have signed.
     */
    public function isFullySigned(): bool
    {
        return $this->signers()->pending()->count() === 0
            && $this->signers()->signed()->count() > 0;
    }

    /**
     * Advance to next signing order.
     */
    public function advanceSigningOrder(): void
    {
        $nextOrder = $this->signers()
            ->where('signing_order', '>', $this->current_signing_order)
            ->min('signing_order');

        if ($nextOrder) {
            $this->update(['current_signing_order' => $nextOrder]);
        }
    }

    /**
     * Mark document as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'COMPLETED',
            'completed_at' => now(),
        ]);
    }

    /**
     * Scope to get documents pending user's signature.
     */
    public function scopePendingSignatureFrom($query, $userId)
    {
        return $query->whereHas('signers', function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->whereIn('status', ['pending', 'notified', 'viewed']);
        })->whereIn('status', ['sent', 'in_progress']);
    }

    /**
     * Get the workflow for this document.
     */
    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Get user who voided this document.
     */
    public function voidedByUser()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * Check if document is voided.
     */
    public function isVoided(): bool
    {
        return $this->status === 'VOIDED';
    }

    /**
     * Void the document.
     */
    public function void(User $user, string $reason): void
    {
        $this->update([
            'status' => 'VOIDED',
            'voided_at' => now(),
            'voided_by' => $user->id,
            'void_reason' => $reason,
        ]);

        // Cancel associated workflow
        if ($this->workflow) {
            $this->workflow->cancel('Document voided: ' . $reason);
        }
    }

    /**
     * Check if document requires financial amount.
     */
    public function requiresAmount(): bool
    {
        return $this->template && $this->template->requiresAmount();
    }

    /**
     * Scope to get documents by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Scope to get financial documents.
     */
    public function scopeFinancial($query)
    {
        return $query->where('document_type', 'FINANCIAL');
    }
}

