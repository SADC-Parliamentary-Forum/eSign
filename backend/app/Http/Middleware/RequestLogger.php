<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs every API request with structured, non-PII metadata.
 *
 * Logged fields: method, path, HTTP status, duration_ms, user_id.
 * Request body, query parameters, and headers are intentionally excluded.
 */
class RequestLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        Log::channel('esign')->info('API Request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'user_id' => optional($request->user())->id,
        ]);

        return $response;
    }
}
