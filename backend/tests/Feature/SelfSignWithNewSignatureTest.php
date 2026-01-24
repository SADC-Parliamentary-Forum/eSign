<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\DocumentField;
use App\Models\User;
use App\Models\UserSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class SelfSignWithNewSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_self_sign_with_provided_signature_data()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create a self-sign document
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'status' => 'DRAFT',
            'is_self_sign' => true,
        ]);

        // Add signer record for self
        DocumentSigner::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'signing_order' => 1,
        ]);

        // Add signature field
        DocumentField::create([
            'document_id' => $document->id,
            'type' => 'SIGNATURE',
            'page_number' => 1,
            'x' => 100,
            'y' => 100,
            'width' => 150,
            'height' => 50,
            'required' => true,
        ]);

        $signatureData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKma1gAAAABJRU5ErkJggg==';

        $response = $this->postJson("/api/documents/{$document->id}/sign-self", [
            'signature_data' => $signatureData,
            'save_to_profile' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Document signed and completed successfully.');

        // Verify document is completed
        $this->assertEquals('COMPLETED', $document->fresh()->status);

        // Verify signature is saved to profile
        $this->assertDatabaseHas('user_signatures', [
            'user_id' => $user->id,
            'type' => 'signature',
            'is_default' => true,
            'image_data' => $signatureData,
        ]);
    }

    public function test_user_can_use_existing_default_if_no_data_provided()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $signatureData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKma1gAAAABJRU5ErkJggg==';

        // Create default signature
        UserSignature::create([
            'user_id' => $user->id,
            'type' => 'signature',
            'is_default' => true,
            'image_data' => $signatureData,
            'name' => 'Default Signature',
            'method' => 'DRAWN',
        ]);

        // Create a self-sign document
        $document = Document::factory()->create([
            'user_id' => $user->id,
            'status' => 'DRAFT',
            'is_self_sign' => true,
        ]);

        // Add signer record for self
        DocumentSigner::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'signing_order' => 1,
        ]);

        // Add signature field
        DocumentField::create([
            'document_id' => $document->id,
            'type' => 'SIGNATURE',
            'page_number' => 1,
            'x' => 100,
            'y' => 100,
            'width' => 150,
            'height' => 50,
            'required' => true,
        ]);

        $response = $this->postJson("/api/documents/{$document->id}/sign-self");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Document signed and completed successfully.');

        $this->assertEquals('COMPLETED', $document->fresh()->status);
    }
}
