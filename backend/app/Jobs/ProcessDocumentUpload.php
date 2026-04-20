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
use Illuminate\Support\Facades\Log;

class ProcessDocumentUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $document;
    protected $processingPath;
    protected $isTemplate;

    public $timeout = 300; // 5 minutes for conversion/upload

    /**
     * Create a new job instance.
     *
     * @param Document $document
     * @param string $processingPath Storage-relative path on the processing disk (e.g. processing/uuid.docx)
     * @param bool $isTemplate Whether this upload is for a template
     */
    public function __construct(Document $document, string $processingPath, bool $isTemplate = false)
    {
        $this->document = $document;
        $this->processingPath = $processingPath;
        $this->isTemplate = $isTemplate;
    }

    /**
     * Execute the job.
     */
    public function handle(DocumentConversionService $conversionService): void
    {
        try {
            // 1. Convert to PDF if needed (path is relative to processing disk)
            $conversionResult = $conversionService->convertToPdfIfNeeded($this->processingPath, 'processing');
            $processedPath = $conversionResult['path'];

            // If conversion was attempted but failed, do not upload the original file
            if (!empty($conversionResult['error'])) {
                $this->document->update(['status' => 'FAILED']);
                throw new \RuntimeException($conversionResult['error']);
            }

            $fullProcessedPath = Storage::disk('processing')->path($processedPath);
            if (!file_exists($fullProcessedPath)) {
                throw new \RuntimeException("Processed file not found: {$processedPath}");
            }

            // 2. Calculate Properties
            $hash = hash_file('sha256', $fullProcessedPath);
            $size = filesize($fullProcessedPath);
            $mime = mime_content_type($fullProcessedPath);

            // 3. Upload to MinIO
            $minioPath = 'documents/' . basename($processedPath);
            Storage::disk('minio')->putFileAs('documents', new \Illuminate\Http\File($fullProcessedPath), basename($processedPath));

            // 4. Update Document
            $this->document->update([
                'file_path' => $minioPath,
                'file_hash' => $hash,
                'size' => $size,
                'mime_type' => $mime, // application/pdf after conversion
                'status' => 'DRAFT',
            ]);

            // 5. Cleanup local files
            if (Storage::disk('processing')->exists($this->processingPath)) {
                Storage::disk('processing')->delete($this->processingPath);
            }
            if ($processedPath !== $this->processingPath && Storage::disk('processing')->exists($processedPath)) {
                Storage::disk('processing')->delete($processedPath);
            }

        } catch (\Exception $e) {
            Log::error("Document processing failed for {$this->document->id}: " . $e->getMessage());
            $this->document->update(['status' => 'FAILED']);
            throw $e;
        }
    }
}
