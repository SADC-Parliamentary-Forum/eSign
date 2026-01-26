<?php

namespace App\Services\BotProtection;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class BotProtectionService
{
    protected $provider;
    protected $config;

    public function __construct()
    {
        $this->provider = Config::get('bot_protection.provider', 'recaptcha');
        $this->config = Config::get("bot_protection.providers.{$this->provider}");
    }

    /**
     * Verify the human token.
     *
     * @param string $token The token from the frontend.
     * @param string|null $action The action being performed (optional context).
     * @return array Result ['success' => bool, 'score' => float, 'errors' => array]
     */
    public function verify(string $token, ?string $action = null): array
    {
        if (!Config::get('bot_protection.enabled')) {
            return ['success' => true, 'score' => 1.0, 'bypass' => true];
        }

        if ($this->provider === 'recaptcha') {
            return $this->verifyRecaptcha($token);
        }

        // Fallback / Other providers
        Log::warning("BotProtection: Unsupported provider {$this->provider}");
        return ['success' => false, 'score' => 0.0, 'errors' => ['unsupported_provider']];
    }

    protected function verifyRecaptcha(string $token): array
    {
        try {
            $response = Http::asForm()->post($this->config['verify_url'], [
                'secret' => $this->config['secret_key'],
                'response' => $token,
            ]);

            $data = $response->json();

            if (!$data['success']) {
                Log::info('BotProtection: reCAPTCHA failed', ['errors' => $data['error-codes'] ?? []]);
                return [
                    'success' => false,
                    'score' => 0.0,
                    'errors' => $data['error-codes'] ?? ['verification_failed']
                ];
            }

            // reCAPTCHA v3 returns a score (0.0 - 1.0)
            // v2 returns success true/false (effectively score 1.0 or 0.0)
            $score = $data['score'] ?? 1.0;

            return [
                'success' => true,
                'score' => $score,
                'hostname' => $data['hostname'] ?? null,
                'action' => $data['action'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error("BotProtection: Connection error: " . $e->getMessage());
            return ['success' => false, 'score' => 0.0, 'errors' => ['connection_error']];
        }
    }
}
