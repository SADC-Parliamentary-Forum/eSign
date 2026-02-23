<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    /**
     * Clear application caches.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearCache()
    {
        try {
            Artisan::call('cache:clear');
            Artisan::call('view:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('event:clear');

            // Re-cache for performance (optional, but good for production)
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache'); // Only if you want to verify views immediately

            Log::info('System cache cleared by admin user', ['user_id' => auth()->id()]);

            return response()->json([
                'message' => 'System caches cleared successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear cache', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to clear cache: ' . $e->getMessage(),
            ], 500);
        }
    }
}
