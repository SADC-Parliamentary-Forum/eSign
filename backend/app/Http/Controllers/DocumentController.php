<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DocumentService;
use App\Services\SigningWorkflowService;
use App\Services\DocumentConversionService;
use App\Models\Document;
use App\Models\Template;
// Removed SignatureField

use App\Models\DocumentField;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    protected DocumentService $documentService;
    protected SigningWorkflowService $workflowService;
    protected DocumentConversionService $conversionService;

    public function __construct(
        DocumentService $documentService,
        SigningWorkflowService $workflowService,
        DocumentConversionService $conversionService
    ) {
        $this->documentService = $documentService;
        $this->workflowService = $workflowService;
        $this->conversionService = $conversionService;
    }

    /**
     * List user documents.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Document::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhereHas('signers', function ($sq) use ($user) {
                    $sq->where('email', $user->email)
                        ->orWhere('user_id', $user->id);
                });
        });

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $sortBy = $request->input('sort', 'updated_at');
        $sortOrder = $request->input('order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $limit = $request->input('limit', 10);

        return response()->json(
            $query->with('signers')->paginate($limit)
        );
    }

    /**
     * Get document statistics for dashboard.
     */
    public function stats(Request $request)
    {
        $userId = $request->user()->id;

        $drafts = Document::where('user_id', $userId)->where('status', 'DRAFT')->count();
        $inProgress = Document::where('user_id', $userId)->where('status', 'IN_PROGRESS')->count();
        $completed = Document::where('user_id', $userId)->where('status', 'COMPLETED')->count();
        $declined = Document::where('user_id', $userId)->where('status', 'DECLINED')->count();
        $total = $drafts + $inProgress + $completed + $declined;

        $completionRate = $total > 0 ? round(($completed / $total) * 100) : 0;

        // Calculate average signing time for completed documents
        $avgSigningTime = '0h';
        $completedDocs = Document::where('user_id', $userId)
            ->where('status', 'COMPLETED')
            ->whereNotNull('completed_at')
            ->get();

        if ($completedDocs->count() > 0) {
            $totalHours = 0;
            foreach ($completedDocs as $doc) {
                $created = new \DateTime($doc->created_at);
                $completed = new \DateTime($doc->completed_at);
                $diff = $created->diff($completed);
                $totalHours += ($diff->days * 24) + $diff->h;
            }
            $avgHours = $totalHours / $completedDocs->count();
            if ($avgHours < 24) {
                $avgSigningTime = round($avgHours, 1) . 'h';
            } else {
                $avgSigningTime = round($avgHours / 24, 1) . 'd';
            }
        }

        return response()->json([
            'drafts' => $drafts,
            'awaitingSignatures' => $inProgress,
            'completed' => $completed,
            'declined' => $declined,
            'total' => $total,
            'completionRate' => $completionRate,
            'avgSigningTime' => $avgSigningTime,
        ]);
    }

    /**
     * Create a new document.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required_without:template_id|file|mimes:pdf,docx,doc|max:20480',
            'template_id' => 'required_without:file|exists:templates,id',
            'signature_level' => 'nullable|string',
            'is_self_sign' => 'nullable|boolean',
        ]);

        try {
            $path = null;
            $hash = null;

            // Handle File Source
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $path = $file->store('documents', 'minio');
                $hash = hash_file('sha256', $file->getPathname());
            } elseif ($request->template_id) {
                $template = Template::with('fields')->findOrFail($request->template_id);

                // Copy template file to new location
                $extension = pathinfo($template->file_path, PATHINFO_EXTENSION);
                $newPath = 'documents/' . Str::random(40) . '.' . $extension;

                if (Storage::disk('minio')->exists($template->file_path)) {
                    Storage::disk('minio')->copy($template->file_path, $newPath);
                    $path = $newPath;
                    $hash = $template->file_hash;
                } else {
                    throw new \Exception('Template file not found.');
                }
            }

            if (!$path) {
                throw new \Exception('Failed to store file or find template.');
            }

            // Convert Word documents to PDF
            $originalPath = $path;
            $conversionResult = $this->conversionService->convertToPdfIfNeeded($path, 'minio');
            $path = $conversionResult['path'];
            $wasConverted = $conversionResult['converted'] ?? false;

            $size = 0;
            if (isset($file)) {
                $size = (int) $file->getSize();
            } elseif (isset($template)) {
                $size = (int) Storage::disk('minio')->size($template->file_path);
            }

            $mimeType = 'application/pdf';
            if (isset($file)) {
                $mimeType = $file->getMimeType();
            } elseif (isset($template)) {
                /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                $disk = Storage::disk('minio');
                $mimeType = $disk->mimeType($template->file_path);
            }

            // If file was converted to PDF, update mime type and size
            if ($wasConverted) {
                $mimeType = 'application/pdf';
                $size = (int) Storage::disk('minio')->size($path);
            }

            $createData = [
                'user_id' => $request->user()->id,
                'title' => $validated['title'],
                'file_path' => $path,
                'file_hash' => $hash,
                'size' => $size,
                'mime_type' => $mimeType,
                'status' => 'DRAFT',
                'signature_level' => isset($template) ? $template->required_signature_level : ($validated['signature_level'] ?? 'SIMPLE'),
                'is_self_sign' => $validated['is_self_sign'] ?? false,
            ];

            $document = Document::create($createData);

            // If created from template, copy fields
            if (isset($template) && $template->fields) {
                foreach ($template->fields as $field) {
                    DocumentField::create([
                        'document_id' => $document->id,
                        'type' => $field->type,
                        'page_number' => $field->page_number,
                        'x' => $field->x_position,
                        'y' => $field->y_position,
                        'width' => $field->width,
                        'height' => $field->height,
                        'signer_role' => $field->signer_role,
                        'label' => $field->label,
                        'required' => $field->required,
                    ]);
                }
            }

            return response()->json($document, 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create document: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get document details.
     */
    public function show(Request $request, $id)
    {
        $document = Document::with(['signatures', 'signers', 'fields', 'workflowLogs'])
            ->findOrFail($id);

        // Check authorization (owner or assigned signer)
        $user = $request->user();
        $isOwner = $document->user_id === $user->id;
        $isSigner = $document->signers()->where('user_id', $user->id)->exists() ||
            $document->signers()->where('email', $user->email)->exists();

        if (!$isOwner && !$isSigner) {
            abort(403, 'Unauthorized access to this document.');
        }

        // Generate signed URL for PDF access
        try {
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('minio');
            $document->pdf_url = $disk->temporaryUrl(
                $document->file_path,
                now()->addHours(2)
            );
        } catch (\Exception $e) {
            // Fallback for local storage or misconfigured minio
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('minio');
            $document->pdf_url = $disk->url($document->file_path);
        }

        return response()->json($document);
    }

    /**
     * Add signers to a document.
     */
    public function addSigners(Request $request, $id)
    {
        $document = Document::where('user_id', $request->user()->id)
            ->where('status', 'DRAFT')
            ->findOrFail($id);

        $validated = $request->validate([
            'signers' => 'required|array|min:1',
            'signers.*.email' => 'required|email',
            'signers.*.name' => 'required|string|max:255',
            'signers.*.order' => 'nullable|integer|min:1',
            'sequential' => 'nullable|boolean',
        ]);

        if (isset($validated['sequential'])) {
            $document->update(['sequential_signing' => $validated['sequential']]);
        }

        // If orders are provided, use them; otherwise auto-increment
        $signers = collect($validated['signers'])->map(function ($s, $index) {
            return [
                'email' => $s['email'],
                'name' => $s['name'],
                'order' => $s['order'] ?? ($index + 1),
                'role' => $s['role'] ?? null,
            ];
        })->sortBy('order')->values()->all();

        // Transaction handling for atomicity
        \Illuminate\Support\Facades\DB::transaction(function () use ($document, $signers) {
            // For DRAFT documents, we replace the signers list to avoid duplication
            // DO NOT delete if status is IN_PROGRESS (that would break ongoing workflows)
            if ($document->status === 'DRAFT') {
                $document->signers()->delete();
            }

            // Create new signer records and map fields
            foreach ($signers as $signerData) {
                $user = \App\Models\User::where('email', $signerData['email'])->first();

                $signer = \App\Models\DocumentSigner::create([
                    'document_id' => $document->id,
                    'user_id' => $user?->id,
                    'email' => $signerData['email'],
                    'name' => $signerData['name'],
                    // 'role' => $signerData['role'] ?? null, // DB column might not exist yet, careful
                    'signing_order' => $signerData['order'],
                ]);

                // Map template fields to this signer if role matches
                if (isset($signerData['role'])) {
                    DocumentField::where('document_id', $document->id)
                        ->where('signer_role', $signerData['role'])
                        ->update([
                            'document_signer_id' => $signer->id,
                            'signer_email' => $signer->email
                        ]);
                }
            }
        });


        return response()->json([
            'message' => 'Signers added successfully.',
            'signers' => $document->fresh()->signers,
        ]);
    }



    /**
     * Send document for signing.
     */
    public function send(Request $request, $id)
    {
        $document = Document::where('user_id', $request->user()->id)
            ->where('status', 'DRAFT')
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
            'completion_recipients' => 'nullable|array',
            'completion_recipients.notify_owner' => 'nullable|boolean',
            'completion_recipients.notify_signers' => 'nullable|boolean',
            'completion_recipients.additional_emails' => 'nullable|array',
            'completion_recipients.additional_emails.*' => 'nullable|email',
        ]);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($document, $validated) {
                // Update document status
                $document->update([
                    'status' => 'IN_PROGRESS',
                    'sent_at' => now(),
                    'sequential_signing' => $validated['sequential'] ?? false,
                    'expires_at' => isset($validated['expires_in_days'])
                        ? now()->addDays($validated['expires_in_days'])
                        : null,
                    'completion_recipients' => $validated['completion_recipients'] ?? null,
                ]);

                // Notify appropriate signers
                $this->workflowService->notifyCurrentSigners($document);
            });

            return response()->json([
                'message' => 'Document sent for signing.',
                'document' => $document->fresh(['signers']),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to send document: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json([
                'message' => 'Failed to send document: ' . $e->getMessage()
            ], 500);
        }
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
            $path = $this->documentService->createEvidenceBundle($document);
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('minio');
            return $disk->download($path);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error generating bundle: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Stream PDF file for viewing.
     * This proxies the file from MinIO to avoid CORS/internal URL issues.
     */
    public function streamPdf(Request $request, $id)
    {
        $document = Document::findOrFail($id);

        // Check authorization
        $user = $request->user();
        $isOwner = $document->user_id === $user->id;
        $isSigner = $document->signers()->where('user_id', $user->id)->exists() ||
            $document->signers()->where('email', $user->email)->exists();

        if (!$isOwner && !$isSigner) {
            abort(403, 'Unauthorized access to this document.');
        }

        try {
            $mimeType = $document->mime_type ?: 'application/pdf';
            $path = $document->file_path;

            return response()->stream(function () use ($path) {
                $stream = Storage::disk('minio')->readStream($path);
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
            ]);
        } catch (\Exception $e) {

            return response()->json(['message' => 'Error loading document: ' . $e->getMessage()], 500);
        }
    }
    /**
     * Delete a document.
     */
    public function destroy(Request $request, $id)
    {
        $document = Document::findOrFail($id);

        if ($request->user()->cannot('delete', $document)) {
            abort(403, 'Unauthorized');
        }

        try {
            // Delete file from storage
            if ($document->file_path && Storage::disk('minio')->exists($document->file_path)) {
                Storage::disk('minio')->delete($document->file_path);
            }

            $document->delete();

            return response()->json(['message' => 'Document deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete document: ' . $e->getMessage()], 500);
        }
    }
    /**
     * Bulk delete documents.
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:documents,id'
        ]);

        $ids = $validated['ids'];
        $count = 0;
        $errors = 0;

        foreach ($ids as $id) {
            $document = Document::find($id);
            if ($document && $request->user()->can('delete', $document)) {
                try {
                    if ($document->file_path && Storage::disk('minio')->exists($document->file_path)) {
                        Storage::disk('minio')->delete($document->file_path);
                    }
                    $document->delete();
                    $count++;
                } catch (\Exception $e) {
                    $errors++;
                }
            } else {
                $errors++;
            }
        }

        return response()->json([
            'message' => "Deleted {$count} documents.",
            'deleted_count' => $count,
            'errors' => $errors
        ]);
    }

    /**
     * Bulk download completed documents as a ZIP file with combined audit trail.
     */
    public function bulkDownload(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:documents,id'
        ]);

        $ids = $validated['ids'];
        $user = $request->user();

        // Fetch all requested documents
        $documents = Document::whereIn('id', $ids)
            ->where('status', 'COMPLETED')
            ->with(['signers', 'signatures', 'workflowLogs'])
            ->get();

        if ($documents->isEmpty()) {
            return response()->json([
                'message' => 'No completed documents found in the selection.'
            ], 422);
        }

        // Verify user has access to all documents
        $accessibleDocs = $documents->filter(function ($doc) use ($user) {
            return $user->can('view', $doc);
        });

        if ($accessibleDocs->count() !== $documents->count()) {
            return response()->json([
                'message' => 'You do not have access to all selected documents.'
            ], 403);
        }

        try {
            $zipPath = $this->createBulkDownloadBundle($accessibleDocs);

            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('minio');

            $filename = 'SignedDocuments_' . date('Y-m-d_His') . '.zip';

            return $disk->download($zipPath, $filename);
        } catch (\Exception $e) {
            \Log::error('Bulk download error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error creating download bundle: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a ZIP bundle containing multiple signed documents and combined audit trail.
     */
    private function createBulkDownloadBundle($documents)
    {
        $tempDir = storage_path('app/temp/' . Str::random(20));
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = 'evidence/bulk/' . Str::random(40) . '.zip';
        $localZipPath = $tempDir . '/bundle.zip';

        $zip = new \ZipArchive();
        if ($zip->open($localZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Could not create ZIP file');
        }

        // Create combined audit trail
        $combinedAuditTrail = $this->generateCombinedAuditTrail($documents);

        // Add each document's signed PDF to the ZIP
        $docIndex = 1;
        foreach ($documents as $document) {
            // Get the signed PDF
            $pdfPath = $document->file_path;
            if (Storage::disk('minio')->exists($pdfPath)) {
                $pdfContent = Storage::disk('minio')->get($pdfPath);
                $safeTitle = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $document->title);
                $filename = sprintf('%02d_%s.pdf', $docIndex, substr($safeTitle, 0, 50));
                $zip->addFromString('Documents/' . $filename, $pdfContent);
            }
            $docIndex++;
        }

        // Add combined audit trail PDF
        $auditPdfContent = $this->generateAuditTrailPdf($combinedAuditTrail, $documents);
        $zip->addFromString('Combined_Audit_Trail.pdf', $auditPdfContent);

        // Add audit trail as JSON for machine readability
        $zip->addFromString('audit_trail.json', json_encode($combinedAuditTrail, JSON_PRETTY_PRINT));

        // Add summary text file
        $summaryText = $this->generateSummaryText($documents);
        $zip->addFromString('README.txt', $summaryText);

        $zip->close();

        // Upload to MinIO
        Storage::disk('minio')->put($zipPath, file_get_contents($localZipPath));

        // Cleanup temp files
        unlink($localZipPath);
        rmdir($tempDir);

        return $zipPath;
    }

    /**
     * Generate combined audit trail data for multiple documents.
     */
    private function generateCombinedAuditTrail($documents)
    {
        $auditData = [
            'generated_at' => now()->toIso8601String(),
            'total_documents' => $documents->count(),
            'documents' => [],
        ];

        foreach ($documents as $document) {
            $docAudit = [
                'id' => $document->id,
                'title' => $document->title,
                'status' => $document->status,
                'created_at' => $document->created_at->toIso8601String(),
                'completed_at' => $document->completed_at?->toIso8601String(),
                'file_hash' => $document->file_hash,
                'signature_level' => $document->signature_level,
                'signers' => [],
                'events' => [],
            ];

            // Add signer information
            foreach ($document->signers as $signer) {
                $docAudit['signers'][] = [
                    'name' => $signer->name,
                    'email' => $signer->email,
                    'status' => $signer->status,
                    'signed_at' => $signer->signed_at?->toIso8601String(),
                    'ip_address' => $signer->ip_address,
                    'user_agent' => $signer->user_agent,
                ];
            }

            // Add workflow events
            foreach ($document->workflowLogs ?? [] as $log) {
                $docAudit['events'][] = [
                    'action' => $log->action,
                    'actor' => $log->actor,
                    'timestamp' => $log->created_at->toIso8601String(),
                    'details' => $log->details,
                    'ip_address' => $log->ip_address,
                ];
            }

            $auditData['documents'][] = $docAudit;
        }

        return $auditData;
    }

    /**
     * Generate PDF version of the combined audit trail.
     */
    private function generateAuditTrailPdf($auditData, $documents)
    {
        $html = view('pdf.combined-audit-trail', [
            'auditData' => $auditData,
            'documents' => $documents,
            'generatedAt' => now(),
        ])->render();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    /**
     * Generate summary text file for the bundle.
     */
    private function generateSummaryText($documents)
    {
        $summary = "SADC PF eSign - Bulk Download Summary\n";
        $summary .= "=====================================\n\n";
        $summary .= "Generated: " . now()->format('Y-m-d H:i:s T') . "\n";
        $summary .= "Total Documents: " . $documents->count() . "\n\n";
        $summary .= "Contents:\n";
        $summary .= "---------\n";
        $summary .= "- Documents/          - Signed PDF documents\n";
        $summary .= "- Combined_Audit_Trail.pdf - Audit trail for all documents\n";
        $summary .= "- audit_trail.json    - Machine-readable audit data\n\n";
        $summary .= "Document List:\n";
        $summary .= "--------------\n";

        $docIndex = 1;
        foreach ($documents as $doc) {
            $summary .= sprintf(
                "%d. %s\n   Status: %s | Completed: %s\n   Signers: %d\n\n",
                $docIndex,
                $doc->title,
                $doc->status,
                $doc->completed_at?->format('Y-m-d H:i:s') ?? 'N/A',
                $doc->signers->count()
            );
            $docIndex++;
        }

        $summary .= "\n---\n";
        $summary .= "This bundle was generated by SADC Parliamentary Forum eSign Platform.\n";
        $summary .= "For verification, please refer to the Combined_Audit_Trail.pdf file.\n";

        return $summary;
    }
}

