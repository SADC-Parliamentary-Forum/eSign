<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_signer_id',
        'verification_type',
        'status',
        'verification_token',
        'verification_code',
        'ip_address',
        'device_fingerprint',
        'geolocation',
        'attempts',
        'expires_at',
        'verified_at',
        'metadata',
    ];

    protected $casts = [
        'device_fingerprint' => 'array',
        'geolocation' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * Get the document signer.
     */
    public function documentSigner(): BelongsTo
    {
        return $this->belongsTo(DocumentSigner::class);
    }

    /**
     * Check if verification is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if verification is verified.
     */
    public function isVerified(): bool
    {
        return $this->status === 'VERIFIED';
    }

    /**
     * Check if max attempts reached.
     */
    public function maxAttemptsReached(): bool
    {
        return $this->attempts >= 3;
    }

    /**
     * Increment attempts.
     */
    public function incrementAttempts(): void
    {
        $this->increment('attempts');

        if ($this->maxAttemptsReached()) {
            $this->update(['status' => 'FAILED']);
        }
    }

    /**
     * Mark as verified.
     */
    public function markAsVerified(): void
    {
        $this->update([
            'status' => 'VERIFIED',
            'verified_at' => now(),
        ]);
    }
}
