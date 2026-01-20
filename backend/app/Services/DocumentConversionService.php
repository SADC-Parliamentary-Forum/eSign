<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocumentConversionService
{
    /**
     * Convert a document to PDF if needed.
     * Supports: doc, docx → pdf
     * 
     * @param string $filePath Path in storage
     * @param string $disk Storage disk name
     * @return array ['path' => new path, 'converted' => bool]
     */
    public function convertToPdfIfNeeded(string $filePath, string $disk = 'minio'): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Already a PDF
        if ($extension === 'pdf') {
            return ['path' => $filePath, 'converted' => false];
        }

        // Word documents
        if (in_array($extension, ['doc', 'docx'])) {
            return $this->convertWordToPdf($filePath, $disk);
        }

        // Unknown format, return as-is
        return ['path' => $filePath, 'converted' => false];
    }

    /**
     * Convert Word document to PDF using LibreOffice.
     */
    protected function convertWordToPdf(string $filePath, string $disk): array
    {
        try {
            // Download file from storage to temp
            $content = Storage::disk($disk)->get($filePath);
            $tempDir = sys_get_temp_dir() . '/doc_conversion_' . Str::random(8);
            mkdir($tempDir, 0755, true);

            $originalExtension = pathinfo($filePath, PATHINFO_EXTENSION);
            $tempFile = $tempDir . '/input.' . $originalExtension;
            file_put_contents($tempFile, $content);

            // Use LibreOffice for conversion
            // This requires LibreOffice to be installed in the Docker container
            $command = sprintf(
                'libreoffice --headless --convert-to pdf --outdir %s %s 2>&1',
                escapeshellarg($tempDir),
                escapeshellarg($tempFile)
            );

            exec($command, $output, $returnCode);

            $pdfFile = $tempDir . '/input.pdf';

            if ($returnCode !== 0 || !file_exists($pdfFile)) {
                // LibreOffice not available or conversion failed
                // Try alternative: use PHPWord + TCPDF (fallback)
                Log::warning('LibreOffice conversion failed, trying PHPWord fallback', [
                    'output' => implode("\n", $output),
                    'returnCode' => $returnCode
                ]);

                $pdfContent = $this->convertWithPhpWord($tempFile);
                if ($pdfContent) {
                    file_put_contents($pdfFile, $pdfContent);
                } else {
                    // Cleanup and return original
                    $this->cleanup($tempDir);
                    return ['path' => $filePath, 'converted' => false, 'error' => 'Conversion failed'];
                }
            }

            // Read converted PDF
            $pdfContent = file_get_contents($pdfFile);

            // Store new PDF
            $newPath = 'documents/' . Str::random(40) . '.pdf';
            Storage::disk($disk)->put($newPath, $pdfContent);

            // Delete original Word file
            Storage::disk($disk)->delete($filePath);

            // Cleanup temp
            $this->cleanup($tempDir);

            Log::info('Document converted to PDF', [
                'original' => $filePath,
                'new' => $newPath
            ]);

            return ['path' => $newPath, 'converted' => true];

        } catch (\Exception $e) {
            Log::error('Document conversion error', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);

            return ['path' => $filePath, 'converted' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fallback conversion using PHPWord (limited support).
     * 
     * @suppressWarnings(PHPMD.StaticAccess)
     */
    protected function convertWithPhpWord(string $filePath): ?string
    {
        try {
            // Check if PHPWord is available (optional dependency)
            $ioFactoryClass = 'PhpOffice\\PhpWord\\IOFactory';
            if (!class_exists($ioFactoryClass)) {
                Log::info('PHPWord not installed, skipping fallback conversion');
                return null;
            }

            // Use dynamic class instantiation to avoid IDE warnings
            /** @var mixed $phpWord */
            $phpWord = $ioFactoryClass::load($filePath);

            // Create PDF writer
            /** @var mixed $pdfWriter */
            $pdfWriter = $ioFactoryClass::createWriter($phpWord, 'PDF');

            $tempPdf = sys_get_temp_dir() . '/' . Str::random(16) . '.pdf';
            $pdfWriter->save($tempPdf);

            $content = file_get_contents($tempPdf);
            unlink($tempPdf);

            return $content;

        } catch (\Exception $e) {
            Log::warning('PHPWord conversion failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Cleanup temporary directory.
     */
    protected function cleanup(string $dir): void
    {
        if (!is_dir($dir))
            return;

        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($dir);
    }
}
