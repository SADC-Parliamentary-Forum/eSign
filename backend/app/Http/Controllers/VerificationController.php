<?php

namespace App\Http\Controllers;

use App\Models\DocumentSigner;
use App\Models\IdentityVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VerificationController extends Controller
{
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

        // Mock Email Sending (Log it for MVP / Demo)
        // In production: Mail::to($signer->email)->send(new OtpMail($otp));
        \Log::info("OTP for Signer {$signer->email}: {$otp}");

        return response()->json([
            'message' => 'OTP sent to email.',
            'verification_id' => $verification->id,
            'debug_otp' => $otp // REMOVE IN PRODUCTION
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
