<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SignatureField extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'document_id',
        'assigned_role_id',
        'assigned_user_id',
        'type',
        'page_number',
        'x_position',
        'y_position',
        'width',
        'height',
        'required'
    ];
}
