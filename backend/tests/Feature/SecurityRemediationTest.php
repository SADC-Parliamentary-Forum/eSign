<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use App\Models\User;

class SecurityRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure bot protection is disabled for these tests to avoid interference
        Config::set('bot_protection.enabled', false);
    }

    public function test_password_reset_is_throttled()
    {
        // Hit the endpoint 6 times. 5 should succeed (validation error), 6th should be 429
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/auth/forgot-password', ['email' => 'test@example.com']);
            // It might fail validation or send email, but shouldn't be 429 yet
            $this->assertNotEquals(429, $response->status());
        }

        $response = $this->postJson('/api/auth/forgot-password', ['email' => 'test@example.com']);
        $response->assertStatus(429);
    }

    public function test_login_sets_session_cookies_for_stateful_request()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'mfa_enabled' => false
        ]);

        // Simulate Stateful Request from localhost:5173
        // 'referer' and 'origin' headers are key for Sanctum to trigger stateful mode
        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ], [
            'Referer' => 'http://localhost:5173',
            'Origin' => 'http://localhost:5173',
        ]);

        $response->assertStatus(200);

        // Assert Set-Cookie header is present (laravel_session and/or XSRF-TOKEN)
        $headers = $response->headers->all();
        $this->assertArrayHasKey('set-cookie', $headers);

        // Check for specific cookies
        $cookies = implode(' ', $response->headers->all('set-cookie'));
        $this->assertStringContainsString('laravel_session', $cookies);
        $this->assertStringContainsString('XSRF-TOKEN', $cookies);
    }

    public function test_mfa_code_generation_runs()
    {
        // This just ensures no crash with random_int changes
        $user = User::factory()->create(['mfa_enabled' => true]);
        $this->actingAs($user);

        $response = $this->postJson('/api/auth/mfa/send');
        $response->assertStatus(200);
    }
}
