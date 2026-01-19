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

    public function __construct(DelegationService $delegationService, \App\Services\DocumentService $documentService)
    {
        $this->delegationService = $delegationService;
        $this->documentService = $documentService;
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
                $isOwner = ($field->signer_email === $user->email) || ($field->document_signer_id === $user->id);
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

                if ($field->type === 'SIGNATURE' || $field->type === 'INITIALS') {
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
                } else {
                    // Text/Date/Checkbox
                    $val = $input['value'];
                    if ($field->type === 'CHECKBOX') {
                        $val = filter_var($val, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
                    }
                    $field->update([
                        'text_value' => (string) $val,
                        'signed_at' => now()
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
                    // Notify next signers (WorkflowService TODO)
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
        // Log rejection...
        $document->update(['status' => 'VOIDED']);
        return response()->json(['message' => 'Document rejected']);
    }
}
