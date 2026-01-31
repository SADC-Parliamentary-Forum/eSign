<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SystemLogController extends Controller
{
    /**
     * Patterns to redact from logs for security.
     */
    private const REDACTION_PATTERNS = [
        '/password["\'\s:=]+[^\s"\',\]]+/i' => 'password: [REDACTED]',
        '/token["\'\s:=]+[^\s"\',\]]+/i' => 'token: [REDACTED]',
        '/secret["\'\s:=]+[^\s"\',\]]+/i' => 'secret: [REDACTED]',
        '/api_key["\'\s:=]+[^\s"\',\]]+/i' => 'api_key: [REDACTED]',
        '/key["\'\s:=]+[a-zA-Z0-9+\/=]{20,}/i' => 'key: [REDACTED]',
        '/Bearer\s+[^\s]+/' => 'Bearer [REDACTED]',
        '/authorization["\'\s:=]+[^\s"\',\]]+/i' => 'authorization: [REDACTED]',
        '/DB_PASSWORD[=:][^\s]+/' => 'DB_PASSWORD=[REDACTED]',
        '/MAIL_PASSWORD[=:][^\s]+/' => 'MAIL_PASSWORD=[REDACTED]',
        '/APP_KEY[=:][^\s]+/' => 'APP_KEY=[REDACTED]',
    ];

    /**
     * Get system logs.
     * Security: Requires admin role and sanitizes sensitive data.
     * Optionally accepts ?lines=N query param.
     */
    public function show(Request $request)
    {
        // Security: Only admins can view system logs
        if (!$request->user() || !$request->user()->hasPermission('admin')) {
            abort(403, 'Unauthorized. Admin access required.');
        }

        $lines = min((int) $request->input('lines', 1000), 5000); // Cap at 5000 lines
        $path = storage_path('logs/laravel.log');

        if (!file_exists($path)) {
            return response()->json(['content' => 'Log file not found.']);
        }

        // Read the file securely
        try {
            $content = file_get_contents($path);

            // Limit response size to 5MB
            if (strlen($content) > 5 * 1024 * 1024) {
                $content = substr($content, -(5 * 1024 * 1024));
                $content = "[(Truncated) ... showing last 5MB]\n" . $content;
            }

            // Security: Sanitize sensitive data from logs
            $content = $this->sanitizeLogs($content);

            // Reverse lines to show latest first
            $logLines = explode("\n", $content);
            $logLines = array_reverse(array_filter($logLines));
            $logLines = array_slice($logLines, 0, $lines);
            $content = implode("\n", $logLines);

            // Log access for audit purposes
            \Log::info('System logs accessed', [
                'user_id' => $request->user()->id,
                'user_email' => $request->user()->email,
                'lines_requested' => $lines,
            ]);

            return response()->json(['content' => $content]);
        } catch (\Exception $e) {
            $message = app()->isProduction() ? 'Error reading logs.' : 'Error reading logs: ' . $e->getMessage();
            return response()->json(['message' => $message], 500);
        }
    }

    /**
     * Sanitize sensitive data from log content.
     * Security: Prevents exposure of secrets, tokens, and credentials.
     */
    private function sanitizeLogs(string $content): string
    {
        foreach (self::REDACTION_PATTERNS as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        return $content;
    }
}
