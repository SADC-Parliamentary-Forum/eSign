<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);

        $response->assertStatus(200);
        $response->assertJson(['status' => trans(Password::RESET_LINK_SENT)]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_link_is_not_sent_if_email_does_not_exist()
    {
        $response = $this->postJson('/api/auth/forgot-password', ['email' => 'unknown@example.com']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Notification::fake();

        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => trans(Password::PASSWORD_RESET)]);

        $this->assertTrue(auth()->attempt([
            'email' => $user->email,
            'password' => 'new-password',
        ]));
    }
}
