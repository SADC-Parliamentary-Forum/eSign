<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\Credentials\Credentials;

class MinIOServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only run if MinIO disk is configured
        $minioConfig = config('filesystems.disks.minio');
        
        if (!$minioConfig || !isset($minioConfig['bucket'])) {
            return;
        }

        try {
            $this->ensureBucketExists();
        } catch (\Exception $e) {
            // Log error but don't crash the application
            // This allows the app to start even if MinIO is temporarily unavailable
            Log::warning('Failed to ensure MinIO bucket exists: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Ensure the MinIO bucket exists, create it if it doesn't.
     */
    protected function ensureBucketExists(): void
    {
        $config = config('filesystems.disks.minio');
        $bucketName = $config['bucket'] ?? null;

        if (!$bucketName) {
            Log::warning('MINIO_BUCKET is not configured');
            return;
        }

        // Create S3 client with MinIO configuration
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => $config['region'] ?? 'us-east-1',
            'endpoint' => $config['endpoint'],
            'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? true,
            'credentials' => new Credentials(
                $config['key'],
                $config['secret']
            ),
            'http' => [
                'verify' => false, // MinIO typically uses self-signed certs in dev
            ],
        ]);

        // Check if bucket exists
        try {
            $s3Client->headBucket([
                'Bucket' => $bucketName,
            ]);
            
            Log::info("MinIO bucket '{$bucketName}' already exists");
            return;
        } catch (AwsException $e) {
            // If bucket doesn't exist (404), create it
            $errorCode = $e->getAwsErrorCode();
            $statusCode = $e->getStatusCode();
            
            if ($errorCode === 'NotFound' || $statusCode === 404 || $errorCode === 'NoSuchBucket') {
                try {
                    $s3Client->createBucket([
                        'Bucket' => $bucketName,
                    ]);
                    
                    Log::info("Created MinIO bucket '{$bucketName}'");
                } catch (AwsException $createException) {
                    // If bucket was created by another process between check and create
                    $createErrorCode = $createException->getAwsErrorCode();
                    if ($createErrorCode !== 'BucketAlreadyOwnedByYou' && 
                        $createErrorCode !== 'BucketAlreadyExists') {
                        throw $createException;
                    }
                    Log::info("MinIO bucket '{$bucketName}' was created by another process");
                }
            } else {
                // Re-throw other AWS errors
                throw $e;
            }
        }
    }
}
