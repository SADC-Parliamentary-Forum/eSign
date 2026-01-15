<?php

namespace App\Services;

use App\Models\Template;
use Illuminate\Support\Facades\Log;

class AITemplateMatchingService
{
    /**
     * Analyze document and extract fingerprint.
     */
    public function analyzeDocument(string $filePath): array
    {
        // In production, this would use PDF analysis libraries to:
        // 1. Extract page dimensions and count
        // 2. Identify text blocks (with hashed content for privacy)
        // 3. Calculate layout geometry
        // 4. Detect signature field density

        // Simplified version
        $hash = hash_file('sha256', $filePath);
        $fileSize = filesize($filePath);

        return [
            'fingerprint' => $hash,
            'file_size' => $fileSize,
            'estimated_pages' => max(1, intval($fileSize / 50000)), // Rough estimate
            'analyzed_at' => now()->toISOString(),
        ];
    }

    /**
     * Suggest matching templates for a document.
     */
    public function suggestTemplates(string $filePath): array
    {
        $documentAnalysis = $this->analyzeDocument($filePath);
        $documentFingerprint = $documentAnalysis['fingerprint'];

        // Get all active templates
        $templates = Template::active()->get();

        $suggestions = [];

        foreach ($templates as $template) {
            if (!$template->document_fingerprint) {
                continue;
            }

            $confidence = $this->calculateConfidence(
                $documentFingerprint,
                $template->document_fingerprint
            );

            // Only include templates with confidence >= 70%
            if ($confidence >= 70) {
                $suggestions[] = [
                    'template' => $template,
                    'confidence' => $confidence,
                    'strength' => $this->getConfidenceStrength($confidence),
                ];
            }
        }

        // Sort by confidence descending
        usort($suggestions, function ($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        Log::info('Template suggestions generated', [
            'document_fingerprint' => substr($documentFingerprint, 0, 16),
            'suggestions_count' => count($suggestions)
        ]);

        return $suggestions;
    }

    /**
     * Calculate confidence score between two fingerprints.
     * In production, this would use more sophisticated similarity algorithms.
     */
    protected function calculateConfidence(string $docFingerprint, string $templateFingerprint): float
    {
        // Exact match = 100%
        if ($docFingerprint === $templateFingerprint) {
            return 100.0;
        }

        // Simplified similarity calculation
        // In production, use Levenshtein, Jaccard, or custom PDF structure comparison
        $similarity = similar_text($docFingerprint, $templateFingerprint, $percent);

        return round($percent, 2);
    }

    /**
     * Get confidence strength label.
     */
    protected function getConfidenceStrength(float $confidence): string
    {
        if ($confidence >= 90) {
            return 'STRONG'; // Auto-suggest with high confidence
        }

        if ($confidence >= 70) {
            return 'MODERATE'; // Suggest with preview
        }

        return 'WEAK'; // Don't suggest
    }

    /**
     * Get best matching template.
     */
    public function getBestMatch(string $filePath): ?array
    {
        $suggestions = $this->suggestTemplates($filePath);

        if (empty($suggestions)) {
            return null;
        }

        // Return first suggestion (highest confidence)
        return $suggestions[0];
    }

    /**
     * Validate template applicability for document.
     */
    public function validateTemplateForDocument(Template $template, string $filePath): array
    {
        $documentAnalysis = $this->analyzeDocument($filePath);
        $confidence = $this->calculateConfidence(
            $documentAnalysis['fingerprint'],
            $template->document_fingerprint ?? ''
        );

        $isApplicable = $confidence >= 70;
        $autoApply = $confidence >= 90;

        return [
            'applicable' => $isApplicable,
            'auto_apply' => $autoApply,
            'confidence' => $confidence,
            'strength' => $this->getConfidenceStrength($confidence),
            'reasons' => $this->getApplicabilityReasons($confidence),
        ];
    }

    /**
     * Get reasons for applicability decision.
     */
    protected function getApplicabilityReasons(float $confidence): array
    {
        $reasons = [];

        if ($confidence >= 90) {
            $reasons[] = 'Document structure matches template with high confidence';
            $reasons[] = 'Automatic field mapping recommended';
        } elseif ($confidence >= 70) {
            $reasons[] = 'Document structure partially matches template';
            $reasons[] = 'Manual review of field mappings recommended';
        } else {
            $reasons[] = 'Document structure does not match template';
            $reasons[] = 'Template not recommended for this document';
        }

        return $reasons;
    }
}
