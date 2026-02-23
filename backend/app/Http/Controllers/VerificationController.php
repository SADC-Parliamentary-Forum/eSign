<?php

namespace App\Http\Controllers;

use App\Models\DocumentSigner;
use App\Models\IdentityVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class VerificationController extends Controller
{
    /**
     * Send email verification link.
     */
    public function createEmailVerification(Request $request, $signerId)
    {
        // ... (Implementation or stub)
        return response()->json(['message' => 'Not implemented yet, use standard auth verification.']);
    }

    /**
     * Verify email (Standard Laravel Verification).
     */
    public function verify(Request $request)
    {
        $user = \App\Models\User::find($request->route('id'));

        if (!$user) {
            return response()->json(['message' => 'Invalid user.'], 400);
        }

        // Security: Use SHA-256 instead of SHA-1 for email hash verification
        if (!hash_equals((string) $request->route('hash'), hash('sha256', $user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid hash.'], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        if ($user->markEmailAsVerified()) {
            event(new \Illuminate\Auth\Events\Verified($user));
            // Fix: Clear user cache so 'me' endpoint returns fresh data
            Cache::forget("user.{$user->id}");
        }

        return response()->json(['message' => 'Email verified successfully.']);
    }

    /**
     * API Endpoint to verify email (if using POST code or similar)
     */
    public function verifyEmail(Request $request)
    {
        // This seems to be a duplicate or alternative to verify()
        // For now, let's just alias it or implement if needed.
        return $this->verify($request);
    }

    /**
     * Send OTP to signer's email.
     */
    public function createOTPVerification(Request $request, $signerId)
    {
        $signer = DocumentSigner::findOrFail($signerId);

        // Security: Ensure the user requesting this is the signer (via token) or authorized user
        // For guest access, we usually trust the token in URL, but here we might need validation.
        // Assuming this endpoint is called from the signing page which has the `token`.
        // TODO: Validate token if needed, or rely on the route middleware if it was protected.

        // Generate OTP using cryptographically secure random
        $otp = (string) random_int(100000, 999999);

        // Create Verification Record with hashed OTP (security: never store plain OTP)
        $verification = IdentityVerification::create([
            'document_signer_id' => $signer->id,
            'verification_type' => 'OTP',
            'verification_code' => hash('sha256', $otp),
            'status' => 'PENDING',
            'ip_address' => $request->ip(),
            'expires_at' => now()->addMinutes(15),
        ]);

        // Send OTP via email
        \Illuminate\Support\Facades\Mail::to($signer->email)->queue(new \App\Mail\OtpMail($otp));

        return response()->json([
            'message' => 'OTP sent to email.',
            'verification_id' => $verification->id,
        ]);
    }

    /**
     * Verify OTP.
     */
    public function verifyOTP(Request $request, $signerId)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $signer = DocumentSigner::findOrFail($signerId);

        $verification = IdentityVerification::where('document_signer_id', $signer->id)
            ->where('verification_type', 'OTP')
            ->where('status', 'PENDING')
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$verification) {
            return response()->json(['message' => 'Invalid or expired OTP request.'], 400);
        }

        if ($verification->attempts >= 3) {
            $verification->update(['status' => 'FAILED']);
            return response()->json(['message' => 'Too many failed attempts.'], 400);
        }

        // Compare hashed OTP (security: use timing-safe comparison)
        if (!hash_equals($verification->verification_code, hash('sha256', $request->code))) {
            $verification->increment('attempts');
            return response()->json(['message' => 'Invalid code.'], 400);
        }

        // Success
        $verification->update([
            'status' => 'VERIFIED',
            'verified_at' => now(),
            'ip_address' => $request->ip(),
        ]);

        // Update Signer
        $signer->update([
            'verified_at' => now(),
            'verification_method' => 'OTP',
            'verification_data' => ['verification_id' => $verification->id]
        ]);

        return response()->json(['message' => 'Identity verified successfully.']);
    }

    /**
     * Get verification status.
     */
    public function getVerificationStatus($signerId)
    {
        $signer = DocumentSigner::findOrFail($signerId);

        return response()->json([
            'is_verified' => !is_null($signer->verified_at),
            'verified_at' => $signer->verified_at,
            'method' => $signer->verification_method,
        ]);
    }
}
