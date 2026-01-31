<?php

namespace Tests\Feature\ProductionReadiness;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TEST SUITE 0 — GLOBAL PRECONDITIONS
 * TC-0.1 Environment Sanity
 */
class GlobalPreconditionsTest extends TestCase
{
    // We don't want to refresh the database for production readiness checks on a real env,
    // but for automated functional tests we might need mixed strategies.
    // For now, let's assume this runs against a test DB or we are just checking availability.
    // use RefreshDatabase; 

    /**
     * TC-0.1 Environment Sanity
     * 
     * Given: Application is deployed
     * Assert: /health returns 200, Metrics endpoint reachable, Logs are ingested
     */
    public function test_health_check_endpoint_returns_healthy_status()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'healthy',
            ])
            ->assertJsonStructure([
                'status',
                'timestamp',
                'checks' => [
                    'database' => ['healthy'],
                    'cache' => ['healthy'],
                    'storage' => ['healthy'],
                    'queue' => ['healthy'],
                ],
            ]);

        // Verify all services report healthy
        $data = $response->json();
        $this->assertTrue($data['checks']['database']['healthy'], 'Database should be healthy');
        $this->assertTrue($data['checks']['cache']['healthy'], 'Cache should be healthy');
    }

    public function test_database_connection_is_active()
    {
        try {
            $pdo = DB::connection()->getPdo();
            $this->assertNotNull($pdo, 'Database connection is null');
        } catch (\Exception $e) {
            $this->fail('Database connection failed: ' . $e->getMessage());
        }
    }

    public function test_application_is_not_in_debug_mode_if_production()
    {
        if (app()->environment('production')) {
            $this->assertFalse(config('app.debug'), 'App should not be in debug mode in production');
        } else {
            $this->markTestSkipped('Not in production environment');
        }
    }
}
