<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Correlation ID Middleware
 *
 * Generates a unique correlation ID for each request to enable
 * end-to-end request tracing across logs and services.
 *
 * Security & Observability: Essential for debugging production issues
 * by linking frontend errors to backend logs.
 */
class CorrelationId
{
    public const HEADER_NAME = 'X-Correlation-ID';
    public const LOG_KEY = 'correlation_id';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Use existing correlation ID from request header or generate new one
        $correlationId = $request->header(self::HEADER_NAME) ?? Str::uuid()->toString();

        // Store in request for access throughout the application
        $request->attributes->set(self::LOG_KEY, $correlationId);

        // Add to log context for all subsequent log entries
        Log::shareContext([
            self::LOG_KEY => $correlationId,
            'request_id' => $correlationId,
            'user_id' => $request->user()?->id,
            'request_path' => $request->path(),
            'request_method' => $request->method(),
            'client_ip' => $request->ip(),
        ]);

        $response = $next($request);

        // Add correlation ID to response headers
        $response->headers->set(self::HEADER_NAME, $correlationId);

        return $response;
    }
}
