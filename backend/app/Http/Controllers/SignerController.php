<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SigningWorkflowService;
use App\Models\DocumentSigner;
use App\Models\Document;

class SignerController extends Controller
{
    protected SigningWorkflowService $workflowService;

    public function __construct(SigningWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Get document for signing via access token.
     * This is a public endpoint for guest signers.
     */
    public function show(string $token)
    {
        $signer = $this->workflowService->getSignerByToken($token);

        if (!$signer) {
            return response()->json(['message' => 'Invalid or expired signing link.'], 404);
        }

        $document = $signer->document;

        // Check if document is still signable
        if ($document->isExpired()) {
            return response()->json(['message' => 'This document has expired.'], 410);
        }

        if ($document->status === 'completed') {
            return response()->json(['message' => 'This document has already been completed.'], 410);
        }

        if ($document->status === 'declined') {
            return response()->json(['message' => 'This document has been declined.'], 410);
        }

        return response()->json([
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'status' => $document->status,
                'file_path' => $document->file_path,
                'expires_at' => $document->expires_at,
            ],
            'signer' => [
                'id' => $signer->id,
                'name' => $signer->name,
                'email' => $signer->email,
                'status' => $signer->status,
                'can_sign' => $signer->canSign(),
                'signing_order' => $signer->signing_order,
            ],
            'fields' => $document->signatureFields,
            'requires_account' => true,
            'requires_verification' => true,
        ]);
    }

    /**
     * Mark document as viewed by signer.
     */
    public function markViewed(string $token)
    {
        $signer = $this->workflowService->getSignerByToken($token);

        if (!$signer) {
            return response()->json(['message' => 'Invalid signing link.'], 404);
        }

        $signer->markAsViewed();

        return response()->json(['message' => 'Document marked as viewed.']);
    }

    /**
     * Sign the document.
     */
    public function sign(Request $request, string $token)
    {
        $signer = $this->workflowService->getSignerByToken($token);

        if (!$signer) {
            return response()->json(['message' => 'Invalid signing link.'], 404);
        }

        if (!$signer->canSign()) {
            return response()->json(['message' => 'You cannot sign this document at this time.'], 403);
        }

        $validated = $request->validate([
            'signature_data' => 'required|string', // Base64 signature image
            'initials_data' => 'nullable|string',  // Base64 initials image
            'user_signature_id' => 'nullable|uuid', // If using saved signature
            'user_initials_id' => 'nullable|uuid',  // If using saved initials
            'save_to_profile' => 'nullable|boolean',
        ]);

        // Security: Validate Signature Data Size & Format
        if (!empty($validated['signature_data'])) {
            if (strlen($validated['signature_data']) > 680000) {
                return response()->json(['message' => 'Signature too large.'], 422);
            }
            if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $validated['signature_data'])) {
                return response()->json(['message' => 'Invalid signature format (PNG/JPEG only).'], 422);
            }
        }
        if (!empty($validated['initials_data'])) {
            if (strlen($validated['initials_data']) > 680000) {
                return response()->json(['message' => 'Initials too large.'], 422);
            }
            if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $validated['initials_data'])) {
                return response()->json(['message' => 'Invalid initials format (PNG/JPEG only).'], 422);
            }
        }

        // 1. Enforce Authentication
        if (!$request->user()) {
            return response()->json([
                'message' => 'You must be logged in to sign this document.',
                'requires_login' => true,
            ], 401);
        }

        // 2. Enforce Email Match
        if ($request->user()->email !== $signer->email) {
            return response()->json([
                'message' => 'The email address of your account (' . $request->user()->email . ') does not match the signer email (' . $signer->email . '). Please log in with the correct account.',
            ], 403);
        }

        // 3. Enforce Email Verification
        if (!$request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Please verify your email address before signing.',
                'requires_verification' => true,
            ], 403);
        }

        // Link signer to user if not already linked
        if ($signer->user_id === null) {
            $signer->update(['user_id' => $request->user()->id]);
        }

        $user = $request->user();

        // Save signature to profile if requested
        if (($validated['save_to_profile'] ?? false) && empty($validated['user_signature_id'])) {
            try {
                $user->signatures()->updateOrCreate(
                    ['type' => 'signature', 'is_default' => true],
                    [
                        'name' => 'Default Signature',
                        'image_data' => $validated['signature_data'],
                        'method' => 'DRAWN',
                    ]
                );
            } catch (\Exception $e) {
                // Ignore error if saving fails, don't block signing
            }
        }

        // Save initials to profile if requested
        if (($validated['save_to_profile'] ?? false) && !empty($validated['initials_data']) && empty($validated['user_initials_id'])) {
            try {
                $user->signatures()->updateOrCreate(
                    ['type' => 'initials', 'is_default' => true],
                    [
                        'name' => 'Default Initials',
                        'image_data' => $validated['initials_data'],
                        'method' => 'DRAWN',
                    ]
                );
            } catch (\Exception $e) {
                // Ignore
            }
        }

        try {
            $document = $this->workflowService->processSignature(
                $signer,
                $validated['signature_data'],
                $validated['initials_data'] ?? null,
                $request->ip(),
                $request->userAgent()
            );

            return response()->json([
                'message' => 'Document signed successfully.',
                'document_status' => $document->status,
            ]);
        } catch (\Exception $e) {
            $message = app()->isProduction() ? 'An error occurred during closing.' : $e->getMessage();
            return response()->json(['message' => $message], 400);
        }
    }

    /**
     * Decline to sign the document.
     */
    public function decline(Request $request, string $token)
    {
        $signer = $this->workflowService->getSignerByToken($token);

        if (!$signer) {
            return response()->json(['message' => 'Invalid signing link.'], 404);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $document = $this->workflowService->processDecline(
                $signer,
                $validated['reason'] ?? null
            );

            return response()->json([
                'message' => 'You have declined to sign this document.',
                'document_status' => $document->status,
            ]);
        } catch (\Exception $e) {
            $message = app()->isProduction() ? 'An error occurred.' : $e->getMessage();
            return response()->json(['message' => $message], 400);
        }
    }
}
