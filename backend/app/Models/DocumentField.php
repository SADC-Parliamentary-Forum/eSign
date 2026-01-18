<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentField extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory, \Illuminate\Database\Eloquent\Concerns\HasUuids;

    protected $fillable = [
        'document_id',
        'document_signer_id',
        'type',
        'page_number',
        'x',
        'y',
        'width',
        'height',
        'signer_email',
        'signer_role',
        'required',
        'label',
        'validation_rules',
        'text_value',
        'signature_id',
        'signed_at',
    ];

    protected $casts = [
        'x' => 'float',
        'y' => 'float',
        'width' => 'float',
        'height' => 'float',
        'page_number' => 'integer',
        'required' => 'boolean',
        'validation_rules' => 'array',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function signer()
    {
        return $this->belongsTo(DocumentSigner::class, 'document_signer_id');
    }

    public function signature()
    {
        return $this->belongsTo(Signature::class);
    }
}
