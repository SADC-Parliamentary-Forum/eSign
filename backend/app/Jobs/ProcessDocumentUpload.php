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
use Throwable;

class ProcessDocumentUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $document;
    protected $processingPath;
    protected $isTemplate;

    public $timeout = 300; // 5 minutes for conversion/upload
    public $tries = 2;
    public $backoff = [10, 30];
    public $failOnTimeout = true;

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
            Log::info('Document processing job started.', [
                'document_id' => $this->document->id,
                'processing_path' => $this->processingPath,
                'attempt' => $this->attempts(),
            ]);

            $this->updateProcessingState(15, 'converting');

            // 1. Convert to PDF if needed (path is relative to processing disk)
            $conversionResult = $conversionService->convertToPdfIfNeeded($this->processingPath, 'processing');
            $processedPath = $conversionResult['path'];

            // If conversion was attempted but failed, do not upload the original file
            if (!empty($conversionResult['error'])) {
                $this->markFailed($conversionResult['error']);
                throw new \RuntimeException($conversionResult['error']);
            }

            $fullProcessedPath = Storage::disk('processing')->path($processedPath);
            if (!file_exists($fullProcessedPath)) {
                $this->markFailed("Processed file not found: {$processedPath}");
                throw new \RuntimeException("Processed file not found: {$processedPath}");
            }

            $this->updateProcessingState(45, 'analyzing');

            // 2. Calculate Properties
            $hash = hash_file('sha256', $fullProcessedPath);
            $size = filesize($fullProcessedPath);
            $mime = mime_content_type($fullProcessedPath);

            $this->updateProcessingState(70, 'uploading');

            // 3. Upload to MinIO
            $minioPath = 'documents/' . basename($processedPath);
            Storage::disk('minio')->putFileAs('documents', new \Illuminate\Http\File($fullProcessedPath), basename($processedPath));

            $this->updateProcessingState(90, 'finalizing');

            // 4. Update Document
            $this->markReady($minioPath, $hash, $size, $mime);

            // 5. Cleanup local files
            if (Storage::disk('processing')->exists($this->processingPath)) {
                Storage::disk('processing')->delete($this->processingPath);
            }
            if ($processedPath !== $this->processingPath && Storage::disk('processing')->exists($processedPath)) {
                Storage::disk('processing')->delete($processedPath);
            }

            Log::info('Document processing job completed.', [
                'document_id' => $this->document->id,
                'processing_path' => $this->processingPath,
                'stored_path' => $minioPath,
            ]);
        } catch (Throwable $e) {
            Log::error("Document processing failed for {$this->document->id}: " . $e->getMessage(), [
                'document_id' => $this->document->id,
                'processing_path' => $this->processingPath,
                'attempt' => $this->attempts(),
                'exception' => get_class($e),
            ]);
            $this->markFailed($e->getMessage());
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Document processing permanently failed.', [
            'document_id' => $this->document->id,
            'processing_path' => $this->processingPath,
            'attempt' => $this->attempts(),
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
        ]);

        $this->markFailed(
            $exception->getMessage() ?: 'Document processing failed due to worker timeout or queue error.'
        );
    }

    protected function updateProcessingState(int $progress, string $stage): void
    {
        Log::info('Document processing stage update.', [
            'document_id' => $this->document->id,
            'processing_path' => $this->processingPath,
            'stage' => $stage,
            'progress' => $progress,
        ]);

        $this->document->update([
            'processing_progress' => $progress,
            'processing_stage' => $stage,
            'processing_error' => null,
        ]);
    }

    protected function markReady(string $minioPath, string $hash, int $size, string $mime): void
    {
        $this->document->update([
            'file_path' => $minioPath,
            'file_hash' => $hash,
            'size' => $size,
            'mime_type' => $mime,
            'status' => 'DRAFT',
            'processing_progress' => 100,
            'processing_stage' => 'ready',
            'processing_error' => null,
        ]);
    }

    protected function markFailed(string $errorMessage): void
    {
        $this->document->update([
            'status' => 'FAILED',
            'processing_progress' => 100,
            'processing_stage' => 'failed',
            'processing_error' => $errorMessage,
        ]);
    }
}
