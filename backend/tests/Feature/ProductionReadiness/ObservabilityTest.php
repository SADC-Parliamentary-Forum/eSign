<?php

namespace Tests\Feature\ProductionReadiness;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * TEST SUITE 3 — OBSERVABILITY & CORRELATION
 */
class ObservabilityTest extends TestCase
{
    /**
     * TC-3.1 Correlation ID Propagation
     */
    public function test_correlation_id_propagates_to_response_headers()
    {
        $correlationId = (string) Str::uuid();

        // Make request with X-Correlation-ID
        $response = $this->withHeaders([
            'X-Correlation-ID' => $correlationId,
        ])->get('/api/health');

        // Assert response has same ID
        $response->assertHeader('X-Correlation-ID', $correlationId);
    }

    public function test_correlation_id_is_generated_if_missing()
    {
        $response = $this->get('/api/health');

        $response->assertHeader('X-Correlation-ID');
        $this->assertTrue(Str::isUuid($response->headers->get('X-Correlation-ID')));
    }

    /**
     * TC-3.2 Log Schema Validation (Simulated)
     * 
     * In a real integration test, we would parse the actual log file.
     * Here we verify the CorrelationId middleware pushes context to the logger.
     */
    public function test_correlation_id_is_pushed_to_log_context()
    {
        $correlationId = (string) Str::uuid();

        // We can spy on the Log facade or check the shared context if using Laravel 11
        // For strictly black-box testing, we might need to read the log file, 
        // but let's assume checking if the middleware runs is sufficient for unit level.

        $this->withHeaders(['X-Correlation-ID' => $correlationId])
            ->get('/api/health');

        // In Laravel 11, we can check shared context
        $context = Log::sharedContext();
        $this->assertArrayHasKey('correlation_id', $context);
        $this->assertEquals($correlationId, $context['correlation_id']);
    }
}
