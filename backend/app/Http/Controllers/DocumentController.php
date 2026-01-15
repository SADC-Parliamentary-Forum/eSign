<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DocumentService;
use App\Services\SigningWorkflowService;
use App\Models\Document;
use App\Models\Template;
use App\Models\SignatureField;

class DocumentController extends Controller
{
    protected DocumentService $documentService;
    protected SigningWorkflowService $workflowService;

    public function __construct(
        DocumentService $documentService,
        SigningWorkflowService $workflowService
    ) {
        $this->documentService = $documentService;
        $this->workflowService = $workflowService;
    }

    /**
     * Upload a new document (optionally from template).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required_without:template_id|file|mimes:pdf,doc,docx|max:20480',
            'template_id' => 'required_without:file|uuid|exists:templates,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            if ($request->hasFile('file')) {
                $document = $this->documentService->upload(
                    $request->file('file'),
                    $request->user(),
                    [
                        'title' => $validated['title'],
                        'description' => $validated['description'] ?? null,
                    ]
                );
            } else {
                // Create from template
                $template = Template::findOrFail($validated['template_id']);
                $document = $this->documentService->createFromTemplate(
                    $template,
                    $request->user(),
                    ['title' => $validated['title']]
                );
            }

            return response()->json($document, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * List user's documents.
     */
    public function index(Request $request)
    {
        $documents = Document::where('user_id', $request->user()->id)
            ->with([
                'signers' => function ($q) {
                    $q->select('id', 'document_id', 'name', 'email', 'status', 'signing_order');
                }
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($documents);
    }

    /**
     * List documents pending user's signature.
     */
    public function pending(Request $request)
    {
        $documents = Document::pendingSignatureFrom($request->user()->id)
            ->with(['user:id,name,email', 'signers'])
            ->orderByDesc('sent_at')
            ->get();

        return response()->json($documents);
    }

    /**
     * Get document details.
     */
    public function show(Request $request, $id)
    {
        $document = Document::with(['signatures', 'signers', 'signatureFields', 'workflowLogs'])
            ->findOrFail($id);

        // Check authorization
        $user = $request->user();
        $isOwner = $document->user_id === $user->id;
        $isSigner = $document->signers()->where('user_id', $user->id)->exists();

        if (!$isOwner && !$isSigner) {
            abort(403, 'Unauthorized access to this document.');
        }

        return response()->json($document);
    }

    /**
     * Add signers to a document.
     */
    public function addSigners(Request $request, $id)
    {
        $document = Document::where('user_id', $request->user()->id)
            ->where('status', 'draft')
            ->findOrFail($id);

        $validated = $request->validate([
            'signers' => 'required|array|min:1',
            'signers.*.email' => 'required|email',
            'signers.*.name' => 'required|string|max:255',
            'signers.*.order' => 'nullable|integer|min:1',
        ]);

        // If orders are provided, use them; otherwise auto-increment
        $signers = collect($validated['signers'])->map(function ($s, $index) {
            return [
                'email' => $s['email'],
                'name' => $s['name'],
                'order' => $s['order'] ?? ($index + 1),
            ];
        })->sortBy('order')->values()->all();

        // Create signer records (don't notify yet)
        foreach ($signers as $signerData) {
            $user = \App\Models\User::where('email', $signerData['email'])->first();

            \App\Models\DocumentSigner::create([
                'document_id' => $document->id,
                'user_id' => $user?->id,
                'email' => $signerData['email'],
                'name' => $signerData['name'],
                'signing_order' => $signerData['order'],
            ]);
        }

        return response()->json([
            'message' => 'Signers added successfully.',
            'signers' => $document->fresh()->signers,
        ]);
    }

    /**
     * Add signature fields to a document.
     */
    public function addFields(Request $request, $id)
    {
        $document = Document::where('user_id', $request->user()->id)
            ->whereIn('status', ['draft', 'sent'])
            ->findOrFail($id);

        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*.type' => 'required|in:signature,initials,date,text',
            'fields.*.page_number' => 'required|integer|min:1',
            'fields.*.x_position' => 'required|numeric|min:0',
            'fields.*.y_position' => 'required|numeric|min:0',
            'fields.*.width' => 'required|numeric|min:10',
            'fields.*.height' => 'required|numeric|min:10',
            'fields.*.assigned_signer_id' => 'nullable|uuid|exists:document_signers,id',
            'fields.*.required' => 'nullable|boolean',
        ]);

        // Replace existing fields
        SignatureField::where('document_id', $id)->delete();

        foreach ($validated['fields'] as $fieldData) {
            SignatureField::create([
                'document_id' => $id,
                ...$fieldData
            ]);
        }

        return response()->json([
            'message' => 'Signature fields saved.',
            'fields' => $document->fresh()->signatureFields,
        ]);
    }

    /**
     * Send document for signing.
     */
    public function send(Request $request, $id)
    {
        $document = Document::where('user_id', $request->user()->id)
            ->where('status', 'draft')
            ->with('signers')
            ->findOrFail($id);

        if ($document->signers->isEmpty()) {
            return response()->json([
                'message' => 'Please add at least one signer before sending.'
            ], 422);
        }

        $validated = $request->validate([
            'sequential' => 'nullable|boolean',
            'expires_in_days' => 'nullable|integer|min:1|max:365',
            'message' => 'nullable|string|max:1000',
        ]);

        // Update document status and notify signers
        $document->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sequential_signing' => $validated['sequential'] ?? false,
            'expires_at' => isset($validated['expires_in_days'])
                ? now()->addDays($validated['expires_in_days'])
                : null,
        ]);

        // Notify appropriate signers
        $this->workflowService->notifyCurrentSigners($document);

        return response()->json([
            'message' => 'Document sent for signing.',
            'document' => $document->fresh(['signers']),
        ]);
    }

    /**
     * Get detailed signing status.
     */
    public function status($id)
    {
        $document = Document::with(['signers', 'signatures'])
            ->findOrFail($id);

        return response()->json([
            'document_id' => $document->id,
            'title' => $document->title,
            'status' => $document->status,
            'sent_at' => $document->sent_at,
            'expires_at' => $document->expires_at,
            'completed_at' => $document->completed_at,
            'sequential_signing' => $document->sequential_signing,
            'current_signing_order' => $document->current_signing_order,
            'signers' => $document->signers->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'email' => $s->email,
                'status' => $s->status,
                'signing_order' => $s->signing_order,
                'notified_at' => $s->notified_at,
                'viewed_at' => $s->viewed_at,
                'signed_at' => $s->signed_at,
                'declined_at' => $s->declined_at,
            ]),
            'progress' => [
                'total' => $document->signers->count(),
                'signed' => $document->signers->where('status', 'signed')->count(),
                'pending' => $document->signers->whereIn('status', ['pending', 'notified', 'viewed'])->count(),
            ],
        ]);
    }

    /**
     * Download evidence bundle.
     */
    public function downloadEvidence($id)
    {
        $document = Document::findOrFail($id);

        if (auth()->user()->cannot('view', $document)) {
            abort(403, 'Unauthorized access to this document.');
        }

        try {
            $zipPath = $this->documentService->createEvidenceBundle($document);
            return response()->download($zipPath)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error generating bundle: ' . $e->getMessage()], 500);
        }
    }
}
