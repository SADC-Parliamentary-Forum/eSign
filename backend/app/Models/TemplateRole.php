<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateRole extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'template_id',
        'organizational_role_id',
        'role', // Legacy field, kept for compatibility
        'action',
        'is_required',
        'required', // Legacy field
        'signing_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'required' => 'boolean',
        'signing_order' => 'integer',
    ];

    /**
     * Get the template this role belongs to.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * Get the organizational role.
     */
    public function organizationalRole(): BelongsTo
    {
        return $this->belongsTo(OrganizationalRole::class);
    }
}
