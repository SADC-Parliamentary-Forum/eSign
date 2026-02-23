<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocumentConversionService
{
    /** Extensions that LibreOffice can convert to PDF (office + images). */
    protected const CONVERTIBLE_EXTENSIONS = [
        'doc', 'docx',           // Word
        'xls', 'xlsx',           // Excel
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif',  // Images
    ];

    /**
     * Convert a document to PDF if needed.
     * Supports: Word (doc, docx), Excel (xls, xlsx), images (jpg, png, gif, etc.) → PDF
     *
     * @param string $filePath Path in storage
     * @param string $disk Storage disk name
     * @return array ['path' => new path, 'converted' => bool]
     */
    public function convertToPdfIfNeeded(string $filePath, string $disk = 'minio'): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            return ['path' => $filePath, 'converted' => false];
        }

        if (in_array($extension, self::CONVERTIBLE_EXTENSIONS)) {
            return $this->convertToPdfWithLibreOffice($filePath, $disk, $extension);
        }

        return ['path' => $filePath, 'converted' => false];
    }

    /**
     * Convert document or image to PDF using LibreOffice (Word, Excel, images).
     */
    protected function convertToPdfWithLibreOffice(string $filePath, string $disk, string $extension): array
    {
        try {
            $content = Storage::disk($disk)->get($filePath);
            $tempDir = sys_get_temp_dir() . '/doc_conversion_' . Str::random(8);
            mkdir($tempDir, 0755, true);

            $originalExtension = pathinfo($filePath, PATHINFO_EXTENSION);
            $tempFile = $tempDir . '/input.' . $originalExtension;
            file_put_contents($tempFile, $content);

            // LibreOffice needs a writable HOME (dconf/cache); avoid "Permission denied" in containers
            $homeDir = $tempDir . '/home';
            mkdir($homeDir, 0755, true);
            $previousHome = getenv('HOME');
            putenv('HOME=' . $homeDir);

            try {
                $command = sprintf(
                    'libreoffice --headless --convert-to pdf --outdir %s %s 2>&1',
                    escapeshellarg($tempDir),
                    escapeshellarg($tempFile)
                );
                exec($command, $output, $returnCode);
            } finally {
                putenv($previousHome !== false ? 'HOME=' . $previousHome : 'HOME');
            }

            $pdfFile = $tempDir . '/input.pdf';

            if ($returnCode !== 0 || !file_exists($pdfFile)) {
                Log::warning('LibreOffice conversion failed', [
                    'output' => implode("\n", $output),
                    'returnCode' => $returnCode,
                    'extension' => $extension
                ]);

                // Word only: try PHPWord fallback
                if (in_array($extension, ['doc', 'docx'])) {
                    $pdfContent = $this->convertWithPhpWord($tempFile);
                    if ($pdfContent) {
                        file_put_contents($pdfFile, $pdfContent);
                    } else {
                        $this->cleanup($tempDir);
                        return ['path' => $filePath, 'converted' => false, 'error' => 'Conversion failed'];
                    }
                } else {
                    $this->cleanup($tempDir);
                    return ['path' => $filePath, 'converted' => false, 'error' => 'Conversion failed'];
                }
            }

            // Read converted PDF
            $pdfContent = file_get_contents($pdfFile);

            // Store new PDF
            $newPath = 'documents/' . Str::random(40) . '.pdf';
            Storage::disk($disk)->put($newPath, $pdfContent);

            // Delete original file (replaced by PDF)
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
     * Cleanup temporary directory (recursive; LibreOffice may create subdirs e.g. home/.cache).
     */
    protected function cleanup(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->cleanup($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
