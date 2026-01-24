<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BulkBatch extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'template_id',
        'user_id',
        'status',
        'total_count',
        'processed_count',
        'success_count',
        'error_count',
        'source_file_path',
        'errors'
    ];

    protected $casts = [
        'errors' => 'array'
    ];

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
