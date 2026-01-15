<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TemplateField extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'template_id',
        'type',
        'signer_role',
        'page_number',
        'x_position',
        'y_position',
        'width',
        'height',
        'required',
        'label',
    ];

    protected $casts = [
        'required' => 'boolean',
        'page_number' => 'integer',
        'x_position' => 'float',
        'y_position' => 'float',
        'width' => 'float',
        'height' => 'float',
    ];

    /**
     * Get the template this field belongs to.
     */
    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * Check if this field requires a signature.
     */
    public function isSignatureField(): bool
    {
        return in_array($this->type, ['signature', 'initials']);
    }
}
