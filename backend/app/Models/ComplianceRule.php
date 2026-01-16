<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ComplianceRule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'conditions',
        'actions',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Scope to get active rules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('priority', 'desc');
    }

    /**
     * Check if document matches conditions.
     */
    public function matches(Document $document): bool
    {
        $conditions = $this->conditions;

        foreach ($conditions as $attribute => $value) {
            // Handle logical operators if complex (e.g. 'amount_gt')
            // Simple MVP: Exact match or key-based
            if (str_ends_with($attribute, '_gt')) {
                $field = str_replace('_gt', '', $attribute);
                if ($document->$field <= $value)
                    return false;
            } elseif (str_ends_with($attribute, '_lt')) {
                $field = str_replace('_lt', '', $attribute);
                if ($document->$field >= $value)
                    return false;
            } elseif ($attribute === 'jurisdiction') {
                if ($document->jurisdiction !== $value)
                    return false;
            }
            // Add more logic here as needed
        }

        return true;
    }
}
