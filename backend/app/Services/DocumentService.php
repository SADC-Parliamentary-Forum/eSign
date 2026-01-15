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
            'status' => 'draft',
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
    public function createEvidenceBundle(Document $document)
    {
        $zipFileName = 'evidence_bundle_' . $document->id . '.zip';
        $zipPath = storage_path('app/' . $zipFileName);

        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {

            // 1. Add the Document
            $fileContent = Storage::disk('minio')->get($document->file_path);
            $zip->addFromString('document.pdf', $fileContent);

            // 2. Generate Audit Trail
            $auditLogs = \App\Models\AuditLog::where('resource_id', $document->id)
                ->orWhere('details->document_id', $document->id)
                ->with('user')
                ->orderBy('created_at', 'asc')
                ->get();

            $auditContent = "Audit Trail for Document: " . $document->title . " (" . $document->id . ")\n";
            $auditContent .= "Generated: " . now() . "\n\n";
            $auditContent .= str_pad("Time", 25) . str_pad("User", 30) . str_pad("Event", 20) . "IP Address\n";
            $auditContent .= str_repeat("-", 100) . "\n";

            foreach ($auditLogs as $log) {
                $user = $log->user ? $log->user->name : 'System';
                $auditContent .= str_pad($log->created_at, 25) .
                    str_pad($user, 30) .
                    str_pad($log->event, 20) .
                    $log->ip_address . "\n";
            }

            $zip->addFromString('audit_trail.txt', $auditContent);
            $zip->close();
        }

        return $zipPath;
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
            'status' => 'draft',
            'mime_type' => 'application/pdf',
            'size' => strlen($fileContent),
            'metadata' => $metadata,
        ]);

        // Copy template fields to document signature fields
        foreach ($template->fields as $templateField) {
            \App\Models\SignatureField::create([
                'document_id' => $document->id,
                'type' => $templateField->type,
                'page_number' => $templateField->page_number,
                'x_position' => $templateField->x_position,
                'y_position' => $templateField->y_position,
                'width' => $templateField->width,
                'height' => $templateField->height,
                'required' => $templateField->required,
            ]);
        }

        return $document;
    }
}
