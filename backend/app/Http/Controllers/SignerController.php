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
            'requires_account' => $signer->user_id === null,
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
            'user_signature_id' => 'nullable|uuid', // If using saved signature
        ]);

        // If user doesn't have an account yet, they need to create one
        if ($signer->user_id === null && !$request->user()) {
            return response()->json([
                'message' => 'You must create an account before signing.',
                'requires_registration' => true,
            ], 403);
        }

        // Link signer to user if they just registered/logged in
        if ($request->user() && $signer->user_id === null) {
            $signer->update(['user_id' => $request->user()->id]);
        }

        try {
            $document = $this->workflowService->processSignature(
                $signer,
                $validated['signature_data'],
                $request->ip(),
                $request->userAgent()
            );

            return response()->json([
                'message' => 'Document signed successfully.',
                'document_status' => $document->status,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
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
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
