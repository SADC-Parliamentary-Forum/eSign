<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationalRole extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'code',
        'level',
        'description',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'is_active' => 'boolean',
    ];

    public function templateRoles(): HasMany
    {
        return $this->hasMany(TemplateRole::class);
    }

    public function templateFields(): HasMany
    {
        return $this->hasMany(TemplateField::class);
    }
}
