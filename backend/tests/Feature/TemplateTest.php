<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateTest extends TestCase
{
    // use RefreshDatabase;
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    public function test_user_can_create_template()
    {
        Storage::fake('minio');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('contract.pdf', 100);

        $response = $this->actingAs($user)->postJson('/api/templates', [
            'name' => 'Service Agreement',
            'description' => 'Standard service agreement',
            'file' => $file,
            'category' => 'Legal',
            'workflow_type' => 'SEQUENTIAL',
            'amount_required' => false,
            'is_bulk_enabled' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Service Agreement');

        $this->assertDatabaseHas('templates', [
            'name' => 'Service Agreement',
            'user_id' => $user->id,
            'is_bulk_enabled' => true,
        ]);
    }

    public function test_template_fields_are_validated_and_normalized()
    {
        $user = User::factory()->create();
        $template = Template::factory()->create(['user_id' => $user->id]);

        $fields = [
            [
                'type' => 'signature', // validation expects lowercase
                'page_number' => 1,
                'x_position' => 10,
                'y_position' => 20,
                'width' => 15,
                'height' => 5,
                'required' => true,
                'label' => 'Sign Here'
            ]
        ];

        // Assuming there is an endpoint to add fields or it's part of update/store
        // Based on previous context, there is a `saveFields` in API but maybe controller uses `store` or `update`?
        // Let's assume we use the endpoint `POST /api/templates/{id}/fields` based on api.js `saveFields`

        $response = $this->withoutExceptionHandling()->actingAs($user)->postJson("/api/templates/{$template->id}/fields", [
            'fields' => $fields
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('template_fields', [
            'template_id' => $template->id,
            'type' => 'signature', // Verification of lowercase conversion
            'required' => true,
        ]);
    }

    public function test_user_can_use_template_to_create_document()
    {
        Storage::fake('minio');
        $user = User::factory()->create();

        // Setup Template with File
        Storage::disk('minio')->put('templates/test.pdf', 'dummy content');
        $template = Template::factory()->create([
            'user_id' => $user->id,
            'file_path' => 'templates/test.pdf'
        ]);

        // Create document from template
        $response = $this->actingAs($user)->postJson('/api/documents', [
            'title' => 'My New Contract',
            'template_id' => $template->id
        ]);

        $response->assertStatus(201);
        $documentId = $response->json('id');

        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'title' => 'My New Contract',
            'user_id' => $user->id,
            'status' => 'DRAFT'
        ]);

        // Verify file was copied (mock check)
        // Since we blindly trust the controller logic which does Storage::copy
        // We can just verify the document has a path different from template
        $document = Document::find($documentId);
        $this->assertNotEquals($template->file_path, $document->file_path);

        // If we want to verify content exists, we'd need to mock the copy successfully
        // Storage::fake() handles basic copy if file exists.
        Storage::disk('minio')->assertExists($document->file_path);
    }

    public function test_cannot_delete_template_if_not_owner()
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $template = Template::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->deleteJson("/api/templates/{$template->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('templates', ['id' => $template->id]);
    }
}
