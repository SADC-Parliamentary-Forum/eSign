<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Document;
use App\Models\UserSignature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected function createDefaultSignature(User $user)
    {
        return UserSignature::create([
            'user_id' => $user->id,
            'type' => 'signature',
            'image_data' => 'data:image/png;base64,dummy',
            'is_default' => true
        ]);
    }

    protected function createDefaultInitials(User $user)
    {
        return UserSignature::create([
            'user_id' => $user->id,
            'type' => 'initials',
            'image_data' => 'data:image/png;base64,dummyinit',
            'is_default' => true
        ]);
    }

    public function test_self_sign_flow_auto_creates_signer()
    {
        Storage::fake('minio');
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $this->createDefaultSignature($user);
        $this->createDefaultInitials($user);

        // Create a fake PDF with proper magic bytes (%PDF-1.4 header)
        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        $file = UploadedFile::fake()->createWithContent('contract.pdf', $pdfContent);

        // 1. Create Document with is_self_sign = true
        $response = $this->actingAs($user)->postJson('/api/documents', [
            'title' => 'Self Sign Contract',
            'file' => $file,
            'is_self_sign' => true
        ]);

        $response->assertStatus(201);
        $documentId = $response->json('id');
        $this->assertNotNull($documentId, 'Document ID should be returned');

        $document = Document::find($documentId);
        $this->assertNotNull($document, 'Document should exist in database');

        // Since Queue is faked, manually set up the file that would have been processed
        $filePath = 'documents/' . $document->id . '/contract.pdf';
        Storage::disk('minio')->put($filePath, $pdfContent);
        $document->update([
            'file_path' => $filePath,
            'file_hash' => hash('sha256', $pdfContent),
        ]);

        // Verify signer was auto-created
        $this->assertDatabaseHas('document_signers', [
            'document_id' => $document->id,
            'email' => $user->email,
        ]);

        // 2. Add signature field (The user would do this via UI)
        // Usually DocumentFieldController::store 
        // But for speed we can use factory or direct DB or controller if convenient
        // Let's use direct DB for now to simulate "User placed field"
        $signer = $document->signers()->first();

        \App\Models\DocumentField::create([
            'document_id' => $document->id,
            'document_signer_id' => $signer->id,
            'type' => 'signature',
            'page_number' => 1,
            'x' => 10,
            'y' => 10,
            'width' => 10,
            'height' => 5,
            'signer_email' => $user->email // important for matching
        ]);

        // 3. Finish Self Sign (Sign and Complete)
        $response = $this->actingAs($user)->withoutExceptionHandling()->postJson("/api/documents/{$document->id}/sign-self");

        $response->assertStatus(200);

        $document->refresh();
        $this->assertEquals('COMPLETED', $document->status); // Or IN_PROGRESS if there are other steps, but only me so COMPLETED
        // If workflow finishes immediately on last signature...
        // Need to check if logic completes document.
    }

    public function test_sequential_signing_enforcement()
    {
        Storage::fake('minio');
        $owner = User::factory()->create();
        $signer1 = User::factory()->create();
        $signer2 = User::factory()->create();

        // Create doc
        $document = Document::factory()->create([
            'user_id' => $owner->id,
            'status' => 'DRAFT',
            'sequential_signing' => true
        ]);

        // Add signers
        $this->actingAs($owner)->postJson("/api/documents/{$document->id}/signers", [
            'signers' => [
                ['email' => $signer1->email, 'name' => 'Signer 1', 'order' => 1],
                ['email' => $signer2->email, 'name' => 'Signer 2', 'order' => 2],
            ],
            'sequential' => true
        ]);

        // Send doc
        $this->actingAs($owner)->postJson("/api/documents/{$document->id}/send", [
            'sequential' => true
        ]);

        $document->refresh();
        $this->assertEquals('IN_PROGRESS', $document->status);
        $this->assertEquals(1, $document->current_signing_order);

        // Signer 2 tries to sign (Should Fail / Be blocked)
        // Note: The "sign" endpoint usually checks order.
        // Assuming there is a standard sign endpoint `POST /api/documents/{id}/sign`

        // We need fields for them to sign
        $signer2DB = $document->signers()->where('email', $signer2->email)->first();
        $field2 = \App\Models\DocumentField::create([
            'document_id' => $document->id,
            'document_signer_id' => $signer2DB->id,
            'type' => 'SIGNATURE',
            'page_number' => 1,
            'x' => 0,
            'y' => 0,
            'width' => 10,
            'height' => 10,
            'signer_email' => $signer2->email
        ]);

        // Let's simulate Signer 2 call
        $response = $this->actingAs($signer2)->postJson("/api/documents/{$document->id}/sign", [
            'fields' => [
                ['field_id' => $field2->id, 'value' => 'dummy_signature_data']
            ]
        ]);

        // Assert failure (403 or specific validation error "Not your turn")
        // Based on logic viewed earlier in BulkSign, checks `current_signing_order`.
        // DocumentController::bulkSign checks it.
        // Does SignatureController check it? Likely yes.
        // Assuming 403 or 422.

        $response->assertStatus(403);
    }
}
