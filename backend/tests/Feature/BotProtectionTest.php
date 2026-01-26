<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;
use App\Services\BotProtection\BotProtectionService;

class BotProtectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Define a test route using the middleware
        Route::post('/test/bot-protected', function () {
            return response()->json(['message' => 'Success']);
        })->middleware('human:test_action');

        // Ensure config is enabled
        Config::set('bot_protection.enabled', true);
        Config::set('bot_protection.min_score', 0.5);
        Config::set('bot_protection.actions.test_action', [
            'enabled' => true,
            'required_score' => 0.7
        ]);
    }

    public function test_missing_token_returns_403()
    {
        $response = $this->postJson('/test/bot-protected');
        $response->assertStatus(403)
            ->assertJson(['code' => 'BOT_TOKEN_MISSING']);
    }

    public function test_low_score_returns_403()
    {
        // Mock Service to return low score
        $mockService = Mockery::mock(BotProtectionService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->with('bad-token', 'test_action')
            ->andReturn(['success' => true, 'score' => 0.3]);

        $this->app->instance(BotProtectionService::class, $mockService);

        $response = $this->postJson('/test/bot-protected', [], [
            'X-Human-Token' => 'bad-token'
        ]);

        $response->assertStatus(403)
            ->assertJson(['code' => 'BOT_VERIFICATION_FAILED']);
    }

    public function test_high_score_passes()
    {
        // Mock Service to return high score
        $mockService = Mockery::mock(BotProtectionService::class);
        $mockService->shouldReceive('verify')
            ->once()
            ->with('good-token', 'test_action')
            ->andReturn(['success' => true, 'score' => 0.9]);

        $this->app->instance(BotProtectionService::class, $mockService);

        $response = $this->postJson('/test/bot-protected', [], [
            'X-Human-Token' => 'good-token'
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Success']);
    }

    public function test_bypassed_if_config_disabled()
    {
        Config::set('bot_protection.enabled', false);

        $response = $this->postJson('/test/bot-protected');
        $response->assertStatus(200);
    }
}
