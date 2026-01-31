<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tracks usage of magic links to prevent reuse attacks.
 * Security: Each magic link can only be used once.
 */
class MagicLinkUse extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'signature_hash',
        'link_type',
        'user_id',
        'ip_address',
        'used_at',
        'expires_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Check if a magic link has already been used.
     */
    public static function hasBeenUsed(string $signatureHash): bool
    {
        return self::where('signature_hash', $signatureHash)->exists();
    }

    /**
     * Mark a magic link as used.
     */
    public static function markAsUsed(
        string $signatureHash,
        string $linkType = 'auth',
        ?string $userId = null,
        ?string $ipAddress = null
    ): self {
        return self::create([
            'signature_hash' => $signatureHash,
            'link_type' => $linkType,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'used_at' => now(),
        ]);
    }

    /**
     * Clean up old entries (for scheduled job).
     */
    public static function cleanupOldEntries(int $daysToKeep = 7): int
    {
        return self::where('used_at', '<', now()->subDays($daysToKeep))->delete();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
