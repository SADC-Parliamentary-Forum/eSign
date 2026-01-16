<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\DocumentField;
use Illuminate\Support\Facades\DB;

class DocumentFieldController extends Controller
{
    /**
     * Get fields for a document.
     */
    public function index(Request $request, $documentId)
    {
        $document = Document::findOrFail($documentId);

        // Auth check (view policy)
        if ($request->user()->cannot('view', $document)) {
            abort(403);
        }

        return response()->json($document->fields);
    }

    /**
     * Save fields for a document (Replace all).
     */
    public function store(Request $request, $documentId)
    {
        $document = Document::findOrFail($documentId);

        // Auth check (update/edit policy)
        // Usually only 'draft' documents can have fields modified
        if ($request->user()->cannot('update', $document)) {
            abort(403);
        }

        if ($document->status !== 'DRAFT') {
            return response()->json(['message' => 'Cannot modify fields of a finalized document'], 400);
        }

        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*.type' => 'required|string|in:SIGNATURE,INITIALS,DATE,TEXT,CHECKBOX',
            'fields.*.page_number' => 'required|integer|min:1',
            'fields.*.x' => 'required|numeric',
            'fields.*.y' => 'required|numeric',
            'fields.*.width' => 'required|numeric',
            'fields.*.height' => 'required|numeric',
            'fields.*.signer_email' => 'nullable|email',
            'fields.*.document_signer_id' => 'nullable|exists:document_signers,id',
            'fields.*.required' => 'boolean',
            'fields.*.label' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Delete existing fields
            $document->fields()->delete();

            // Create new fields
            $fields = [];
            foreach ($validated['fields'] as $fieldData) {
                $fields[] = new DocumentField($fieldData);
            }

            $document->fields()->saveMany($fields);

            DB::commit();

            return response()->json(['message' => 'Fields saved successfully', 'count' => count($fields)]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to save fields: ' . $e->getMessage()], 500);
        }
    }
}
