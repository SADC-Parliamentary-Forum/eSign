<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class AIService
{
    protected $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * Extract text from the document
     */
    public function extractText(Document $document): string
    {
        $path = Storage::disk('minio')->path($document->file_path);

        // If it's not local (s3/minio), we might need to download temp content
        if (!file_exists($path)) {
            $content = Storage::disk('minio')->get($document->file_path);
            $pdf = $this->parser->parseContent($content);
        } else {
            $pdf = $this->parser->parseFile($path);
        }

        return $pdf->getText();
    }

    /**
     * Suggest signature locations based on keywords
     */
    public function suggestSignatureFields(Document $document): array
    {
        $text = $this->extractText($document);
        $suggestions = [];

        // Naive logic: Find "Sign Here" or "Signature" and guess page/position
        // In reality, we need X/Y coordinates which text parser implies but simplistic regex won't give easily without advanced parsing.
        // Smalot parser can give pages.

        // For MVP/Demo: We will return a "Found" status and a dummy coordinate if keyword exists.
        if (stripos($text, 'Signature') !== false || stripos($text, 'Sign Here') !== false) {
            $suggestions[] = [
                'page' => 1, // Default to page 1 for now
                'x' => 100,
                'y' => 500,
                'label' => 'Suggested Signature'
            ];
        }

        return $suggestions;
    }

    /**
     * Analyze contract for risky clauses
     */
    public function analyzeRisk(Document $document): array
    {
        $text = $this->extractText($document);
        $risks = [];

        $keywords = [
            'indemnification' => 'High Risk: Contains indemnification clause.',
            'unlimited liability' => 'Critical: Unlimited liability detected.',
            'exclusive jurisdiction' => 'Medium: Exclusive jurisdiction clause.',
            'arbitration' => 'Medium: Arbitration clause detected.',
            'auto-renewal' => 'Medium: Auto-renewal clause detected.',
        ];

        foreach ($keywords as $term => $warning) {
            if (stripos($text, $term) !== false) {
                $risks[] = [
                    'term' => $term,
                    'severity' => str_contains($warning, 'Critical') ? 'critical' : (str_contains($warning, 'High') ? 'high' : 'medium'),
                    'message' => $warning
                ];
            }
        }

        return $risks;
    }
}
