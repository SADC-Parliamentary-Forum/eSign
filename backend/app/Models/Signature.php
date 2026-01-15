<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Signature extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'document_id',
        'user_id',
        'signature_field_id',
        'signature_data',
        'ip_address',
        'user_agent',
        'signed_at',
        'certificate_hash'
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
