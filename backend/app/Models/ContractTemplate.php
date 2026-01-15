<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ContractTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'file_path',
        'placeholders',
    ];

    protected $casts = [
        'placeholders' => 'array',
    ];
}
