<?php

namespace Database\Factories;

use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TemplateFactory extends Factory
{
    protected $model = Template::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph,
            'category' => 'Contract',
            'workflow_type' => 'SEQUENTIAL', // SEQUENTIAL, PARALLEL, MIXED
            'amount_required' => false,
            'is_bulk_enabled' => false,
            'is_field_locked' => false,
            'file_path' => 'templates/dummy.pdf',
            'file_hash' => 'dummyhash',
            'is_public' => false,
            'required_signature_level' => 'SIMPLE',
            'status' => 'DRAFT',
        ];
    }
}
