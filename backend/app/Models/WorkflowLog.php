<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class WorkflowLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'document_id',
        'user_id',
        'action',
        'previous_status',
        'new_status',
        'comment',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
