<?php

namespace App\Http\Controllers;

use App\Models\DocumentSigner;
use App\Models\IdentityVerification;
use App\Mail\OtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VerificationController extends Controller
{
    /**
     * Ensure the authenticated user is the signer for the given DocumentSigner (by user_id or email).
     */
    private function authorizeSigner(Request $request, DocumentSigner $signer): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }
        if ($signer->user_id !== null && $signer->user_id === $user->id) {
            return true;
        }
        return $signer->email === $user->email;
    }

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

        if (!hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid hash.'], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        if ($user->markEmailAsVerified()) {
            event(new \Illuminate\Auth\Events\Verified($user));
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

        if (!$this->authorizeSigner($request, $signer)) {
            return response()->json(['message' => 'You are not authorized to request OTP for this signer.'], 403);
        }

        // Generate OTP
        $otp = (string) rand(100000, 999999);

        // Create Verification Record
        $verification = IdentityVerification::create([
            'document_signer_id' => $signer->id,
            'verification_type' => 'OTP',
            'verification_code' => $otp, // Hash this in production!
            'status' => 'PENDING',
            'ip_address' => $request->ip(),
            'expires_at' => now()->addMinutes(15),
        ]);

        // Send OTP via email
        try {
            Mail::to($signer->email)->send(new OtpMail($otp, $signer->email));
        } catch (\Exception $e) {
            Log::error('Failed to send OTP email to signer', [
                'signer_email' => $signer->email,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info("OTP sent for Signer {$signer->email}");

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

        if (!$this->authorizeSigner($request, $signer)) {
            return response()->json(['message' => 'You are not authorized to verify OTP for this signer.'], 403);
        }

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

        if ($request->code !== $verification->verification_code) {
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
    public function getVerificationStatus(Request $request, $signerId)
    {
        $signer = DocumentSigner::findOrFail($signerId);

        if (!$this->authorizeSigner($request, $signer)) {
            return response()->json(['message' => 'You are not authorized to view verification status for this signer.'], 403);
        }

        return response()->json([
            'is_verified' => !is_null($signer->verified_at),
            'verified_at' => $signer->verified_at,
            'method' => $signer->verification_method,
        ]);
    }
}
