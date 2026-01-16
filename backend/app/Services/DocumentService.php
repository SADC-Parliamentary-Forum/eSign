<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\Document;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DocumentService
{
    /**
     * Handle document upload, conversion and storage
     */
    public function upload(UploadedFile $file, $user, array $metadata = []): Document
    {
        // 1. Generate Temp Path
        $ext = $file->getClientOriginalExtension();
        $tempPath = $file->storeAs('temp', Str::uuid() . '.' . $ext);
        $fullTempPath = Storage::path($tempPath);

        // 2. Convert to PDF/A if needed
        $finalPath = $fullTempPath;
        $mimeType = $file->getClientMimeType();

        if (in_array($ext, ['doc', 'docx'])) {
            $finalPath = $this->convertToPdf($fullTempPath);
            $mimeType = 'application/pdf';
        }

        // 3. Calculate Hash
        $hash = hash_file('sha256', $finalPath);

        // 4. Store in MinIO
        $storagePath = 'documents/' . date('Y/m') . '/' . $hash . '.pdf';
        Storage::disk('minio')->put($storagePath, file_get_contents($finalPath));

        // 5. Create Record
        $document = Document::create([
            'user_id' => $user->id,
            'title' => $metadata['title'] ?? $file->getClientOriginalName(),
            'file_path' => $storagePath,
            'file_hash' => $hash,
            'status' => 'DRAFT',
            'mime_type' => $mimeType,
            'size' => filesize($finalPath),
            'metadata' => $metadata,
        ]);

        // Cleanup temp
        Storage::delete($tempPath);
        if ($finalPath !== $fullTempPath) {
            @unlink($finalPath);
        }

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
        return Storage::disk('minio')->url($document->file_path);
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
            file_put_contents($tempDir . '/document.pdf', $documentContent);

            // 2. Generate Audit Trail PDF
            $pdfContent = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.audit_trail', [
                'document' => $document->load(['user', 'signers', 'workflowLogs'])
            ])->output();
            file_put_contents($tempDir . '/audit_trail.pdf', $pdfContent);

            // 3. Create ZIP
            $zipPath = $tempDir . '/' . $zipFileName;
            $zip = new \ZipArchive;
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                $zip->addFile($tempDir . '/document.pdf', 'Signed_Document.pdf');
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
}
