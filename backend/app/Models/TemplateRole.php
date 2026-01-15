<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TemplateRole extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'template_id',
        'role',
        'action',
        'required',
        'signing_order',
    ];

    protected $casts = [
        'required' => 'boolean',
        'signing_order' => 'integer',
    ];

    /**
     * Get the template this role belongs to.
     */
    public function template()
    {
        return $this->belongsTo(Template::class);
    }
}
