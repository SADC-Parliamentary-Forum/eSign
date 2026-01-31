<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_document()
    {
        Storage::fake('minio');
        Storage::fake('local');
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();

        // Create a fake PDF with proper magic bytes (%PDF-1.4 header)
        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        $file = UploadedFile::fake()->createWithContent('contract.pdf', $pdfContent);

        $response = $this->actingAs($user)->postJson('/api/documents', [
            'file' => $file,
            'title' => 'Test Contract',
            'department' => 'Legal',
            'value' => 5000
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'Test Contract');

        // Document status may be DRAFT, IN_PROGRESS, or PROCESSING depending on async processing
        $this->assertDatabaseHas('documents', [
            'title' => 'Test Contract',
            'user_id' => $user->id,
        ]);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ProcessDocumentUpload::class);
    }

    public function test_user_cannot_view_others_document()
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $document = Document::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->getJson("/api/documents/{$document->id}");

        $response->assertStatus(403);
    }

    public function test_owner_can_sign_document()
    {
        // Mock Storage for "File exists" check in service if needed, 
        // but for signing we mainly check DB state.

        $user = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'status' => 'IN_PROGRESS'
        ]);

        \App\Models\DocumentSigner::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'signing_order' => 1
        ]);

        $field = \App\Models\DocumentField::create([
            'document_id' => $document->id,
            'type' => 'SIGNATURE',
            'page_number' => 1,
            'x' => 100,
            'y' => 100,
            'width' => 150,
            'height' => 50,
            'signer_email' => $user->email // Self sign
        ]);

        $signatureData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKwAEQAAAABJRU5ErkJggg==';

        $response = $this->actingAs($user)->postJson("/api/documents/{$document->id}/sign", [
            'fields' => [
                [
                    'field_id' => $field->id,
                    'value' => $signatureData
                ]
            ]
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => 'COMPLETED'
        ]);

        $this->assertDatabaseHas('signatures', [
            'document_id' => $document->id,
            'user_id' => $user->id
        ]);

        // Verify Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'resource_id' => $document->id,
            'event' => 'signed'
        ]);
    }
}
