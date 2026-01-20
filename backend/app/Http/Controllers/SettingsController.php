<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    /**
     * Get all system settings.
     */
    public function index()
    {
        $settings = Cache::get('system_settings', $this->getDefaults());

        return response()->json($settings);
    }

    /**
     * Update system settings.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'app_name' => 'nullable|string|max:255',
            'require_mfa' => 'nullable|boolean',
            'session_timeout' => 'nullable|integer|min:5|max:1440',
            'max_document_size' => 'nullable|integer|min:1|max:100',
            'allowed_file_types' => 'nullable|string|max:255',
            'email_from_name' => 'nullable|string|max:255',
            'email_from_address' => 'nullable|email|max:255',
        ]);

        $current = Cache::get('system_settings', $this->getDefaults());
        $updated = array_merge($current, $validated);

        // Store settings in cache (persisted for 1 year)
        Cache::put('system_settings', $updated, now()->addYear());

        return response()->json([
            'message' => 'Settings updated successfully',
            'settings' => $updated,
        ]);
    }

    /**
     * Get default settings.
     */
    private function getDefaults(): array
    {
        return [
            'app_name' => config('app.name', 'eSign'),
            'require_mfa' => false,
            'session_timeout' => 60,
            'max_document_size' => 25,
            'allowed_file_types' => 'pdf,doc,docx',
            'email_from_name' => config('mail.from.name', 'eSign Platform'),
            'email_from_address' => config('mail.from.address', 'noreply@esign.com'),
        ];
    }
}
