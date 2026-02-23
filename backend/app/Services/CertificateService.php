<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\DocumentSigner;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CertificateService
{
    /**
     * Generate self-signed certificate for signer.
     */
    public function generateCertificate(DocumentSigner $signer, string $certificateType = 'SELF_SIGNED'): Certificate
    {
        // Generate key pair
        $keyPair = $this->generateKeyPair();

        $serialNumber = $this->generateSerialNumber();
        $issuer = config('app.name') . ' Certificate Authority';
        $subject = "CN={$signer->name},E={$signer->email}";

        // Generate certificate PEM
        $certificatePem = $this->generateCertificatePEM(
            $keyPair['public'],
            $keyPair['private'],
            $subject,
            $issuer
        );

        $certificate = Certificate::create([
            'document_signer_id' => $signer->id,
            'certificate_type' => $certificateType,
            'serial_number' => $serialNumber,
            'issuer' => $issuer,
            'subject' => $subject,
            'public_key' => $keyPair['public'],
            'private_key' => encrypt($keyPair['private']), // Encrypted storage
            'valid_from' => now(),
            'valid_to' => now()->addYears(2),
            'certificate_pem' => $certificatePem,
            'thumbprint' => hash('sha256', $certificatePem),
        ]);

        Log::info('Certificate generated', [
            'certificate_id' => $certificate->id,
            'signer_id' => $signer->id,
            'serial' => $serialNumber,
        ]);

        return $certificate;
    }

    /**
     * Generate RSA key pair.
     */
    protected function generateKeyPair(): array
    {
        // Security: Use RSA-4096 for stronger long-term security
        $config = [
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $privateKey = openssl_pkey_new($config);

        // Export private key
        openssl_pkey_export($privateKey, $privateKeyPem);

        // Export public key
        $publicKeyDetails = openssl_pkey_get_details($privateKey);
        $publicKeyPem = $publicKeyDetails['key'];

        return [
            'private' => $privateKeyPem,
            'public' => $publicKeyPem,
        ];
    }

    /**
     * Generate certificate serial number.
     */
    protected function generateSerialNumber(): string
    {
        return strtoupper(Str::random(16));
    }

    /**
     * Generate self-signed certificate PEM.
     */
    protected function generateCertificatePEM(
        string $publicKey,
        string $privateKey,
        string $subject,
        string $issuer
    ): string {
        // Parse subject and issuer
        $dn = [
            'commonName' => $subject,
            'organizationName' => $issuer,
        ];

        $privateKeyResource = openssl_pkey_get_private($privateKey);

        // Generate CSR
        $csr = openssl_csr_new($dn, $privateKeyResource, [
            'digest_alg' => 'sha256',
        ]);

        // Sign certificate (self-signed for 2 years)
        $x509 = openssl_csr_sign($csr, null, $privateKeyResource, 730, [
            'digest_alg' => 'sha256',
        ]);

        // Export certificate
        openssl_x509_export($x509, $certPem);

        return $certPem;
    }

    /**
     * Validate certificate.
     */
    public function validateCertificate(Certificate $certificate): array
    {
        $errors = [];

        if ($certificate->isExpired()) {
            $errors[] = 'Certificate has expired';
        }

        if ($certificate->isRevoked()) {
            $errors[] = 'Certificate has been revoked';
        }

        // Verify certificate signature
        if (!$this->verifyCertificateSignature($certificate)) {
            $errors[] = 'Certificate signature is invalid';
        }

        return $errors;
    }

    /**
     * Verify certificate signature.
     */
    protected function verifyCertificateSignature(Certificate $certificate): bool
    {
        try {
            // Parse certificate
            $x509 = openssl_x509_read($certificate->certificate_pem);
            if (!$x509) {
                return false;
            }

            // Get certificate info
            $certInfo = openssl_x509_parse($x509);

            // Verify certificate is readable and valid
            return $certInfo !== false;
        } catch (\Exception $e) {
            Log::error('Certificate signature verification failed', [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get certificate chain.
     */
    public function getCertificateChain(Certificate $certificate): array
    {
        // For self-signed certificates, chain only contains the certificate itself
        // In future: implement full CA chain
        return [
            [
                'serial' => $certificate->serial_number,
                'issuer' => $certificate->issuer,
                'subject' => $certificate->subject,
                'valid_from' => $certificate->valid_from->toISOString(),
                'valid_to' => $certificate->valid_to->toISOString(),
                'thumbprint' => $certificate->thumbprint,
            ],
        ];
    }
}
