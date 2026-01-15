<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UserSignature extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'method',
        'name',
        'image_data',
        'file_url',
        'hash',
        'is_default',
        'is_immutable',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_immutable' => 'boolean',
    ];

    protected $hidden = [
        'image_data', // Don't include in list queries by default
    ];

    /**
     * Get the user that owns the signature.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get only signatures (not initials).
     */
    public function scopeSignatures($query)
    {
        return $query->where('type', 'signature');
    }

    /**
     * Scope to get only initials.
     */
    public function scopeInitials($query)
    {
        return $query->where('type', 'initials');
    }

    /**
     * Set this signature as the default for its type.
     */
    public function setAsDefault(): void
    {
        // Remove default from other signatures of same type
        static::where('user_id', $this->user_id)
            ->where('type', $this->type)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Make signature immutable (after first use).
     */
    public function makeImmutable(): void
    {
        if (!$this->is_immutable) {
            $this->update(['is_immutable' => true]);
        }
    }

    /**
     * Generate and store hash for signature.
     */
    public function generateHash(): void
    {
        if ($this->image_data && !$this->hash) {
            $this->update(['hash' => hash('sha256', $this->image_data)]);
        }
    }

    /**
     * Check if signature can be modified.
     */
    public function canModify(): bool
    {
        return !$this->is_immutable;
    }
}
