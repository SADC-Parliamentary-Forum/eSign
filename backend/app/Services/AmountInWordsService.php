<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Converts numeric amounts to South African English words, verifies
 * that a signer's written word amount matches a given numeric amount,
 * and extracts numeric currency amounts from PDF documents.
 *
 * Example: 1500.50 → "One Thousand Five Hundred Rand and Fifty Cents"
 */
class AmountInWordsService
{
    private const ONES = [
        '',
        'One',
        'Two',
        'Three',
        'Four',
        'Five',
        'Six',
        'Seven',
        'Eight',
        'Nine',
        'Ten',
        'Eleven',
        'Twelve',
        'Thirteen',
        'Fourteen',
        'Fifteen',
        'Sixteen',
        'Seventeen',
        'Eighteen',
        'Nineteen',
    ];

    private const TENS = [
        '',
        '',
        'Twenty',
        'Thirty',
        'Forty',
        'Fifty',
        'Sixty',
        'Seventy',
        'Eighty',
        'Ninety',
    ];

    private const SCALE = [
        '',
        'Thousand',
        'Million',
        'Billion',
        'Trillion',
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Convert a numeric amount to its canonical English word form.
     *
     * @param  float  $amount     The numeric value (e.g. 1500.50)
     * @param  string $currency   Major currency unit name (default: Rand)
     * @param  string $cents      Minor currency unit name (default: Cents)
     * @return string             e.g. "One Thousand Five Hundred Rand and Fifty Cents"
     */
    public function toWords(float $amount, string $currency = 'Rand', string $cents = 'Cents'): string
    {
        if ($amount < 0) {
            return 'Minus ' . $this->toWords(abs($amount), $currency, $cents);
        }

        $intPart = (int) floor($amount);
        $centsPart = (int) round(($amount - $intPart) * 100);

        $words = $this->integerToWords($intPart);
        $result = ($words === '' ? 'Zero' : $words) . ' ' . $currency;

        if ($centsPart > 0) {
            $result .= ' and ' . $this->integerToWords($centsPart) . ' ' . $cents;
        } else {
            $result .= ' and Zero ' . $cents;
        }

        return $result;
    }

    /**
     * Verify that a written word amount matches the numeric amount.
     * Comparison is case-insensitive and trims extra whitespace.
     *
     * @param  float  $numericAmount   The authoritative amount from the document
     * @param  string $wordsProvided   What the signer typed
     * @param  string $currency        Major currency unit (default: Rand)
     * @param  string $cents           Minor currency unit (default: Cents)
     * @return array{match: bool, expected: string, provided: string}
     */
    public function verify(
        float $numericAmount,
        string $wordsProvided,
        string $currency = 'Rand',
        string $cents = 'Cents'
    ): array {
        $expected = $this->toWords($numericAmount, $currency, $cents);
        $provided = trim($wordsProvided);

        $match = strcasecmp($expected, $provided) === 0;

        return [
            'match' => $match,
            'expected' => $expected,
            'provided' => $provided,
        ];
    }

    /**
     * Extract the primary payment/total amount from a PDF document stored in MinIO.
     *
     * Strategy:
     *  1. Download the PDF to a temp file.
     *  2. Extract plain text via Ghostscript (gs -sDEVICE=txtwrite).
     *  3. Regex-scan the text for currency amounts using common SA patterns:
     *       R 1 500.00 / R1500.00 / ZAR 1,500.00 / 1500.00
     *  4. Prefer amounts that appear near "Total", "Amount", "Due" etc.
     *  5. Return the largest such "headline" amount found, or null if none.
     *
     * @param  Document $document
     * @return float|null  The extracted numeric amount, or null if not found
     */
    public function extractAmountFromDocument(Document $document): ?float
    {
        $filePath = $document->file_path ?? $document->original_file_path;

        if (!$filePath) {
            return null;
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('minio');

        if (!$disk->exists($filePath)) {
            Log::warning("AmountInWordsService: file not found in MinIO: {$filePath}");
            return null;
        }

        // Download to a temp file
        $tempPdf = tempnam(sys_get_temp_dir(), 'esign_pdf_') . '.pdf';
        $tempTxt = tempnam(sys_get_temp_dir(), 'esign_txt_') . '.txt';

        try {
            file_put_contents($tempPdf, $disk->get($filePath));

            // Attempt extraction with pdftotext (poppler) first — best quality
            $text = $this->extractTextWithPdfToText($tempPdf, $tempTxt);

            // Fallback to Ghostscript if pdftotext is unavailable
            if ($text === null) {
                $text = $this->extractTextWithGhostscript($tempPdf, $tempTxt);
            }

            if ($text === null || trim($text) === '') {
                Log::warning("AmountInWordsService: could not extract text from PDF {$filePath}");
                return null;
            }

            return $this->findPrimaryAmount($text);
        } catch (\Throwable $e) {
            Log::error("AmountInWordsService: PDF amount extraction failed: " . $e->getMessage());
            return null;
        } finally {
            @unlink($tempPdf);
            @unlink($tempTxt);
        }
    }

    // -------------------------------------------------------------------------
    // PDF Text Extraction
    // -------------------------------------------------------------------------

    private function extractTextWithPdfToText(string $pdfPath, string $txtPath): ?string
    {
        $process = new Process(['pdftotext', '-layout', $pdfPath, $txtPath]);
        $process->setTimeout(30);
        $process->run();

        if ($process->isSuccessful() && file_exists($txtPath)) {
            return file_get_contents($txtPath) ?: null;
        }

        return null;
    }

    private function extractTextWithGhostscript(string $pdfPath, string $txtPath): ?string
    {
        $process = new Process([
            'gs',
            '-sDEVICE=txtwrite',
            '-dNOPAUSE',
            '-dQUIET',
            '-dBATCH',
            '-sOutputFile=' . $txtPath,
            $pdfPath,
        ]);
        $process->setTimeout(30);
        $process->run();

        if ($process->isSuccessful() && file_exists($txtPath)) {
            return file_get_contents($txtPath) ?: null;
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Amount Detection
    // -------------------------------------------------------------------------

    /**
     * Scan extracted PDF text and return the most likely "total/payment" amount.
     *
     * Matches patterns like:
     *   R 1 500.00 | R1500 | ZAR 1,500.00 | 1 500,00 | 1500.00
     *
     * Prefers amounts on lines that contain keywords like Total, Amount, Due, Pay.
     * Falls back to the largest monetary amount found anywhere in the document.
     */
    private function findPrimaryAmount(string $text): ?float
    {
        // currency pattern: optional prefix (R / ZAR / USD / $), number with
        // optional thousands separators (space / comma) and decimal part
        $currencyPattern = '/(?:(?:R|ZAR|USD|\$)\s*)?(\d{1,3}(?:[\s,]\d{3})*(?:[.,]\d{2})?|\d+(?:[.,]\d{2})?)/';

        // Keywords that indicate a "headline" amount line
        $keywordPattern = '/\b(total|amount\s+due|amount\s+payable|payment\s+amount|net\s+amount|grand\s+total|invoice\s+total|sum\s+due|sum\s+payable|subtotal|sub-total)\b/i';

        $lines = explode("\n", $text);

        $keywordAmounts = [];
        $allAmounts = [];

        foreach ($lines as $line) {
            $lineAmounts = [];
            if (preg_match_all($currencyPattern, $line, $matches)) {
                foreach ($matches[1] as $raw) {
                    $value = $this->normaliseNumber($raw);
                    if ($value !== null && $value >= 1.00) {
                        $lineAmounts[] = $value;
                        $allAmounts[] = $value;
                    }
                }
            }

            // If line has a keyword, collect its amounts as high-priority
            if ($lineAmounts && preg_match($keywordPattern, $line)) {
                foreach ($lineAmounts as $v) {
                    $keywordAmounts[] = $v;
                }
            }
        }

        // Prefer the largest amount on a keyword line; fall back to largest overall
        if ($keywordAmounts) {
            return max($keywordAmounts);
        }

        if ($allAmounts) {
            return max($allAmounts);
        }

        return null;
    }

    /**
     * Normalise a raw number string to a float.
     * Handles: "1 500.00", "1,500.00", "1500,00" (SA comma-decimal)
     */
    private function normaliseNumber(string $raw): ?float
    {
        $raw = trim($raw);

        // Remove thousands separators (space or comma when followed by 3 digits)
        // then treat remaining comma as decimal point (SA style)
        // Detect SA comma-decimal style: digits,2digits at end
        if (preg_match('/^[\d\s]+,\d{2}$/', $raw)) {
            $raw = str_replace([' ', ','], ['', '.'], $raw);
        } else {
            // Standard: remove spaces and commas used as thousands separators
            $raw = str_replace([' ', ','], '', $raw);
        }

        if (!is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    // -------------------------------------------------------------------------
    // Integer-to-Words helpers
    // -------------------------------------------------------------------------

    private function integerToWords(int $number): string
    {
        if ($number === 0) {
            return '';
        }

        if ($number < 0) {
            return 'Minus ' . $this->integerToWords(abs($number));
        }

        if ($number < 20) {
            return self::ONES[$number];
        }

        if ($number < 100) {
            $tens = self::TENS[(int) ($number / 10)];
            $ones = self::ONES[$number % 10];
            return $ones ? $tens . '-' . $ones : $tens;
        }

        if ($number < 1000) {
            $hundreds = self::ONES[(int) ($number / 100)] . ' Hundred';
            $remainder = $number % 100;
            return $remainder ? $hundreds . ' ' . $this->integerToWords($remainder) : $hundreds;
        }

        // 1,000 and above — group into triplets
        $groups = [];
        $n = $number;
        while ($n > 0) {
            $groups[] = $n % 1000;
            $n = (int) ($n / 1000);
        }

        $parts = [];
        foreach (array_reverse($groups) as $idx => $group) {
            $scaleLabel = self::SCALE[count($groups) - 1 - $idx];
            if ($group !== 0) {
                $part = $this->integerToWords($group);
                $parts[] = $scaleLabel ? $part . ' ' . $scaleLabel : $part;
            }
        }

        return implode(' ', $parts);
    }
}
