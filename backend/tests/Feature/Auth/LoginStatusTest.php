<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LoginStatusTest extends TestCase
{
    // Use RefreshDatabase to ensure a clean state, but be careful if it wipes manual seed data the user cares about.
    // However, for tests, we usually want clean state.
    // Given the issues with seeders before, I'll be careful.
    // But standard practice is RefreshDatabase. I'll use it for this test file.
    use RefreshDatabase;

    public function test_active_user_can_login()
    {
        $user = User::factory()->create([
            'status' => 'ACTIVE',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_login()
    {
        $user = User::factory()->create([
            'status' => 'INACTIVE',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(403);
        $this->assertGuest();
        $response->assertJson(['message' => 'Your account is suspended or inactive.']);
    }

    public function test_invited_user_cannot_login()
    {
        $user = User::factory()->create([
            'status' => 'INVITED',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(403);
        $this->assertGuest();
        $response->assertJson(['message' => 'Your account is suspended or inactive.']);
    }
}
