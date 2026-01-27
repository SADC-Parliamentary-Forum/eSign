<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use App\Models\User;
use App\Models\Document;

class SecurityRemediationIteration2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('bot_protection.enabled', false);
    }

    public function test_unprotected_routes_are_gone()
    {
        // These routes were previously accessible without auth. 
        // Now they should be 401 (Middlewared) or 404 (if completely gone from top level)
        // Since they exist inside auth group, they are 401 unauth.

        // We need a document ID to hit the route
        $doc = Document::factory()->create();

        $response = $this->postJson("/api/documents/{$doc->id}/signers", []);
        $response->assertStatus(401);

        $response = $this->getJson("/api/documents/{$doc->id}/fields");
        $response->assertStatus(401);

        $response = $this->postJson("/api/documents/{$doc->id}/send", []);
        $response->assertStatus(401);
    }

    public function test_signature_validation_rejects_large_payload()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Huge payload > 500KB
        $hugeData = 'data:image/png;base64,' . str_repeat('A', 700000);

        $response = $this->postJson('/api/signatures/mine', [
            'type' => 'signature',
            'image_data' => $hugeData
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Signature image too large. Max 500KB.']);
    }

    public function test_signature_validation_rejects_svg()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $svgData = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjxzY3JpcHQ+YWxlcnQoMSk8L3NjcmlwdD48L3N2Zz4='; // <svg>...</svg>

        $response = $this->postJson('/api/signatures/mine', [
            'type' => 'signature',
            'image_data' => $svgData
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Invalid image format. Must be a base64 encoded PNG or JPEG image.']);
    }

    public function test_valid_signature_is_accepted()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Small valid PNG
        $validData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $response = $this->postJson('/api/signatures/mine', [
            'type' => 'signature',
            'image_data' => $validData
        ]);

        $response->assertStatus(201);
    }
}
