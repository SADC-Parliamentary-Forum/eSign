<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'status',
        'mfa_enabled',
        'phone_number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'mfa_enabled' => 'boolean',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function hasPermission(string $permission): bool
    {
        if (!$this->role) {
            return false;
        }

        // Admin has all permissions
        if ($this->role->name === 'admin') {
            return true;
        }

        return in_array($permission, $this->role->permissions ?? []);
    }

    /**
     * Get all saved signatures for this user.
     */
    public function savedSignatures()
    {
        return $this->hasMany(UserSignature::class);
    }

    /**
     * Get the user's default signature.
     */
    public function defaultSignature()
    {
        return $this->hasOne(UserSignature::class)
            ->where('type', 'signature')
            ->where('is_default', true);
    }

    /**
     * Get the user's default initials.
     */
    public function defaultInitials()
    {
        return $this->hasOne(UserSignature::class)
            ->where('type', 'initials')
            ->where('is_default', true);
    }

    /**
     * Get documents created by this user.
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Get document signer records for this user.
     */
    public function signerAssignments()
    {
        return $this->hasMany(DocumentSigner::class);
    }

    /**
     * Get workflow steps assigned to this user.
     */
    public function workflowSteps()
    {
        return $this->hasMany(WorkflowStep::class, 'assigned_user_id');
    }

    /**
     * Scope to get active users.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    /**
     * Scope to get invited users.
     */
    public function scopeInvited($query)
    {
        return $query->where('status', 'INVITED');
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }
}
