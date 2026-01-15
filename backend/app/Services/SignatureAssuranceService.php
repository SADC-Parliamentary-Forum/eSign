<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\IdentityVerification;
use Illuminate\Support\Facades\Log;

class SignatureAssuranceService
{
    /**
     * Get required verification methods for signature level.
     */
    public function getRequiredVerifications(string $signatureLevel): array
    {
        return match ($signatureLevel) {
            'SIMPLE' => ['EMAIL'],
            'ADVANCED' => ['EMAIL', 'OTP'],
            'QUALIFIED' => ['EMAIL', 'OTP', 'DEVICE'],
            default => ['EMAIL'],
        };
    }

    /**
     * Check if signer has completed required verifications.
     */
    public function hasCompletedVerifications(DocumentSigner $signer): bool
    {
        $document = $signer->document;
        $requiredVerifications = $this->getRequiredVerifications($document->signature_level);

        foreach ($requiredVerifications as $verificationType) {
            $verification = IdentityVerification::where('document_signer_id', $signer->id)
                ->where('verification_type', $verificationType)
                ->where('status', 'VERIFIED')
                ->first();

            if (!$verification) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get missing verifications for signer.
     */
    public function getMissingVerifications(DocumentSigner $signer): array
    {
        $document = $signer->document;
        $requiredVerifications = $this->getRequiredVerifications($document->signature_level);
        $completedVerifications = IdentityVerification::where('document_signer_id', $signer->id)
            ->where('status', 'VERIFIED')
            ->pluck('verification_type')
            ->toArray();

        return array_diff($requiredVerifications, $completedVerifications);
    }

    /**
     * Calculate trust score for document.
     */
    public function calculateTrustScore(Document $document): array
    {
        $signatureLevelWeight = match ($document->signature_level) {
            'SIMPLE' => 60,
            'ADVANCED' => 80,
            'QUALIFIED' => 100,
            default => 60,
        };

        // Check all signers have completed verifications
        $allSignersVerified = $document->signers->every(function ($signer) {
            return $this->hasCompletedVerifications($signer);
        });

        $verificationWeight = $allSignersVerified ? 100 : 50;

        // Check timestamps are present
        $timestampWeight = $document->signers->every(fn($s) => $s->signed_at) ? 100 : 0;

        // Check certificates exist
        $certificateWeight = $document->signers->every(function ($signer) {
            return $signer->certificate()->exists();
        }) ? 100 : 0;

        // Calculate weighted score
        $score = (
            ($signatureLevelWeight * 0.40) +
            ($verificationWeight * 0.30) +
            ($timestampWeight * 0.20) +
            ($certificateWeight * 0.10)
        );

        return [
            'score' => round($score, 2),
            'breakdown' => [
                'signature_level' => $signatureLevelWeight,
                'identity_verification' => $verificationWeight,
                'timestamps' => $timestampWeight,
                'certificates' => $certificateWeight,
            ],
        ];
    }

    /**
     * Update document trust score.
     */
    public function updateTrustScore(Document $document): void
    {
        $trustData = $this->calculateTrustScore($document);

        $document->update([
            'trust_score' => $trustData['score'],
            'trust_breakdown' => $trustData['breakdown'],
        ]);

        Log::info('Trust score updated', [
            'document_id' => $document->id,
            'trust_score' => $trustData['score'],
        ]);
    }

    /**
     * Validate signature level compliance.
     */
    public function validateSignatureCompliance(Document $document): array
    {
        $errors = [];

        foreach ($document->signers as $signer) {
            if (!$this->hasCompletedVerifications($signer)) {
                $missing = $this->getMissingVerifications($signer);
                $errors[] = [
                    'signer_id' => $signer->id,
                    'signer_email' => $signer->email,
                    'missing_verifications' => $missing,
                ];
            }
        }

        return $errors;
    }

    /**
     * Get signature level description.
     */
    public function getSignatureLevelDescription(string $level): array
    {
        return match ($level) {
            'SIMPLE' => [
                'name' => 'Simple Electronic Signature',
                'description' => 'Email verification only',
                'use_cases' => ['Internal documents', 'Low-risk agreements'],
                'compliance' => 'ESIGN Act compliant',
            ],
            'ADVANCED' => [
                'name' => 'Advanced Electronic Signature',
                'description' => 'Email + OTP verification',
                'use_cases' => ['Contracts', 'Business agreements', 'NDAs'],
                'compliance' => 'eIDAS Article 26 aligned',
            ],
            'QUALIFIED' => [
                'name' => 'Qualified Electronic Signature',
                'description' => 'Email + OTP + Device verification',
                'use_cases' => ['Legal documents', 'Financial agreements', 'Government forms'],
                'compliance' => 'eIDAS Article 28 aligned',
            ],
            default => [],
        };
    }
}
