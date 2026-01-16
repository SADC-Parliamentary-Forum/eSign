<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Delegation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'delegate_user_id',
        'starts_at',
        'ends_at',
        'is_active',
        'reason',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * User who delegated (The Boss).
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * User receiving authority (The Delegate).
     */
    public function delegate()
    {
        return $this->belongsTo(User::class, 'delegate_user_id');
    }

    /**
     * Scope for active delegations at current time.
     */
    public function scopeActive($query)
    {
        $now = now();
        return $query->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            });
    }
}
