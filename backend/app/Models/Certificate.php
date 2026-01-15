<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_signer_id',
        'certificate_type',
        'serial_number',
        'issuer',
        'subject',
        'public_key',
        'private_key',
        'valid_from',
        'valid_to',
        'revoked_at',
        'certificate_pem',
        'thumbprint',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = [
        'private_key', // Never expose private key
    ];

    /**
     * Get the document signer.
     */
    public function documentSigner(): BelongsTo
    {
        return $this->belongsTo(DocumentSigner::class);
    }

    /**
     * Check if certificate is valid.
     */
    public function isValid(): bool
    {
        $now = now();
        return !$this->revoked_at
            && $this->valid_from <= $now
            && $this->valid_to >= $now;
    }

    /**
     * Check if certificate is expired.
     */
    public function isExpired(): bool
    {
        return $this->valid_to < now();
    }

    /**
     * Check if certificate is revoked.
     */
    public function isRevoked(): bool
    {
        return !is_null($this->revoked_at);
    }

    /**
     * Revoke the certificate.
     */
    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    /**
     * Get certificate fingerprint.
     */
    public function getFingerprint(): string
    {
        return $this->thumbprint ?? hash('sha256', $this->certificate_pem);
    }
}
