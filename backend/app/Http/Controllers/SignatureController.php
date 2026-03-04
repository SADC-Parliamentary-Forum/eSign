<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\Signature;
use App\Models\DocumentField;
use App\Models\DocumentSigner;
use App\Services\DelegationService;
use Illuminate\Support\Facades\DB;

class SignatureController extends Controller
{
    protected $delegationService;
    protected $documentService;
    protected $auditService;
    protected $workflowService;

    public function __construct(
        DelegationService $delegationService,
        \App\Services\DocumentService $documentService,
        \App\Services\AuditService $auditService,
        \App\Services\SigningWorkflowService $workflowService
    ) {
        $this->delegationService = $delegationService;
        $this->documentService = $documentService;
        $this->auditService = $auditService;
        $this->workflowService = $workflowService;
    }

    public function sign(Request $request, $documentId)
    {
        $document = Document::findOrFail($documentId);
        $user = $request->user();

        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*.field_id' => 'required|exists:document_fields,id',
            'fields.*.value' => 'nullable', // Allow empty string (e.g. unchecking checkbox or clearing text)
        ]);

        // Verification Enforcer
        if (in_array($document->signature_level, ['ADVANCED', 'QUALIFIED'])) {
            $signer = $document->signers()->where('email', $user->email)->first();
            // Note: Verification might need to apply to DELEGATE too.
            // For now, if delegation is active, we trust the delegate's own verification status?
            // Or the delegator's? Let's assume delegate must be verified if they sign.

            // Check if user is signing as themselves
            if ($signer && !$signer->verified_at) {
                return response()->json([
                    'message' => 'Identity verification required before signing.',
                    'requires_verification' => true
                ], 403);
            }
        }

        DB::transaction(function () use ($validated, $document, $user, $request) {
            foreach ($validated['fields'] as $input) {
                // Fetch field
                $field = DocumentField::where('id', $input['field_id'])->first();

                if (!$field)
                    continue;

                // Authorization: Check ownership or DELEGATION
                // Check by signer email or by matching the signer record's user_id
                $signerRecord = $field->document_signer_id
                    ? \App\Models\DocumentSigner::find($field->document_signer_id)
                    : null;
                $isOwner = ($field->signer_email === $user->email)
                    || ($signerRecord && $signerRecord->user_id === $user->id);
                $canSign = $isOwner;

                // If not directly owned, check delegation
                if (!$isOwner) {
                    $targetSigner = null;
                    if ($field->document_signer_id) {
                        $targetSigner = DocumentSigner::find($field->document_signer_id);
                    } elseif ($field->signer_email) {
                        $targetSigner = DocumentSigner::where('document_id', $document->id)
                            ->where('email', $field->signer_email)->first();
                    }

                    if ($targetSigner && $targetSigner->user_id) {
                        // Check if current user is a valid delegate for target user
                        if ($this->delegationService->canActOnBehalfOf($user->id, $targetSigner->user_id)) {
                            $canSign = true;
                        }
                    }
                }

                if (!$canSign) {
                    continue;
                }

                // Sequential Signing Check
                if ($document->sequential_signing) {
                    $fieldSigner = $field->signer;
                    if ($fieldSigner && $fieldSigner->signing_order != $document->current_signing_order) {
                        abort(403, 'It is not your turn to sign.');
                    }
                }

                if ($field->type === 'SIGNATURE' || $field->type === 'INITIALS') {
                    // Security: Validate Signature Data
                    $sigData = $input['value'];
                    if (strlen($sigData) > 680000) {
                        abort(422, 'Signature image too large. Max 500KB.');
                    }
                    if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $sigData)) {
                        abort(422, 'Invalid signature format. must be PNG or JPEG.');
                    }
                    $sig = Signature::create([
                        'document_id' => $document->id,
                        'user_id' => $user->id, // The actor (Delegate)
                        'signature_data' => $input['value'],
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'signed_at' => now(),
                        // 'on_behalf_of' => ... (Need to add column to Signature table if we want explicit tracking)
                        // For now metadata is enough or inferred from field owner vs user_id
                    ]);
                    $field->update([
                        'signature_id' => $sig->id,
                        'signed_at' => now()
                    ]);

                    $this->auditService->log(
                        $user,
                        'signed',
                        'document',
                        $document->id,
                        ['field_id' => $field->id, 'type' => $field->type]
                    );
                } else {
                    // Text/Date/Checkbox/Amount-in-Words
                    $val = $input['value'];

                    if ($field->type === 'AMOUNT_IN_WORDS') {
                        /** @var \App\Services\AmountInWordsService $amountService */
                        $amountService = app(\App\Services\AmountInWordsService::class);

                        // Extract the authoritative amount from the PDF itself
                        $docAmount = $amountService->extractAmountFromDocument($document);

                        // Fall back to the stored DB amount (if PDF extraction failed)
                        if ($docAmount === null) {
                            $docAmount = (float) ($document->amount ?? 0);
                        }

                        if ($docAmount <= 0) {
                            abort(422, 'Could not determine the document amount. Ensure the PDF contains a clear monetary amount.');
                        }

                        $result = $amountService->verify($docAmount, (string) $val);

                        if (!$result['match']) {
                            abort(422, sprintf(
                                'Amount in words does not match the amount in the document. ' .
                                'Expected: "%s". You entered: "%s".',
                                $result['expected'],
                                $result['provided']
                            ));
                        }
                    } elseif ($field->type === 'CHECKBOX') {
                        $val = filter_var($val, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
                    }

                    $field->update([
                        'text_value' => (string) $val,
                        'signed_at' => now(),
                    ]);
                }
            }

            // Update Signer Status
            $this->updateSignerStatus($document, $user);

            // Update Document Status
            $this->updateDocumentStatus($document);
        });

        return response()->json(['message' => 'Fields signed successfully']);
    }

    private function updateSignerStatus(Document $document, $user)
    {
        // Find signer record (check by email or user_id)
        $signer = $document->signers()->where('email', $user->email)->first();
        if (!$signer && $user->id) {
            $signer = $document->signers()->where('user_id', $user->id)->first();
        }

        if (!$signer)
            return;

        // Check if all REQUIRED fields for this signer are signed
        $pendingRequired = $document->fields()
            ->where(function ($q) use ($user, $signer) {
                $q->where('signer_email', $user->email)
                    ->orWhere('document_signer_id', $signer->id); // match signer record id if linked
            })
            ->where('required', true)
            ->whereNull('signed_at')
            ->exists();

        if (!$pendingRequired) {
            $signer->update([
                'status' => 'signed',
                'signed_at' => now(),
            ]);
        }
    }

    private function updateDocumentStatus(Document $document)
    {
        // Refresh signers
        $document->load('signers');

        $isCompleted = false;

        if ($document->sequential_signing) {
            // Find current order
            $currentOrder = $document->current_signing_order;

            // Check if ALL signers at this order are signed
            $currentSigners = $document->signers->where('signing_order', $currentOrder);
            $allCurrentSigned = $currentSigners->every(fn($s) => $s->status === 'signed');

            if ($allCurrentSigned && $currentSigners->isNotEmpty()) {
                // Move to next order
                $nextOrder = $document->signers->where('signing_order', '>', $currentOrder)->min('signing_order');

                if ($nextOrder) {
                    $document->update(['current_signing_order' => $nextOrder]);
                    // Notify next signers in the queue
                    $this->workflowService->notifyCurrentSigners($document->fresh());
                } else {
                    // No next order -> Completed
                    $isCompleted = true;
                }
            }
        } else {
            // Parallel: Check if ALL signers are signed
            if ($document->signers->every(fn($s) => $s->status === 'signed')) {
                $isCompleted = true;
            }
        }

        if ($isCompleted) {
            $document->update(['status' => 'COMPLETED', 'completed_at' => now()]);

            // Finalize Document (Stamp Signatures)
            try {
                $this->documentService->finalizeDocument($document);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to finalize document {$document->id}: " . $e->getMessage());
                \Illuminate\Support\Facades\Log::error($e->getTraceAsString());
            }
        }
    }

    public function reject(Request $request, $documentId)
    {
        $document = Document::findOrFail($documentId);
        $validated = $request->validate(['reason' => 'nullable|string|max:1000']);

        // Mark the rejecting signer as declined
        $user = $request->user();
        $signer = $document->signers()->where('email', $user->email)->first()
            ?? $document->signers()->where('user_id', $user->id)->first();

        if ($signer) {
            $signer->update([
                'status' => 'declined',
                'declined_at' => now(),
                'decline_reason' => $validated['reason'] ?? null,
            ]);
        }

        $document->update(['status' => 'DECLINED']);
        return response()->json(['message' => 'Document rejected successfully.']);
    }
}
