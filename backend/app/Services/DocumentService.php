<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\Document;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use setasign\Fpdi\Fpdi;

class DocumentService
{
    /**
     * Overlay signatures onto the PDF.
     */
    /**
     * Overlay signatures onto the PDF.
     */
    protected function applySignaturesToPdf(Document $document, string $inputPath, string $outputPath)
    {
        // 1. Normalize PDF using Ghostscript (fix compression/version issues)
        $normalizedPath = $inputPath . '_normalized.pdf';

        // gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dNOPAUSE -dQUIET -dBATCH -sOutputFile=output.pdf input.pdf
        try {
            $process = new Process([
                'gs',
                '-sDEVICE=pdfwrite',
                '-dCompatibilityLevel=1.4',
                '-dNOPAUSE',
                '-dQUIET',
                '-dBATCH',
                '-sOutputFile=' . $normalizedPath,
                $inputPath
            ]);
            $process->mustRun();
            // Use normalized PDF if successful
            $sourcePath = $normalizedPath;
        } catch (\Exception $e) {
            // Fallback to original if GS fails
            \Illuminate\Support\Facades\Log::warning('Ghostscript normalization failed: ' . $e->getMessage());
            $sourcePath = $inputPath;
        }

        try {
            // Initialize FPDI with Points as unit (compatible with standard PDF coords)
            $pdf = new Fpdi('P', 'pt');

            $pageCount = $pdf->setSourceFile($sourcePath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                // Add page with same size/orientation
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);

                // Find fields for this page
                $fields = $document->fields()
                    ->where('page_number', $pageNo)
                    ->with('signature')
                    ->get();

                foreach ($fields as $field) {
                    // Calculate coordinates in Points
                    $pageWidth = $size['width'];
                    $pageHeight = $size['height'];

                    $x = ($field->x / 100) * $pageWidth;
                    $y = ($field->y / 100) * $pageHeight;
                    $w = ($field->width / 100) * $pageWidth;
                    $h = ($field->height / 100) * $pageHeight;

                    // Handle Signature/Initials (Images)
                    if (($field->type === 'SIGNATURE' || $field->type === 'INITIALS') && $field->signature && $field->signature->signature_data) {
                        try {
                            $data = $field->signature->signature_data;

                            // Parse Base64
                            if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
                                $data = substr($data, strpos($data, ',') + 1);
                                $extension = strtolower($type[1]);
                                $data = base64_decode($data);

                                if ($data === false) {
                                    \Illuminate\Support\Facades\Log::error("Base64 decode failed for Field {$field->id}");
                                    continue;
                                }

                                $tempImg = tempnam(sys_get_temp_dir(), 'sig');
                                file_put_contents($tempImg, $data);

                                $pdf->Image($tempImg, $x, $y, $w, $h, $extension);

                                @unlink($tempImg);
                            } else {
                                \Illuminate\Support\Facades\Log::warning("Invalid image data format for Field {$field->id}");
                            }
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::warning('Failed to stamp signature ' . $field->id . ': ' . $e->getMessage());
                        }
                    }
                    // Handle Text/Date
                    elseif (in_array($field->type, ['TEXT', 'DATE']) && $field->text_value) {
                        try {
                            $pdf->SetFont('Helvetica', '', 11);
                            $pdf->SetXY($x, $y);
                            // MultiCell to handle width wrapping
                            $pdf->MultiCell($w, $h, $field->text_value, 0, 'L');
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::warning('Failed to stamp text field ' . $field->id . ': ' . $e->getMessage());
                        }
                    }
                    // Handle Checkbox
                    elseif ($field->type === 'CHECKBOX' && $field->text_value === 'true') {
                        try {
                            $pdf->SetFont('Helvetica', 'B', 14);
                            $pdf->SetXY($x, $y);
                            $pdf->Cell($w, $h, 'X', 0, 0, 'C');
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::warning('Failed to stamp checkbox ' . $field->id . ': ' . $e->getMessage());
                        }
                    }
                }
            }

