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
     * @param string $localPath Storage-relative path on the local disk (e.g. processing/uuid.docx)
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
            // 1. Convert to PDF if needed (path is relative to local disk so conversion can read it)
            $conversionResult = $conversionService->convertToPdfIfNeeded($this->localPath, 'local');
            $processedPath = $conversionResult['path'];

            // If conversion was attempted but failed, do not upload the original file
            if (!empty($conversionResult['error'])) {
                $this->document->update(['status' => 'FAILED']);
                throw new \RuntimeException($conversionResult['error']);
            }

            $fullProcessedPath = Storage::disk('local')->path($processedPath);
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
            if (Storage::disk('local')->exists($this->localPath)) {
                Storage::disk('local')->delete($this->localPath);
            }
            if ($processedPath !== $this->localPath && Storage::disk('local')->exists($processedPath)) {
                Storage::disk('local')->delete($processedPath);
            }

        } catch (\Exception $e) {
            \Log::error("Document processing failed for {$this->document->id}: " . $e->getMessage());
            $this->document->update(['status' => 'FAILED']);
            throw $e;
        }
    }
}
