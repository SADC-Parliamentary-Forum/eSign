<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessDocumentUpload;
use App\Models\Document;
use App\Services\DocumentConversionService;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProcessDocumentUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = new Container();
        $logger = Mockery::mock();
        $logger->shouldIgnoreMissing();
        $app->instance('log', $logger);
        Facade::setFacadeApplication($app);
    }

    protected function tearDown(): void
    {
        Facade::setFacadeApplication(null);
        Mockery::close();
        parent::tearDown();
    }

    public function test_mark_ready_sets_terminal_ready_state(): void
    {
        $document = Mockery::mock(Document::class);
        $document->shouldReceive('getAttribute')->with('id')->andReturn('doc-1')->byDefault();
        $document->shouldReceive('update')
            ->once()
            ->with([
                'file_path' => 'documents/doc-1.pdf',
                'file_hash' => 'abc123',
                'size' => 2048,
                'mime_type' => 'application/pdf',
                'status' => 'DRAFT',
                'processing_progress' => 100,
                'processing_stage' => 'ready',
                'processing_error' => null,
            ]);

        $job = new ProcessDocumentUpload($document, 'processing/doc-1.docx');

        $method = new \ReflectionMethod($job, 'markReady');
        $method->setAccessible(true);
        $method->invoke($job, 'documents/doc-1.pdf', 'abc123', 2048, 'application/pdf');

        $this->assertTrue(true);
    }

    public function test_conversion_failure_marks_document_failed(): void
    {
        $document = Mockery::mock(Document::class);
        $document->shouldReceive('getAttribute')->with('id')->andReturn('doc-2')->byDefault();
        $document->shouldReceive('update')
            ->atLeast()
            ->once();

        $conversionService = Mockery::mock(DocumentConversionService::class);
        $conversionService->shouldReceive('convertToPdfIfNeeded')
            ->once()
            ->andReturn([
                'path' => 'processing/doc-2.docx',
                'converted' => false,
                'error' => 'Conversion failed',
            ]);

        $job = new ProcessDocumentUpload($document, 'processing/doc-2.docx');

        $this->expectException(RuntimeException::class);
        $job->handle($conversionService);
    }

    public function test_failed_hook_marks_terminal_failed_state_for_timeout_or_worker_errors(): void
    {
        $document = Mockery::mock(Document::class);
        $document->shouldReceive('getAttribute')->with('id')->andReturn('doc-3')->byDefault();
        $document->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function (array $payload) {
                return $payload['status'] === 'FAILED'
                    && $payload['processing_stage'] === 'failed'
                    && str_contains($payload['processing_error'], 'timed out');
            }));

        $job = new ProcessDocumentUpload($document, 'processing/doc-3.docx');
        $job->failed(new RuntimeException('Worker timed out while converting document'));

        $this->assertTrue(true);
    }
}

