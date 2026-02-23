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

        // Excel documents
        if (in_array($extension, ['xls', 'xlsx'])) {
            return $this->convertExcelToPdf($filePath, $disk);
        }

        // Image files
        if (in_array($extension, ['png', 'jpg', 'jpeg'])) {
            return $this->convertImageToPdf($filePath, $disk);
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
            // Safe Mode and Custom UserInstallation are critical for Docker (www-data user)
            $command = sprintf(
                'libreoffice --headless --convert-to pdf --outdir %s -env:UserInstallation=file://%s %s 2>&1',
                escapeshellarg($tempDir),
                escapeshellarg($tempDir . '/soffice_config'),
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
                'new' => $newPath,
                'size' => strlen($pdfContent)
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
            $settingsClass = 'PhpOffice\\PhpWord\\Settings';

            if (!class_exists($ioFactoryClass)) {
                Log::info('PHPWord not installed, skipping fallback conversion');
                return null;
            }

            // Configure PDF Renderer
            // Check for TCPDF (primary) or DomPDF (secondary)
            $tcpdfPath = base_path('vendor/tecnickcom/tcpdf');
            $dompdfPath = base_path('vendor/dompdf/dompdf');

            if (file_exists($tcpdfPath)) {
                $settingsClass::setPdfRendererName($settingsClass::PDF_RENDERER_TCPDF);
                $settingsClass::setPdfRendererPath($tcpdfPath);
            } elseif (file_exists($dompdfPath)) {
                $settingsClass::setPdfRendererName($settingsClass::PDF_RENDERER_DOMPDF);
                $settingsClass::setPdfRendererPath($dompdfPath);
            } else {
                Log::warning('No compatible PDF renderer found for PHPWord (TCPDF or DomPDF required)');
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

            if (file_exists($tempPdf)) {
                $content = file_get_contents($tempPdf);
                unlink($tempPdf);
                return $content;
            }

            return null;

        } catch (\Exception $e) {
            Log::warning('PHPWord conversion failed', ['error' => $e->getMessage()]);
            return null;
        }
    }


    /**
     * Convert Image to PDF using TCPDF.
     */
    protected function convertImageToPdf(string $filePath, string $disk): array
    {
        try {
            $content = Storage::disk($disk)->get($filePath);
            $tempDir = sys_get_temp_dir() . '/img_conversion_' . Str::random(8);
            mkdir($tempDir, 0755, true);

            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            $tempFile = $tempDir . '/input.' . $extension;
            file_put_contents($tempFile, $content);

            $pdfFile = $tempDir . '/output.pdf';

            // Create PDF
            $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetHeaderMargin(0);
            $pdf->SetFooterMargin(0);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(FALSE, 0);
            $pdf->AddPage();

            // Get image dimensions to fit page
            list($width, $height) = getimagesize($tempFile);
            $pageWidth = $pdf->getPageWidth();
            $pageHeight = $pdf->getPageHeight();

            // Simple scaling to fit width
            $pdf->Image($tempFile, 0, 0, $pageWidth, 0, '', '', '', false, 300, '', false, false, 0);

            $pdf->Output($pdfFile, 'F');

            // Store new PDF
            $pdfContent = file_get_contents($pdfFile);
            $newPath = 'documents/' . Str::random(40) . '.pdf';
            Storage::disk($disk)->put($newPath, $pdfContent);
            Storage::disk($disk)->delete($filePath);

            $this->cleanup($tempDir);

            Log::info('Image converted to PDF', ['original' => $filePath, 'new' => $newPath]);

            return ['path' => $newPath, 'converted' => true];

        } catch (\Exception $e) {
            Log::error('Image conversion error', ['error' => $e->getMessage()]);
            return ['path' => $filePath, 'converted' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Convert Excel to PDF using LibreOffice or PhpSpreadsheet fallback.
     */
    protected function convertExcelToPdf(string $filePath, string $disk): array
    {
        try {
            $content = Storage::disk($disk)->get($filePath);
            $tempDir = sys_get_temp_dir() . '/xls_conversion_' . Str::random(8);
            mkdir($tempDir, 0755, true);

            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            $tempFile = $tempDir . '/input.' . $extension;
            file_put_contents($tempFile, $content);

            // 1. Try LibreOffice
            $command = sprintf(
                'libreoffice --headless --convert-to pdf --outdir %s -env:UserInstallation=file://%s %s 2>&1',
                escapeshellarg($tempDir),
                escapeshellarg($tempDir . '/soffice_config'),
                escapeshellarg($tempFile)
            );
            exec($command, $output, $returnCode);

            $pdfFile = $tempDir . '/input.pdf';

            if ($returnCode !== 0 || !file_exists($pdfFile)) {
                Log::warning('LibreOffice Excel conversion failed, trying PhpSpreadsheet fallback', [
                    'output' => implode("\n", $output)
                ]);

                // 2. Fallback: PhpSpreadsheet
                $pdfContent = $this->convertWithPhpSpreadsheet($tempFile);
                if ($pdfContent) {
                    file_put_contents($pdfFile, $pdfContent);
                } else {
                    $this->cleanup($tempDir);
                    return ['path' => $filePath, 'converted' => false, 'error' => 'Conversion failed'];
                }
            }

            // Store PDF
            $pdfContent = file_get_contents($pdfFile);
            $newPath = 'documents/' . Str::random(40) . '.pdf';
            Storage::disk($disk)->put($newPath, $pdfContent);
            Storage::disk($disk)->delete($filePath);

            $this->cleanup($tempDir);

            return ['path' => $newPath, 'converted' => true];

        } catch (\Exception $e) {
            Log::error('Excel conversion error', ['error' => $e->getMessage()]);
            return ['path' => $filePath, 'converted' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fallback conversion using PhpSpreadsheet.
     */
    protected function convertWithPhpSpreadsheet(string $filePath): ?string
    {
        try {
            $class = 'PhpOffice\\PhpSpreadsheet\\IOFactory';
            $settingsClass = 'PhpOffice\\PhpSpreadsheet\\Settings';

            if (!class_exists($class)) {
                Log::info('PhpSpreadsheet not installed');
                return null;
            }

            // Configure PDF Renderer (DomPDF or TCPDF)
            $tcpdfPath = base_path('vendor/tecnickcom/tcpdf');
            $dompdfPath = base_path('vendor/dompdf/dompdf');

            if (class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
                if (class_exists('TCPDF')) {
                    \PhpOffice\PhpSpreadsheet\Settings::setPdfRendererName(\PhpOffice\PhpSpreadsheet\Settings::PDF_RENDERER_TCPDF);
                    \PhpOffice\PhpSpreadsheet\Settings::setPdfRenderer($tcpdfPath);
                } elseif (class_exists('Dompdf\\Dompdf')) {
                    \PhpOffice\PhpSpreadsheet\Settings::setPdfRendererName(\PhpOffice\PhpSpreadsheet\Settings::PDF_RENDERER_DOMPDF);
                    \PhpOffice\PhpSpreadsheet\Settings::setPdfRenderer($dompdfPath);
                }
            }

            /** @var mixed $spreadsheet */
            $spreadsheet = $class::load($filePath);
            $writer = $class::createWriter($spreadsheet, 'Pdf');

            $tempPdf = sys_get_temp_dir() . '/' . Str::random(16) . '.pdf';
            $writer->save($tempPdf);

            if (file_exists($tempPdf)) {
                $content = file_get_contents($tempPdf);
                unlink($tempPdf);
                return $content;
            }
            return null;
        } catch (\Exception $e) {
            Log::warning('PhpSpreadsheet conversion failed', ['error' => $e->getMessage()]);
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
