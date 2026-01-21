<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

class DocumentSigner extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'document_id',
        'user_id',
        'email',
        'name',
        'signing_order',
        'status',
        'access_token',
        'notified_at',
        'viewed_at',
        'signed_at',
        'declined_at',
        'decline_reason',
        'ip_address',
        'user_agent',
        'verified_at',
        'verification_method',
        'verification_data',
    ];

    protected $casts = [
        'signing_order' => 'integer',
        'notified_at' => 'datetime',
        'viewed_at' => 'datetime',
        'signed_at' => 'datetime',
        'declined_at' => 'datetime',
        'verified_at' => 'datetime',
        'verification_data' => 'array',
    ];

    protected $hidden = [
        'access_token', // Don't expose token in API responses
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($signer) {
            if (empty($signer->access_token)) {
                $signer->access_token = Str::random(64);
            }
        });
    }

    /**
     * Get the document this signer is assigned to.
     */
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the user account if signer has one.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get signatures made by this signer on the document.
     */
    public function signatures()
    {
        return $this->hasMany(Signature::class, 'user_id', 'user_id')
            ->where('document_id', $this->document_id);
    }

    /**
     * Get identity verifications for this signer.
     */
    public function identityVerifications()
    {
        return $this->hasMany(IdentityVerification::class);
    }

    /**
     * Get the certificate for this signer.
     */
    public function certificate()
    {
        return $this->hasOne(Certificate::class);
    }

    /**
     * Check if this signer can currently sign the document.
     */
    public function canSign(): bool
    {
        // Already signed or declined
        if (in_array($this->status, ['signed', 'declined'])) {
            return false;
        }

        $document = $this->document;

        // Document not in signable state
        if (!in_array(strtoupper($document->status), ['SENT', 'IN_PROGRESS'])) {
            return false;
        }

        // Check expiration
        if ($document->expires_at && $document->expires_at->isPast()) {
            return false;
        }

        // If not sequential, anyone can sign
        if (!$document->sequential_signing) {
            return true;
        }

        // For sequential signing, check if it's this signer's turn
        return $document->current_signing_order === $this->signing_order;
    }

    /**
     * Mark the signer as having viewed the document.
     */
    public function markAsViewed(): void
    {
        if ($this->status === 'pending' || $this->status === 'notified') {
            $this->update([
                'status' => 'viewed',
                'viewed_at' => now(),
            ]);
        }
    }

    /**
     * Mark the signer as having signed.
     */
    public function markAsSigned(?string $ipAddress = null, ?string $userAgent = null): void
    {
        $this->update([
            'status' => 'signed',
            'signed_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Mark the signer as having declined.
     */
    public function markAsDeclined(?string $reason = null): void
    {
        $this->update([
            'status' => 'declined',
            'declined_at' => now(),
            'decline_reason' => $reason,
        ]);
    }

    /**
     * Scope to get pending signers.
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'notified', 'viewed']);
    }

    /**
     * Scope to get signed signers.
     */
    public function scopeSigned($query)
    {
        return $query->where('status', 'signed');
    }

    /**
     * Generate signing URL for this signer.
     */
    public function getSigningUrl(): string
    {
        return config('app.frontend_url') . '/sign/' . $this->access_token;
    }
}
