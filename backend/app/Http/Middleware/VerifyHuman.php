<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Config;
use App\Services\BotProtection\BotProtectionService;

class VerifyHuman
{
    protected $botService;

    public function __construct(BotProtectionService $botService)
    {
        $this->botService = $botService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $action  Specific action name to check config against (e.g. 'login')
     */
    public function handle(Request $request, Closure $next, ?string $action = null): Response
    {
        // 1. Check if Global or System is enabled
        if (!Config::get('bot_protection.enabled')) {
            return $next($request);
        }

        // 2. Skip verification for authenticated users on write actions (e.g. document_upload)
        //    to avoid blocking legitimate users when reCAPTCHA score is low (VPN, privacy tools, etc.)
        $skipWhenAuthenticated = ['document_upload'];
        if ($action && in_array($action, $skipWhenAuthenticated, true) && $request->user()) {
            return $next($request);
        }

        // 3. Check if Action is enabled (if action provided)
        $minScore = Config::get('bot_protection.min_score', 0.5);
        if ($action) {
            $actionConfig = Config::get("bot_protection.actions.{$action}");
            if (isset($actionConfig['enabled']) && !$actionConfig['enabled']) {
                return $next($request);
            }
            if (isset($actionConfig['required_score'])) {
                $minScore = $actionConfig['required_score'];
            }
        }

        // 4. Retrieve Token
        $token = $request->header('X-Human-Token');

        if (!$token) {
            $blockWhenMissing = Config::get('bot_protection.enforcement.block_when_token_missing', true);
            if ($blockWhenMissing && Config::get('bot_protection.enforcement.block_on_failure')) {
                return response()->json([
                    'message' => 'Bot protection token missing.',
                    'code' => 'BOT_TOKEN_MISSING'
                ], 403);
            }
            // Token missing but not blocking (e.g. mobile app): skip verification
            return $next($request);
        }

        // 5. Verify Token
        $result = $this->botService->verify($token, $action);

        if (!$result['success'] || $result['score'] < $minScore) {
            if (Config::get('bot_protection.enforcement.log_attempts')) {
                \Illuminate\Support\Facades\Log::warning('BotProtection: Blocked request', [
                    'action' => $action,
                    'score' => $result['score'] ?? 0,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
            }

            if (Config::get('bot_protection.enforcement.block_on_failure')) {
                return response()->json([
                    'message' => 'Bot protection verification failed.',
                    'code' => 'BOT_VERIFICATION_FAILED',
                    'score' => $result['score'] ?? 0
                ], 403);
            }
        }

        return $next($request);
    }
}
