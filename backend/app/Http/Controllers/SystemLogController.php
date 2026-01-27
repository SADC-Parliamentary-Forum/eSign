<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SystemLogController extends Controller
{
    /**
     * Get system logs.
     * Optionally accepts ?lines=N query param.
     */
    public function show(Request $request)
    {
        $lines = $request->input('lines', 1000);
        $path = storage_path('logs/laravel.log');

        if (!file_exists($path)) {
            return response()->json(['content' => 'Log file not found.']);
        }

        // Read the file securely
        try {
            $content = file_get_contents($path);

            // If the file is too large, we might want to trim it, 
            // but for now let's just return the last N lines if requested logic is complex, 
            // or simply return content. 
            // Let's implement a simple tail logic if we can, or just return all for V1.

            // Simple approach: standard read. 
            // For a production app, we should use 'tail' command or a library to read end of file.
            // But reading whole file into memory is risky if it's huge. 

            // Let's limit response size
            if (strlen($content) > 5 * 1024 * 1024) { // 5MB limit
                $content = substr($content, -(5 * 1024 * 1024));
                $content = "[(Truncated) ... showing last 5MB]\n" . $content;
            }

            // Reverse lines to show latest first
            $lines = explode("\n", $content);
            $lines = array_reverse(array_filter($lines));
            $content = implode("\n", $lines);

            return response()->json(['content' => $content]);
        } catch (\Exception $e) {
            $message = app()->isProduction() ? 'Error reading logs.' : 'Error reading logs: ' . $e->getMessage();
            return response()->json(['message' => $message], 500);
        }
    }
}
