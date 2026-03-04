<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\EvidencePackageService;
use App\Services\SignatureAssuranceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class EvidencePackageController extends Controller
{
    protected EvidencePackageService $evidenceService;
    protected SignatureAssuranceService $signatureService;

    public function __construct(
        EvidencePackageService $evidenceService,
        SignatureAssuranceService $signatureService
    ) {
        $this->evidenceService = $evidenceService;
        $this->signatureService = $signatureService;
    }

    /**
     * Generate evidence package for document.
     */
    public function generate(Request $request, $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        // Check if user is authorized (owner or signer)
        if (
            $request->user()->id !== $document->user_id &&
            !$document->signers()->where('email', $request->user()->email)->exists()
        ) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if document is completed
        if ($document->status !== 'COMPLETED') {
            return response()->json([
                'message' => 'Evidence package can only be generated for completed documents',
            ], 400);
        }

        try {
            // Update trust score before generating
            $this->signatureService->updateTrustScore($document);

            // Generate evidence package
            $filepath = $this->evidenceService->generateEvidencePackage($document);

            return response()->json([
                'message' => 'Evidence package generated successfully',
                'evidence_package' => [
                    'path' => $filepath,
                    'generated_at' => $document->evidence_generated_at,
                    'trust_score' => $document->trust_score,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Evidence package generation failed for document ' . $id . ': ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to generate evidence package. Please try again.',
            ], 500);
        }
    }

    /**
     * Download evidence package.
     */
    public function download(Request $request, $id)
    {
        $document = Document::findOrFail($id);

        // Check if user is authorized
        if (
            $request->user()->id !== $document->user_id &&
            !$document->signers()->where('email', $request->user()->email)->exists()
        ) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('minio');

        if (!$document->evidence_package_path || !$disk->exists($document->evidence_package_path)) {
            // Auto-generate if missing
            try {
                $this->signatureService->updateTrustScore($document);
                $this->evidenceService->generateEvidencePackage($document);
                $document->refresh();
            } catch (\Exception $e) {
                \Log::error('Evidence package generation failed: ' . $e->getMessage());
                return response()->json([
                    'message' => 'Evidence package not found and could not be generated.',
                ], 500);
            }
        }

        // Create a clean filename from document title
        $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($document->title, PATHINFO_FILENAME));
        $filename = 'Evidence_Package_' . $baseName . '_' . $document->id . '.pdf';

        return $disk->download($document->evidence_package_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Get evidence package info.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        // Check if user is authorized
        if (
            $request->user()->id !== $document->user_id &&
            !$document->signers()->where('email', $request->user()->email)->exists()
        ) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'document' => $document->only(['id', 'title', 'status']),
            'evidence_package' => [
                'exists' => !is_null($document->evidence_package_path),
                'path' => $document->evidence_package_path,
                'generated_at' => $document->evidence_generated_at,
            ],
            'trust_score' => $document->trust_score,
            'trust_breakdown' => $document->trust_breakdown,
        ]);
    }
}
