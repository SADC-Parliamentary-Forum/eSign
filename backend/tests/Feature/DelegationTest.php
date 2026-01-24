<?php

namespace Tests\Feature;

use App\Models\Delegation;
use App\Models\Document;
use App\Models\DocumentField;
use App\Models\DocumentSigner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class DelegationTest extends TestCase
{
    // use RefreshDatabase; // Disabled because environment is shared/persistent state usually

    public function test_user_can_create_delegation()
    {
        $delegator = User::factory()->create();
        $delegate = User::factory()->create();

        $response = $this->actingAs($delegator)->postJson('/api/delegations', [
            'delegate_email' => $delegate->email,
            'starts_at' => now()->toIso8601String(),
            'ends_at' => now()->addDays(7)->toIso8601String(),
            'reason' => 'Vacation'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('delegations', [
            'user_id' => $delegator->id,
            'delegate_user_id' => $delegate->id,
            'reason' => 'Vacation'
        ]);
    }

    public function test_user_can_list_delegations()
    {
        $delegator = User::factory()->create();
        $delegate = User::factory()->create();

        Delegation::create([
            'user_id' => $delegator->id,
            'delegate_user_id' => $delegate->id,
            'starts_at' => now(),
            'is_active' => true
        ]);

        $response = $this->actingAs($delegator)->getJson('/api/delegations');

        $response->assertStatus(200)
            ->assertJsonStructure(['my_delegations', 'delegations_to_me']);

        $this->assertCount(1, $response->json('my_delegations'));
    }

    public function test_delegate_can_sign_on_behalf_of_delegator()
    {
        // 1. Setup users
        $delegator = User::factory()->create();
        $delegate = User::factory()->create();

        // 2. Create active delegation
        Delegation::create([
            'user_id' => $delegator->id,
            'delegate_user_id' => $delegate->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true
        ]);

        // 3. Create document assigned to DELEGATOR
        $document = Document::factory()->create();

        $signer = DocumentSigner::create([
            'document_id' => $document->id,
            'user_id' => $delegator->id,
            'email' => $delegator->email,
            'name' => $delegator->name,
            'sign_sequence' => 1
        ]);

        $field = DocumentField::create([
            'document_id' => $document->id,
            'document_signer_id' => $signer->id,
            'type' => 'SIGNATURE',
            'page_number' => 1,
            'x' => 100,
            'y' => 100,
            'width' => 150,
            'height' => 50
        ]);

        // 4. Delegate attempts to sign
        $response = $this->actingAs($delegate)->postJson("/api/documents/{$document->id}/sign", [
            'fields' => [
                [
                    'field_id' => $field->id,
                    'value' => 'data:image/png;base64,signaturedata...'
                ]
            ]
        ]);

        // 5. Assert success
        $response->assertStatus(200); // Or 201/204

        $this->assertDatabaseHas('signatures', [
            'document_id' => $document->id,
            'user_id' => $delegate->id // It is signed by the DELGATE
        ]);
    }
}
