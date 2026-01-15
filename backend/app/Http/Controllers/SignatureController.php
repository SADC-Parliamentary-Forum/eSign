<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\SignatureField;
use App\Models\Signature;
use Illuminate\Support\Facades\DB;

class SignatureController extends Controller
{
    /**
     * Add signature fields to a document (Preparation)
     */
    public function storeFields(Request $request, $documentId)
    {
        $document = Document::findOrFail($documentId);
        // Authorization check here...

        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*.type' => 'required|string',
            'fields.*.page_number' => 'required|integer',
            'fields.*.x_position' => 'required|numeric',
            'fields.*.y_position' => 'required|numeric',
            'fields.*.width' => 'required|numeric',
            'fields.*.height' => 'required|numeric',
            'fields.*.assigned_role_id' => 'nullable|exists:roles,id',
        ]);

        // Replace existing fields for simplicity in MVP
        SignatureField::where('document_id', $documentId)->delete();

        foreach ($validated['fields'] as $field) {
            SignatureField::create([
                'document_id' => $documentId,
                ...$field
            ]);
        }

        $document->update(['status' => 'pending']); // Ready for signing

        return response()->json(['message' => 'Fields saved, workflow started']);
    }

    /**
     * Sign a document
     */
    public function sign(Request $request, $documentId)
    {
        $document = Document::findOrFail($documentId);
        $user = $request->user();

        // Check if user is allowed to sign (based on fields or role)
        // Simple logic: If user role matches any field or user matches field
        // For MVP: assume user is valid signer.

        $signatureData = $request->input('signature_data'); // Base64 image

        Signature::create([
            'document_id' => $documentId,
            'user_id' => $user->id,
            'signature_data' => $signatureData,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'signed_at' => now(),
        ]);

        // Check if all required fields are signed
        // If yes, mark document as 'signed'
        // $document->update(['status' => 'signed']);

        return response()->json(['message' => 'Document signed successfully']);
    }

    public function reject(Request $request, $documentId)
    {
        $document = Document::findOrFail($documentId);
        // Log rejection...
        $document->update(['status' => 'rejected']);
        return response()->json(['message' => 'Document rejected']);
    }
}
