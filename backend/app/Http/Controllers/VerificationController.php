<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentSigner;
use App\Services\SignatureAssuranceService;
use App\Services\IdentityVerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VerificationController extends Controller
{
    protected IdentityVerificationService $verificationService;
    protected SignatureAssuranceService $signatureService;

    public function __construct(
        IdentityVerificationService $verificationService,
        SignatureAssuranceService $signatureService
    ) {
        $this->verificationService = $verificationService;
        $this->signatureService = $signatureService;
    }

    /**
     * Create email verification for signer.
     */
    public function createEmailVerification(Request $request, $signerId): JsonResponse
    {
        $signer = DocumentSigner::findOrFail($signerId);

        // Check if user is authorized
        if ($request->user()->id !== $signer->document->user_id && $signer->email !== $request->user()->email) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $verification = $this->verificationService->createEmailVerification(
            $signer,
            $request->ip()
        );

        return response()->json([
            'message' => 'Verification email sent',
            'verification' => $verification->only(['id', 'verification_type', 'status', 'expires_at']),
        ]);
    }

    /**
     * Verify email token.
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $verification = $this->verificationService->verifyEmailToken($request->token);

        if (!$verification) {
            return response()->json(['message' => 'Invalid or expired token'], 400);
        }

        return response()->json([
            'message' => 'Email verified successfully',
            'verification' => $verification,
        ]);
    }

    /**
     * Create OTP verification for signer.
     */
    public function createOTPVerification(Request $request, $signerId): JsonResponse
    {
        $signer = DocumentSigner::findOrFail($signerId);

        // Check if user is authorized
        if ($signer->email !== $request->user()->email) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $verification = $this->verificationService->createOTPVerification(
            $signer,
            $request->ip()
        );

        return response()->json([
            'message' => 'OTP sent',
            'verification' => $verification->only(['id', 'verification_type', 'status', 'expires_at']),
        ]);
    }

    /**
     * Verify OTP code.
     */
    public function verifyOTP(Request $request, $signerId): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $signer = DocumentSigner::findOrFail($signerId);

        // Check if user is authorized
        if ($signer->email !== $request->user()->email) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $verified = $this->verificationService->verifyOTP($signer, $request->code);

        if (!$verified) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        return response()->json(['message' => 'OTP verified successfully']);
    }

    /**
     * Create device fingerprint verification.
     */
    public function createDeviceVerification(Request $request, $signerId): JsonResponse
    {
        $request->validate([
            'fingerprint' => 'required|array',
            'fingerprint.userAgent' => 'required|string',
            'fingerprint.screenResolution' => 'nullable|string',
            'fingerprint.timezone' => 'nullable|string',
            'fingerprint.language' => 'nullable|string',
        ]);

        $signer = DocumentSigner::findOrFail($signerId);

        // Check if user is authorized
        if ($signer->email !== $request->user()->email) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $verification = $this->verificationService->createDeviceVerification(
            $signer,
            $request->fingerprint,
            $request->ip()
        );

        return response()->json([
            'message' => 'Device fingerprint captured',
            'verification' => $verification,
        ]);
    }

    /**
     * Get verification status for signer.
     */
    public function getVerificationStatus(Request $request, $signerId): JsonResponse
    {
        $signer = DocumentSigner::findOrFail($signerId);

        // Check if user is authorized
        if ($request->user()->id !== $signer->document->user_id && $signer->email !== $request->user()->email) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $requiredVerifications = $this->signatureService->getRequiredVerifications(
            $signer->document->signature_level
        );

        $completedVerifications = $this->verificationService->getVerifications($signer);
        $missingVerifications = $this->signatureService->getMissingVerifications($signer);

        return response()->json([
            'signer' => $signer->only(['id', 'name', 'email', 'status']),
            'signature_level' => $signer->document->signature_level,
            'required_verifications' => $requiredVerifications,
            'completed_verifications' => $completedVerifications,
            'missing_verifications' => $missingVerifications,
            'can_sign' => empty($missingVerifications),
        ]);
    }
}