            $pdf->Output($outputPath, 'F');

        } catch (\Exception $e) {
            // If FPDI fails (e.g. still compression issue), copy source to output so we at least return the doc
            \Illuminate\Support\Facades\Log::error('FPDI Stamping failed: ' . $e->getMessage());
            copy($inputPath, $outputPath);
        } finally {
            if (file_exists($normalizedPath))
                @unlink($normalizedPath);
        }
    }

    /**
     * Handle document upload, conversion and storage
     */
    public function upload(UploadedFile $file, $user, array $metadata = []): Document
    {
        // 1. Move to Processing Storage (Persistent vs UploadedFile which is tmp)
        $ext = $file->getClientOriginalExtension();
        $processingPath = 'processing/' . Str::uuid() . '.' . $ext;

        // Ensure processing directory exists (local disk root is storage/app/private)
        Storage::disk('local')->makeDirectory('processing');

        // Store in restricted local disk (e.g. storage/app/private/processing)
        Storage::disk('local')->putFileAs('processing', $file, basename($processingPath));

        // 2. Create Record with PROCESSING status
        $document = Document::create([
            'user_id' => $user->id,
            'title' => $metadata['title'] ?? $file->getClientOriginalName(),
            'file_path' => null, // Placeholder until processed
            'file_hash' => null,
            'status' => 'IN_PROGRESS',
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'metadata' => $metadata,
        ]);

        // 3. Dispatch Job (pass storage-relative path so conversion can read from local disk)
        \App\Jobs\ProcessDocumentUpload::dispatch($document, $processingPath);

        return $document;
    }

    protected function convertToPdf($inputPath)
    {
        $outputDir = dirname($inputPath);
        $process = new Process([
            'libreoffice',
            '--headless',
            '--convert-to',
            'pdf',
            '--outdir',
            $outputDir,
            $inputPath
        ]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $filename = pathinfo($inputPath, PATHINFO_FILENAME);
        return $outputDir . '/' . $filename . '.pdf';
    }

    public function getFileUrl(Document $document)
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('minio');
        return $disk->url($document->file_path);
    }

    /**
     * Generate a ZIP bundle containing the Signed Document and Audit Trail
     */
    /**
     * Generate a ZIP bundle containing the Signed Document and Audit Trail
     */
    public function createEvidenceBundle(Document $document)
    {
        $zipFileName = 'evidence_' . $document->id . '.zip';
        $tempDir = storage_path('app/temp/' . Str::uuid());
        mkdir($tempDir, 0755, true);

        try {
            // 1. Get the Signed Document (or current document file)
            $documentContent = Storage::disk('minio')->get($document->file_path);
            $inputPdfPath = $tempDir . '/original.pdf';
            file_put_contents($inputPdfPath, $documentContent);

            // 1b. Apply Signatures (Stamping)
            $stampedPdfPath = $tempDir . '/document.pdf';
            $this->applySignaturesToPdf($document, $inputPdfPath, $stampedPdfPath);

            // 2. Generate Audit Trail PDF
            $pdfContent = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.audit_trail', [
                'document' => $document->load(['user', 'signers', 'workflowLogs'])
            ])->output();
            file_put_contents($tempDir . '/audit_trail.pdf', $pdfContent);

            // 3. Create ZIP
            $zipPath = $tempDir . '/' . $zipFileName;
            $zip = new \ZipArchive;
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                $baseName = pathinfo($document->title, PATHINFO_FILENAME);
                $signedName = $baseName . ' signed.pdf';

                $zip->addFile($tempDir . '/document.pdf', $signedName);
                $zip->addFile($tempDir . '/audit_trail.pdf', 'Audit_Trail.pdf');
                $zip->close();
            }

            // 4. Store ZIP in MinIO
            $storagePath = 'evidence/' . date('Y/m') . '/' . $zipFileName;
            Storage::disk('minio')->put($storagePath, file_get_contents($zipPath));

            return $storagePath;

        } finally {
            // Cleanup
            @unlink($tempDir . '/document.pdf');
            @unlink($tempDir . '/audit_trail.pdf');
            @unlink($tempDir . '/' . $zipFileName);
            @rmdir($tempDir);
        }
    }

    /**
     * Create a document from a template
     */
    public function createFromTemplate($template, $user, array $metadata = []): Document
    {
        // Copy template file to documents folder
        $templatePath = $template->file_path;
        $newPath = 'documents/' . date('Y/m') . '/' . Str::uuid() . '.pdf';

        // Copy from template location to document location
        Storage::disk('minio')->copy($templatePath, $newPath);

        // Get hash of the copied file
        $fileContent = Storage::disk('minio')->get($newPath);
        $hash = hash('sha256', $fileContent);

        // Create document record
        $document = Document::create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'title' => $metadata['title'] ?? $template->name,
            'file_path' => $newPath,
            'file_hash' => $hash,
            'status' => 'DRAFT',
            'mime_type' => 'application/pdf',
            'size' => strlen($fileContent),
            'metadata' => $metadata,
            'signature_level' => $template->required_signature_level ?? ($metadata['signature_level'] ?? 'SIMPLE'),
        ]);

        // Copy template fields to document fields
        foreach ($template->fields as $templateField) {
            \App\Models\DocumentField::create([
                'document_id' => $document->id,
                'type' => $templateField->type,
                'page_number' => $templateField->page_number,
                'x_position' => $templateField->x_position,
                'y_position' => $templateField->y_position,
                'width' => $templateField->width,
                'height' => $templateField->height,
                'required' => $templateField->required,
                'signer_role' => $templateField->signer_role,
            ]);
        }

        return $document;
    }
    /**
     * Finalize the document by stamping signatures and updating the file.
     */
    public function finalizeDocument(Document $document)
    {
        $tempDir = storage_path('app/temp/' . Str::uuid());
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        try {
            // 1. Download current file
            $content = Storage::disk('minio')->get($document->file_path);
            $inputPath = $tempDir . '/original.pdf';
            file_put_contents($inputPath, $content);

            // 2. Stamp signatures
            $outputPath = $tempDir . '/final.pdf';
            $this->applySignaturesToPdf($document, $inputPath, $outputPath);

            // 3. Update in MinIO
            $finalContent = file_get_contents($outputPath);
            Storage::disk('minio')->put($document->file_path, $finalContent);

            // 4. Update Document Metadata
            $document->update([
                'file_hash' => hash('sha256', $finalContent),
                'size' => strlen($finalContent),
            ]);

        } finally {
            // Cleanup
            @unlink($inputPath);
            @unlink($outputPath);
            @rmdir($tempDir);
        }
    }

    /**
     * Generate audit trail PDF content.
     */
    public function generateAuditTrailPdf(Document $document): string
    {
        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.audit_trail', [
            'document' => $document->load(['user', 'signers', 'workflowLogs'])
        ])->output();
    }

    /**
     * Get the signed PDF content (with signatures stamped).
     */
    public function getSignedPdfContent(Document $document): string
    {
        // For completed documents, the file is already stamped
        return Storage::disk('minio')->get($document->file_path);
    }
}
