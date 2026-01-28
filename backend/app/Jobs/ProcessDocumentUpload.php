<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentConversionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessDocumentUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $document;
    protected $localPath;
    protected $isTemplate;

    public $timeout = 300; // 5 minutes for conversion/upload

    /**
     * Create a new job instance.
     *
     * @param Document $document
     * @param string $localPath Path to the temporary local file
     * @param bool $isTemplate Whether this upload is for a template
     */
    public function __construct(Document $document, string $localPath, bool $isTemplate = false)
    {
        $this->document = $document;
        $this->localPath = $localPath;
        $this->isTemplate = $isTemplate;
    }

    /**
     * Execute the job.
     */
    public function handle(DocumentConversionService $conversionService): void
    {
        try {
            // 1. Convert to PDF if needed
            $conversionResult = $conversionService->convertToPdfIfNeeded($this->localPath, 'local');
            $processedPath = $conversionResult['path'];

            // 2. Calculate Properties
            $hash = hash_file('sha256', $processedPath);
            $size = filesize($processedPath);
            $mime = mime_content_type($processedPath);

            // 3. Upload to MinIO
            $minioPath = 'documents/' . basename($processedPath);
            Storage::disk('minio')->putFileAs('documents', new \Illuminate\Http\File($processedPath), basename($processedPath));

            // 4. Update Document
            $this->document->update([
                'file_path' => $minioPath,
                'file_hash' => $hash,
                'size' => $size,
                'mime_type' => $mime, // Should be application/pdf after conversion
                'status' => 'DRAFT', // Ready for use
            ]);

            // 5. Cleanup
            if (file_exists($this->localPath))
                unlink($this->localPath);
            if ($processedPath !== $this->localPath && file_exists($processedPath))
                unlink($processedPath);

        } catch (\Exception $e) {
            \Log::error("Document processing failed for {$this->document->id}: " . $e->getMessage());
            $this->document->update(['status' => 'FAILED']); // Add FAILED status handling in frontend
            throw $e;
        }
    }
}
