<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\User;
use App\Models\Signature;
use App\Notifications\SigningRequestNotification;
use App\Notifications\DocumentSignedNotification;
use App\Notifications\DocumentCompletedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;

class SigningWorkflowService
{
    /**
     * Send document for signing to specified signers.
     */
    public function sendForSigning(Document $document, array $signers, array $options = []): Document
    {
        return DB::transaction(function () use ($document, $signers, $options) {
            // Create signer records
            $order = 1;
            foreach ($signers as $signerData) {
                // Check if user already exists
                $user = User::where('email', $signerData['email'])->first();

                DocumentSigner::create([
                    'document_id' => $document->id,
                    'user_id' => $user?->id,
                    'email' => $signerData['email'],
                    'name' => $signerData['name'],
                    'signing_order' => $options['sequential'] ?? false ? $order++ : 1,
                ]);
            }

            // Update document status
            $document->update([
                'status' => 'IN_PROGRESS',
                'sent_at' => now(),
                'sequential_signing' => $options['sequential'] ?? false,
                'expires_at' => isset($options['expires_in_days'])
                    ? now()->addDays($options['expires_in_days'])
                    : null,
            ]);

            // Notify signers (sequential: only first order, parallel: all)
            $this->notifyCurrentSigners($document);

            return $document->fresh(['signers']);
        });
    }

    /**
     * Process a signer's signature.
     */
    public function processSignature(
        DocumentSigner $signer,
        string $signatureData,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): Document {
        return DB::transaction(function () use ($signer, $signatureData, $ipAddress, $userAgent) {
            $document = $signer->document;

            // Verify signer can sign
            if (!$signer->canSign()) {
                throw new \Exception('You cannot sign this document at this time.');
            }

            // Create signature record
            Signature::create([
                'document_id' => $document->id,
                'user_id' => $signer->user_id,
                'signature_data' => $signatureData,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'signed_at' => now(),
            ]);

            // Update signer status
            $signer->markAsSigned($ipAddress, $userAgent);

            // Update document status
            // Document is already IN_PROGRESS (set during sendForSigning)

            // Notify document owner
            $this->notifyOwnerOfSignature($document, $signer);

            // Check if workflow should advance
            $this->advanceWorkflow($document);

            return $document->fresh(['signers']);
        });
    }

    /**
     * Process a signer declining to sign.
     */
    public function processDecline(DocumentSigner $signer, ?string $reason = null): Document
    {
        return DB::transaction(function () use ($signer, $reason) {
            $document = $signer->document;

            $signer->markAsDeclined($reason);

            $document->update(['status' => 'VOIDED']);

            // Notify document owner
            $this->notifyOwnerOfDecline($document, $signer, $reason);

            return $document->fresh(['signers']);
        });
    }

    /**
     * Advance the signing workflow to the next step.
     */
    protected function advanceWorkflow(Document $document): void
    {
        $document->refresh();

        // Check if document is fully signed
        if ($document->isFullySigned()) {
            $document->markAsCompleted();
            $this->notifyAllOfCompletion($document);
            return;
        }

        // For sequential signing, advance to next order
        if ($document->sequential_signing) {
            $currentOrderComplete = $document->signers()
                ->where('signing_order', $document->current_signing_order)
                ->whereIn('status', ['pending', 'notified', 'viewed'])
                ->count() === 0;

            if ($currentOrderComplete) {
                $document->advanceSigningOrder();
                $this->notifyCurrentSigners($document);
            }
        }
    }

    /**
     * Notify signers who are currently able to sign.
     */
    public function notifyCurrentSigners(Document $document): void
    {
        $signers = $document->currentSigners()->get();

        foreach ($signers as $signer) {
            if ($signer->status === 'pending') {
                $signer->update([
                    'status' => 'notified',
                    'notified_at' => now(),
                ]);

                // Send notification
                if ($signer->user) {
                    $signer->user->notify(new SigningRequestNotification($document, $signer));
                }

                // Always send email (even for registered users, for multi-channel)
                Notification::route('mail', $signer->email)
                    ->notify(new SigningRequestNotification($document, $signer));
            }
        }
    }

    /**
     * Notify document owner that a signer has signed.
     */
    protected function notifyOwnerOfSignature(Document $document, DocumentSigner $signer): void
    {
        $document->user->notify(new DocumentSignedNotification($document, $signer));
    }

    /**
     * Notify document owner that a signer has declined.
     */
    protected function notifyOwnerOfDecline(Document $document, DocumentSigner $signer, ?string $reason): void
    {
        // Could create a specific DeclinedNotification
        $document->user->notify(new DocumentSignedNotification($document, $signer, true, $reason));
    }

    /**
     * Notify all parties that the document is complete.
     */
    protected function notifyAllOfCompletion(Document $document): void
    {
        // Notify owner
        $document->user->notify(new DocumentCompletedNotification($document));

        // Notify all signers
        foreach ($document->signers as $signer) {
            if ($signer->user) {
                $signer->user->notify(new DocumentCompletedNotification($document));
            } else {
                Notification::route('mail', $signer->email)
                    ->notify(new DocumentCompletedNotification($document));
            }
        }
    }

    /**
     * Get a signer by their access token.
     */
    public function getSignerByToken(string $token): ?DocumentSigner
    {
        return DocumentSigner::where('access_token', $token)
            ->with(['document', 'user'])
            ->first();
    }
}
