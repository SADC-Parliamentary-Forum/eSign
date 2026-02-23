<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Security service for file upload validation.
 * Implements defense-in-depth for file uploads.
 */
class FileSecurityService
{
    /**
     * Magic bytes (file signatures) for allowed file types.
     * These are the first bytes of each file type that identify it regardless of extension.
     */
    private const FILE_SIGNATURES = [
        'pdf' => [
            ['signature' => '%PDF', 'offset' => 0],
        ],
        'docx' => [
            ['signature' => "PK\x03\x04", 'offset' => 0], // ZIP-based Office format
        ],
        'doc' => [
            ['signature' => "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", 'offset' => 0], // OLE Compound Document
        ],
        'xlsx' => [
            ['signature' => "PK\x03\x04", 'offset' => 0], // ZIP-based Office format
        ],
        'xls' => [
            ['signature' => "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", 'offset' => 0], // OLE Compound Document
        ],
    ];

    /**
     * Dangerous file extensions that should never be allowed.
     */
    private const DANGEROUS_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'com', 'msi', 'scr', 'pif', 'vbs', 'vbe',
        'js', 'jse', 'ws', 'wsf', 'wsc', 'wsh', 'ps1', 'ps1xml', 'ps2',
        'ps2xml', 'psc1', 'psc2', 'msc', 'msp', 'mst', 'cpl', 'scf',
        'lnk', 'inf', 'reg', 'dll', 'ocx', 'sys', 'drv', 'php', 'phtml',
        'php3', 'php4', 'php5', 'asp', 'aspx', 'sh', 'bash', 'zsh',
        'csh', 'pl', 'py', 'rb', 'jar', 'class', 'war',
    ];

    /**
     * Allowed MIME types for document uploads.
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
        'application/msword', // doc
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
        'application/vnd.ms-excel', // xls
    ];

    /**
     * Maximum file size in bytes (10 MB).
     */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * Validate an uploaded file for security.
     *
     * @param UploadedFile $file
     * @return array{valid: bool, error: string|null}
     */
    public function validateUpload(UploadedFile $file): array
    {
        // 1. Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return ['valid' => false, 'error' => 'File exceeds maximum allowed size of 10 MB'];
        }

        // 2. Check extension against dangerous list
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, self::DANGEROUS_EXTENSIONS)) {
            \Log::warning('Blocked dangerous file upload', [
                'extension' => $extension,
                'original_name' => $file->getClientOriginalName(),
            ]);
            return ['valid' => false, 'error' => 'File type not allowed for security reasons'];
        }

        // 3. Validate MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            \Log::warning('Blocked file with disallowed MIME type', [
                'mime_type' => $mimeType,
                'original_name' => $file->getClientOriginalName(),
            ]);
            return ['valid' => false, 'error' => 'File type not allowed. Please upload PDF or Word documents only.'];
        }

        // 4. Validate file signature (magic bytes)
        if (!$this->validateFileSignature($file)) {
            \Log::warning('File signature mismatch', [
                'claimed_extension' => $extension,
                'claimed_mime' => $mimeType,
                'original_name' => $file->getClientOriginalName(),
            ]);
            return ['valid' => false, 'error' => 'File content does not match its extension. Upload rejected for security reasons.'];
        }

        // 5. Check for embedded executables in Office documents
        if (in_array($extension, ['docx', 'xlsx', 'doc', 'xls'])) {
            $embeddedCheck = $this->checkForEmbeddedExecutables($file);
            if (!$embeddedCheck['safe']) {
                \Log::warning('Blocked file with embedded executable content', [
                    'original_name' => $file->getClientOriginalName(),
                    'reason' => $embeddedCheck['reason'],
                ]);
                return ['valid' => false, 'error' => 'File contains potentially dangerous embedded content'];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate file signature (magic bytes) matches the claimed file type.
     *
     * @param UploadedFile $file
     * @return bool
     */
    public function validateFileSignature(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());

        // If we don't have a signature definition, allow it (rely on other checks)
        if (!isset(self::FILE_SIGNATURES[$extension])) {
            return true;
        }

        $handle = fopen($file->getPathname(), 'rb');
        if (!$handle) {
            return false;
        }

        $signatures = self::FILE_SIGNATURES[$extension];
        $valid = false;

        foreach ($signatures as $sigDef) {
            $offset = $sigDef['offset'];
            $expectedSignature = $sigDef['signature'];
            $length = strlen($expectedSignature);

            fseek($handle, $offset);
            $actualBytes = fread($handle, $length);

            if ($actualBytes === $expectedSignature) {
                $valid = true;
                break;
            }
        }

        fclose($handle);

        return $valid;
    }

    /**
     * Check for embedded executables in Office documents.
     * This is a basic check - for production, consider using a proper antivirus.
     *
     * @param UploadedFile $file
     * @return array{safe: bool, reason: string|null}
     */
    private function checkForEmbeddedExecutables(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        // For ZIP-based formats (docx, xlsx), check archive contents
        if (in_array($extension, ['docx', 'xlsx'])) {
            $zip = new \ZipArchive();
            if ($zip->open($file->getPathname()) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryName = $zip->getNameIndex($i);
                    $entryExt = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));

                    // Check for executable extensions inside the archive
                    if (in_array($entryExt, ['exe', 'dll', 'bat', 'cmd', 'vbs', 'js', 'ps1'])) {
                        $zip->close();
                        return ['safe' => false, 'reason' => "Contains embedded file: {$entryName}"];
                    }

                    // Check for OLE objects (commonly used for malware)
                    if (strpos($entryName, 'oleObject') !== false || strpos($entryName, 'embeddings') !== false) {
                        // This is suspicious but not necessarily malicious
                        // Log for monitoring
                        \Log::info('Office document contains embedded OLE object', [
                            'file' => $file->getClientOriginalName(),
                            'entry' => $entryName,
                        ]);
                    }
                }
                $zip->close();
            }
        }

        return ['safe' => true, 'reason' => null];
    }

    /**
     * Scan file for malware using ClamAV if available.
     * Returns scan result.
     *
     * @param string $filePath
     * @return array{clean: bool, threat: string|null}
     */
    public function scanForMalware(string $filePath): array
    {
        // Check if ClamAV is available
        if (!$this->isClamAvailable()) {
            // Log warning but don't block - ClamAV not configured
            \Log::warning('ClamAV not available for malware scanning', ['file' => $filePath]);
            return ['clean' => true, 'threat' => null]; // Assume clean if scanning unavailable
        }

        try {
            // Use clamscan command-line tool
            $process = new \Symfony\Component\Process\Process([
                'clamscan',
                '--no-summary',
                '--infected',
                $filePath
            ]);
            $process->run();

            // Exit code 0 = clean, 1 = infected, 2 = error
            if ($process->getExitCode() === 0) {
                return ['clean' => true, 'threat' => null];
            } elseif ($process->getExitCode() === 1) {
                $output = $process->getOutput();
                \Log::critical('Malware detected in uploaded file', [
                    'file' => $filePath,
                    'scan_output' => $output,
                ]);
                return ['clean' => false, 'threat' => trim($output)];
            } else {
                \Log::error('ClamAV scan error', [
                    'file' => $filePath,
                    'error' => $process->getErrorOutput(),
                ]);
                return ['clean' => true, 'threat' => null]; // Don't block on scan errors
            }
        } catch (\Exception $e) {
            \Log::error('ClamAV scan exception', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);
            return ['clean' => true, 'threat' => null];
        }
    }

    /**
     * Check if ClamAV is available on the system.
     *
     * @return bool
     */
    private function isClamAvailable(): bool
    {
        try {
            $process = new \Symfony\Component\Process\Process(['which', 'clamscan']);
            $process->run();
            return $process->isSuccessful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
