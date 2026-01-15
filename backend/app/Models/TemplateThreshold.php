<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TemplateThreshold extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'template_id',
        'min_amount',
        'max_amount',
        'required_roles',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'required_roles' => 'array',
    ];

    /**
     * Get the template this threshold belongs to.
     */
    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * Check if an amount falls within this threshold.
     */
    public function coversAmount(float $amount): bool
    {
        if ($amount < $this->min_amount) {
            return false;
        }

        if ($this->max_amount !== null && $amount > $this->max_amount) {
            return false;
        }

        return true;
    }
}
