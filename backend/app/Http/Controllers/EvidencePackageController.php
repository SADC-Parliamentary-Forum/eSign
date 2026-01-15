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
            return response()->json([
                'message' => 'Failed to generate evidence package',
                'error' => $e->getMessage(),
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

        if (!$document->evidence_package_path) {
            return response()->json([
                'message' => 'Evidence package not found. Generate it first.',
            ], 404);
        }

        if (!Storage::exists($document->evidence_package_path)) {
            return response()->json([
                'message' => 'Evidence package file not found.',
            ], 404);
        }

        $filename = 'Evidence_Package_' . $document->id . '.pdf';

        return Storage::download($document->evidence_package_path, $filename);
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
