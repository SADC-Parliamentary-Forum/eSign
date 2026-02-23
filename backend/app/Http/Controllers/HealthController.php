<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Health Check Controller
 *
 * Provides health check endpoints for container orchestration and monitoring.
 * Used by Docker health checks and load balancers to determine service availability.
 */
class HealthController extends Controller
{
    /**
     * Liveness probe - Is the application running?
     * Used by container orchestration to determine if the container should be restarted.
     */
    public function liveness(): JsonResponse
    {
        return response()->json([
            'status' => 'alive',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Readiness probe - Is the application ready to receive traffic?
     * Used by load balancers to determine if traffic should be routed to this instance.
     */
    public function readiness(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
        ];

        $allHealthy = !in_array(false, array_column($checks, 'healthy'));
        $status = $allHealthy ? 'ready' : 'degraded';
        $httpCode = $allHealthy ? 200 : 503;

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $httpCode);
    }

    /**
     * Full health check with detailed service status.
     * Returns comprehensive health information for monitoring dashboards.
     */
    public function health(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
        ];

        $allHealthy = !in_array(false, array_column($checks, 'healthy'));
        $status = $allHealthy ? 'healthy' : 'unhealthy';
        $httpCode = $allHealthy ? 200 : 503;

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'checks' => $checks,
        ], $httpCode);
    }

    /**
     * Check database connectivity.
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'healthy' => true,
                'latency_ms' => $latency,
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'Database connection failed',
            ];
        }
    }

    /**
     * Check cache (Redis) connectivity.
     */
    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            $key = 'health_check_' . uniqid();
            Cache::put($key, true, 10);
            $result = Cache::get($key);
            Cache::forget($key);
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'healthy' => $result === true,
                'latency_ms' => $latency,
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'Cache connection failed',
            ];
        }
    }

    /**
     * Check storage (MinIO/S3) connectivity.
     */
    private function checkStorage(): array
    {
        try {
            $start = microtime(true);
            $disk = Storage::disk('minio');

            // Try to list files in root to verify connection
            $disk->directories('/');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'healthy' => true,
                'latency_ms' => $latency,
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'Storage connection failed',
            ];
        }
    }

    /**
     * Check queue connectivity.
     */
    private function checkQueue(): array
    {
        try {
            $start = microtime(true);
            $connection = config('queue.default');

            if ($connection === 'redis') {
                // Verify Redis queue connection
                $redis = app('redis')->connection(config('queue.connections.redis.connection'));
                $redis->ping();
            }

            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'healthy' => true,
                'connection' => $connection,
                'latency_ms' => $latency,
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => config('app.debug') ? $e->getMessage() : 'Queue connection failed',
            ];
        }
    }
}
