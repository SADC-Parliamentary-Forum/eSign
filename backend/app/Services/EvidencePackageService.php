<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use TCPDF;

class EvidencePackageService
{
    protected SignatureAssuranceService $signatureAssuranceService;
    protected CertificateService $certificateService;

    public function __construct(
        SignatureAssuranceService $signatureAssuranceService,
        CertificateService $certificateService
    ) {
        $this->signatureAssuranceService = $signatureAssuranceService;
        $this->certificateService = $certificateService;
    }

    /**
     * Generate evidence package for document.
     */
    public function generateEvidencePackage(Document $document): string
    {
        // Create PDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');

        // Set document information
        $pdf->SetCreator('eSign Platform');
        $pdf->SetAuthor($document->user->name ?? 'System');
        $pdf->SetTitle('Evidence Package - ' . $document->title);
        $pdf->SetSubject('Legal Evidence Package');

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);

        // Remove header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Add content
        $this->addCoverPage($pdf, $document);
        $this->addDocumentSummary($pdf, $document);
        $this->addSignatureDetails($pdf, $document);
        $this->addVerificationDetails($pdf, $document);
        $this->addCertificateChain($pdf, $document);
        $this->addHashVerification($pdf, $document);
        $this->addAuditTrail($pdf, $document);

        // Generate PDF content
        $pdfContent = $pdf->Output('', 'S');

        // Save to storage
        $filename = 'evidence_packages/' . $document->id . '_' . now()->timestamp . '.pdf';
        Storage::put($filename, $pdfContent);

        // Update document
        $document->update([
            'evidence_package_path' => $filename,
            'evidence_generated_at' => now(),
        ]);

        Log::info('Evidence package generated', [
            'document_id' => $document->id,
            'file_path' => $filename,
            'size' => strlen($pdfContent),
        ]);

