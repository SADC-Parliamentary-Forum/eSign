<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TemplateFieldMapping extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'template_id',
        'role',
        'page',
        'x',
        'y',
        'width',
        'height',
    ];

    protected $casts = [
        'page' => 'integer',
        'x' => 'decimal:4',
        'y' => 'decimal:4',
        'width' => 'decimal:4',
        'height' => 'decimal:4',
    ];

    /**
     * Get the template this field mapping belongs to.
     */
    public function template()
    {
        return $this->belongsTo(Template::class);
    }
}
