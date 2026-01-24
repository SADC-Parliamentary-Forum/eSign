<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(4),
            'status' => 'DRAFT',
            'file_path' => 'documents/dummy.pdf',
            'file_hash' => 'dummyhash',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'signature_level' => 'SIMPLE',
            'sequential_signing' => false,
            'is_self_sign' => false,
        ];
    }
}