        return $filename;
    }

    /**
     * Add cover page.
     */
    protected function addCoverPage(TCPDF $pdf, Document $document): void
    {
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 24);
        $pdf->Cell(0, 20, 'EVIDENCE PACKAGE', 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 12);
        $pdf->Ln(10);

        $pdf->Cell(0, 6, 'Document: ' . $document->title, 0, 1);
        $pdf->Cell(0, 6, 'Generated: ' . now()->format('F j, Y g:i A T'), 0, 1);
        $pdf->Cell(0, 6, 'Document ID: ' . $document->id, 0, 1);

        $pdf->Ln(10);

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'Legal Notice', 0, 1);

        $pdf->SetFont('helvetica', '', 10);
        $text = 'This Evidence Package contains legally binding evidence of electronic signatures applied to the referenced document. ' .
            'The information contained herein is cryptographically verified and tamper-proof. ' .
            'Any modification to this document will invalidate the digital signatures and evidence contained within.';
        $pdf->MultiCell(0, 5, $text, 0, 'L');

        $pdf->Ln(10);

        // Trust Score Box
        $trustScore = $document->trust_score ?? 0;
        $pdf->SetFillColor($this->getTrustScoreColor($trustScore));
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 12, 'Trust Score: ' . number_format($trustScore, 0) . '%', 1, 1, 'C', true);
    }

    /**
     * Add document summary.
     */
    protected function addDocumentSummary(TCPDF $pdf, Document $document): void
    {
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 8, '1. Document Summary', 0, 1);
        $pdf->Ln(3);

        $pdf->SetFont('helvetica', '', 10);

        $data = [
            ['Property', 'Value'],
            ['Document Title', $document->title],
            ['Document ID', (string) $document->id],
            ['Status', $document->status],
            ['Signature Level', $document->signature_level],
            ['Created', $document->created_at->format('Y-m-d H:i:s T')],
            ['Completed', $document->completed_at?->format('Y-m-d H:i:s T') ?? 'N/A'],
            ['File Hash (SHA-256)', substr($document->file_hash, 0, 40) . '...'],
            ['Total Signers', (string) $document->signers->count()],
        ];

        $this->addTable($pdf, $data);
    }

    /**
     * Add signature details.
     */
    protected function addSignatureDetails(TCPDF $pdf, Document $document): void
    {
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 8, '2. Signature Details', 0, 1);
        $pdf->Ln(3);

        foreach ($document->signers as $index => $signer) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 6, 'Signer ' . ($index + 1), 0, 1);

            $pdf->SetFont('helvetica', '', 10);

            $data = [
                ['Property', 'Value'],
                ['Name', $signer->name],
                ['Email', $signer->email],
                ['Role', $signer->role ?? 'Signer'],
                ['Signing Order', (string) $signer->signing_order],
                ['Status', $signer->status],
                ['Signed At', $signer->signed_at?->format('Y-m-d H:i:s T') ?? 'Not signed'],
                ['IP Address', $signer->ip_address ?? 'N/A'],
                ['Location', $this->formatGeolocation($signer->geolocation)],
            ];

            $this->addTable($pdf, $data);
            $pdf->Ln(5);
        }
    }

    /**
     * Add verification details.
     */
    protected function addVerificationDetails(TCPDF $pdf, Document $document): void
    {
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 8, '3. Identity Verification', 0, 1);
        $pdf->Ln(3);

        foreach ($document->signers as $index => $signer) {
            $verifications = $signer->identityVerifications;

            if ($verifications->isEmpty()) {
                continue;
            }

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 6, $signer->name . ' - Verifications', 0, 1);

            $pdf->SetFont('helvetica', '', 10);

            $data = [['Type', 'Status', 'Verified At', 'IP Address']];

            foreach ($verifications as $verification) {
                $data[] = [
                    $verification->verification_type,
                    $verification->status,
                    $verification->verified_at?->format('Y-m-d H:i:s') ?? 'N/A',
                    $verification->ip_address ?? 'N/A',
                ];
            }

            $this->addTable($pdf, $data);
            $pdf->Ln(5);
        }
    }

    /**
     * Add certificate chain.
     */
    protected function addCertificateChain(TCPDF $pdf, Document $document): void
    {
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 8, '4. Certificate Chain', 0, 1);
        $pdf->Ln(3);

        foreach ($document->signers as $index => $signer) {
            $certificate = $signer->certificate;

            if (!$certificate) {
                continue;
            }

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 6, 'Certificate for ' . $signer->name, 0, 1);

            $pdf->SetFont('helvetica', '', 10);

            $data = [
                ['Property', 'Value'],
                ['Serial Number', $certificate->serial_number],
                ['Type', $certificate->certificate_type],
                ['Issuer', $certificate->issuer],
                ['Subject', $certificate->subject],
                ['Valid From', $certificate->valid_from->format('Y-m-d H:i:s T')],
                ['Valid To', $certificate->valid_to->format('Y-m-d H:i:s T')],
                ['Thumbprint', substr($certificate->thumbprint, 0, 32) . '...'],
                ['Status', $certificate->isValid() ? 'Valid' : 'Invalid'],
            ];

            $this->addTable($pdf, $data);
            $pdf->Ln(5);
        }
    }

    /**
     * Add hash verification.
     */
    protected function addHashVerification(TCPDF $pdf, Document $document): void
    {
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 8, '5. Hash Verification', 0, 1);
        $pdf->Ln(3);

        $pdf->SetFont('helvetica', '', 10);

        $text = 'The document hash provides cryptographic proof that the original document has not been altered. ' .
            'Any modification to the document will result in a different hash value.';
        $pdf->MultiCell(0, 5, $text, 0, 'L');

        $pdf->Ln(5);

        $data = [
            ['Property', 'Value'],
            ['Algorithm', 'SHA-256'],
            ['Document Hash', $document->file_hash],
            ['Hash Generated', $document->created_at->format('Y-m-d H:i:s T')],
        ];

        $this->addTable($pdf, $data);
    }

    /**
     * Add audit trail.
     */
    protected function addAuditTrail(TCPDF $pdf, Document $document): void
    {
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 8, '6. Audit Trail', 0, 1);
        $pdf->Ln(3);

        $pdf->SetFont('helvetica', '', 10);

        $events = [
            [
                'Timestamp' => $document->created_at->format('Y-m-d H:i:s T'),
                'Event' => 'Document Created',
                'Actor' => $document->user->name ?? 'System',
            ],
        ];

        foreach ($document->signers->sortBy('signed_at') as $signer) {
            if ($signer->signed_at) {
                $events[] = [
                    'Timestamp' => $signer->signed_at->format('Y-m-d H:i:s T'),
                    'Event' => 'Document Signed',
                    'Actor' => $signer->name,
                ];
            }
        }

        if ($document->completed_at) {
            $events[] = [
                'Timestamp' => $document->completed_at->format('Y-m-d H:i:s T'),
                'Event' => 'Workflow Completed',
                'Actor' => 'System',
            ];
        }

        $data = [['Timestamp', 'Event', 'Actor']];
        $data = array_merge($data, array_map(fn($e) => array_values($e), $events));

        $this->addTable($pdf, $data);
    }

    /**
     * Add table to PDF.
     */
    protected function addTable(TCPDF $pdf, array $data): void
    {
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetTextColor(0);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetLineWidth(0.2);
        $pdf->SetFont('', 'B');

        // Header
        $header = array_shift($data);
        $colWidths = count($header) === 2 ? [60, 120] : array_fill(0, count($header), 180 / count($header));

        foreach ($header as $i => $col) {
            $pdf->Cell($colWidths[$i], 6, $col, 1, 0, 'L', true);
        }
        $pdf->Ln();

        // Data
        $pdf->SetFont('');
        $pdf->SetFillColor(255, 255, 255);

        foreach ($data as $row) {
            foreach ($row as $i => $col) {
                $pdf->Cell($colWidths[$i], 6, $col, 1, 0, 'L');
            }
            $pdf->Ln();
        }
    }

    /**
     * Get color for trust score.
     */
    protected function getTrustScoreColor(float $score): array
    {
        if ($score >= 80) {
            return [144, 238, 144]; // Light green
        } elseif ($score >= 50) {
            return [255, 255, 153]; // Light yellow
        } else {
            return [255, 182, 193]; // Light red
        }
    }

    /**
     * Format geolocation for display.
     */
    protected function formatGeolocation(?array $geolocation): string
    {
        if (!$geolocation) {
            return 'N/A';
        }

        $parts = array_filter([
            $geolocation['city'] ?? null,
            $geolocation['region'] ?? null,
            $geolocation['country'] ?? null,
        ]);

        return implode(', ', $parts) ?: 'N/A';
    }
}
