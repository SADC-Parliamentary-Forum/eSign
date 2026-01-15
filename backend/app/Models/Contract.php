<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Contract extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'document_id',
        'reference_number',
        'start_date',
        'end_date',
        'value',
        'currency',
        'counterparty_name',
        'counterparty_email',
        'renewal_terms',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'value' => 'decimal:2',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
