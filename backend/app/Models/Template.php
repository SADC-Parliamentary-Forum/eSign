<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'status',
        'file_path',
        'file_hash',
        'document_fingerprint',
        'workflow_type',
        'version',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'amount_required',
        'required_signature_level',
        'default_retention_days',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'amount_required' => 'boolean',
        'version' => 'integer',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the user who created the template.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the template's signature/field placeholders.
     */
    public function fields()
    {
        return $this->hasMany(TemplateField::class);
    }

    /**
     * Get documents created from this template.
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Scope to get public templates.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope to get user's templates (own + public).
     */
    public function scopeAvailableTo($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhere('is_public', true);
        });
    }

    /**
     * Get template roles.
     */
    public function roles()
    {
        return $this->hasMany(TemplateRole::class);
    }

    /**
     * Get template field mappings.
     */
    public function fieldMappings()
    {
        return $this->hasMany(TemplateFieldMapping::class);
    }

    /**
     * Get template thresholds.
     */
    public function thresholds()
    {
        return $this->hasMany(TemplateThreshold::class)->orderBy('min_amount');
    }

    /**
     * Get user who reviewed this template.
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get user who approved this template.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope to get active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    /**
     * Scope to get templates by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Check if template is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    /**
     * Check if template requires amount.
     */
    public function requiresAmount(): bool
    {
        return $this->amount_required;
    }

    /**
     * Get the applicable threshold for a given amount.
     */
    public function getThresholdForAmount(float $amount): ?TemplateThreshold
    {
        return $this->thresholds()
            ->get()
            ->first(fn($threshold) => $threshold->coversAmount($amount));
    }
}
