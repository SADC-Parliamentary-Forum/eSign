<?php

namespace Tests\Feature\ProductionReadiness;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

/**
 * TEST SUITE 1 — FUNCTIONAL TESTS (CORE APP)
 */
class FunctionalTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * TC-1.1 Core Feature Happy Path
     */
    public function test_core_user_can_login_and_fetch_profile()
    {
        $user = User::factory()->create([
            'password' => bcrypt('StrongPass123!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPass123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token', 'user']);

        // Test authenticated route
        Sanctum::actingAs($user);
        $this->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJson(['id' => $user->id]);
    }

    /**
     * TC-1.2 Invalid Input Handling
     */
    public function test_login_with_invalid_credentials_returns_friendly_error()
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);

        // Check for 422 on Validation
        $responseValidation = $this->postJson('/api/auth/login', [
            'email' => 'not-an-email',
            'password' => '',
        ]);

        $responseValidation->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /**
     * TC-1.3 Permission Enforcement
     */
    public function test_regular_user_cannot_access_admin_routes()
    {
        $user = User::factory()->create(); // Not admin
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/admin/audit-logs');

        $response->assertStatus(403);
    }
}
