<?php

namespace App\Services;

use App\Models\DocumentSigner;
use App\Models\IdentityVerification;
use App\Mail\OtpMail;
use App\Mail\VerifySignerMail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class IdentityVerificationService
{
    /**
     * Create email verification.
     */
    public function createEmailVerification(DocumentSigner $signer, ?string $ipAddress = null): IdentityVerification
    {
        $token = Str::random(64);

        $verification = IdentityVerification::create([
            'document_signer_id' => $signer->id,
            'verification_type' => 'EMAIL',
            'status' => 'PENDING',
            'verification_token' => hash('sha256', $token),
            'ip_address' => $ipAddress,
            'expires_at' => now()->addHours(24),
        ]);

        // Send verification email
        $this->sendVerificationEmail($signer, $token);

        Log::info('Email verification created', [
            'signer_id' => $signer->id,
            'expires_at' => $verification->expires_at,
        ]);

        return $verification;
    }

    /**
     * Verify email token.
     */
    public function verifyEmailToken(string $token): ?IdentityVerification
    {
        $hashedToken = hash('sha256', $token);

        $verification = IdentityVerification::where('verification_token', $hashedToken)
            ->where('verification_type', 'EMAIL')
            ->where('status', 'PENDING')
            ->first();

        if (!$verification) {
            return null;
        }

        if ($verification->isExpired()) {
            $verification->update(['status' => 'EXPIRED']);
            return null;
        }

        $verification->markAsVerified();

        Log::info('Email verified', ['verification_id' => $verification->id]);

        return $verification;
    }

    /**
     * Create OTP verification.
     */
    public function createOTPVerification(DocumentSigner $signer, ?string $ipAddress = null): IdentityVerification
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $verification = IdentityVerification::create([
            'document_signer_id' => $signer->id,
            'verification_type' => 'OTP',
            'status' => 'PENDING',
            'verification_code' => $code,
            'ip_address' => $ipAddress,
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
        ]);

        // Send OTP via SMS or email
        $this->sendOTP($signer, $code);

        Log::info('OTP verification created', [
            'signer_id' => $signer->id,
            'expires_at' => $verification->expires_at,
        ]);

        return $verification;
    }

    /**
     * Verify OTP code.
     */
    public function verifyOTP(DocumentSigner $signer, string $code): bool
    {
        $verification = IdentityVerification::where('document_signer_id', $signer->id)
            ->where('verification_type', 'OTP')
            ->where('status', 'PENDING')
            ->latest()
            ->first();

        if (!$verification) {
            return false;
        }

        if ($verification->isExpired()) {
            $verification->update(['status' => 'EXPIRED']);
            return false;
        }

        if ($verification->maxAttemptsReached()) {
            return false;
        }

        if ($verification->verification_code !== $code) {
            $verification->incrementAttempts();
            Log::warning('Invalid OTP attempt', [
                'signer_id' => $signer->id,
                'attempts' => $verification->attempts,
            ]);
            return false;
        }

        $verification->markAsVerified();

        Log::info('OTP verified', ['verification_id' => $verification->id]);

        return true;
    }

    /**
     * Create device fingerprint verification.
     */
    public function createDeviceVerification(
        DocumentSigner $signer,
        array $deviceFingerprint,
        ?string $ipAddress = null
    ): IdentityVerification {
        $geolocation = $this->getGeolocation($ipAddress);

        $verification = IdentityVerification::create([
            'document_signer_id' => $signer->id,
            'verification_type' => 'DEVICE',
            'status' => 'VERIFIED', // Auto-verified on capture
            'ip_address' => $ipAddress,
            'device_fingerprint' => $deviceFingerprint,
            'geolocation' => $geolocation,
            'verified_at' => now(),
        ]);

        Log::info('Device fingerprint captured', [
            'signer_id' => $signer->id,
            'ip' => $ipAddress,
            'location' => $geolocation['city'] ?? 'Unknown',
        ]);

        return $verification;
    }

    /**
     * Get geolocation from IP address.
     */
    protected function getGeolocation(?string $ipAddress): ?array
    {
        if (!$ipAddress || $ipAddress === '127.0.0.1') {
            return null;
        }

        try {
            // Use ipapi.co for IP geolocation (free tier: 1000 req/day)
            $baseUrl = config('esign.services.ipapi.url');
            $response = file_get_contents("{$baseUrl}/{$ipAddress}/json/");
            $data = json_decode($response, true);

            return [
                'ip' => $data['ip'] ?? $ipAddress,
                'city' => $data['city'] ?? null,
                'region' => $data['region'] ?? null,
                'country' => $data['country_name'] ?? null,
                'country_code' => $data['country_code'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'timezone' => $data['timezone'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Geolocation lookup failed', [
                'ip' => $ipAddress,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Send verification email.
     */
    protected function sendVerificationEmail(DocumentSigner $signer, string $token): void
    {
        $verificationUrl = config('app.frontend_url') . "/verify-email?token={$token}";

        try {
            Mail::to($signer->email)->send(new VerifySignerMail($verificationUrl, $signer->name));
        } catch (\Exception $e) {
            Log::error('Failed to send verification email', [
                'signer_email' => $signer->email,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Verification email sent', [
            'signer_email' => $signer->email,
        ]);
    }

    /**
     * Send OTP.
     */
    protected function sendOTP(DocumentSigner $signer, string $code): void
    {
        try {
            Mail::to($signer->email)->send(new OtpMail($code, $signer->email));
        } catch (\Exception $e) {
            Log::error('Failed to send OTP email', [
                'signer_email' => $signer->email,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('OTP sent', ['signer_email' => $signer->email]);
    }

    /**
     * Get all verifications for signer.
     */
    public function getVerifications(DocumentSigner $signer): array
    {
        return IdentityVerification::where('document_signer_id', $signer->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('verification_type')
            ->map(fn($group) => $group->first())
            ->toArray();
    }
}
