<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Template;
use App\Models\TemplateField;
use App\Services\DocumentService;
use App\Services\TemplateService;
use App\Services\FinancialThresholdService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class TemplateController extends Controller
{
    protected DocumentService $documentService;
    protected TemplateService $templateService;
    protected FinancialThresholdService $thresholdService;

    public function __construct(
        DocumentService $documentService,
        TemplateService $templateService,
        FinancialThresholdService $thresholdService
    ) {
        $this->documentService = $documentService;
        $this->templateService = $templateService;
        $this->thresholdService = $thresholdService;
    }

    /**
     * List available templates (user's own + public).
     */
    public function index(Request $request)
    {
        $templates = Template::availableTo($request->user()->id)
            ->with('fields')
            ->get();

        return response()->json($templates);
    }

    /**
     * Create a new template.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'nullable|string|max:50',
            'workflow_type' => 'nullable|in:SEQUENTIAL,PARALLEL,MIXED',
            'amount_required' => 'nullable|boolean',
            'is_bulk_enabled' => 'nullable|boolean',
            'is_field_locked' => 'nullable|boolean',
            'file' => 'required|file|mimes:pdf|max:20480',
            'is_public' => 'nullable|boolean',
            'required_signature_level' => 'nullable|in:SIMPLE,ADVANCED,QUALIFIED',
        ]);

        try {
            // Upload template file
            $file = $request->file('file');
            $path = $file->store('templates', 'minio');
            $hash = hash_file('sha256', $file->getPathname());

            $template = Template::create([
                'user_id' => $request->user()->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'category' => $validated['category'] ?? 'Contract',
                'workflow_type' => $validated['workflow_type'] ?? 'SEQUENTIAL',
                'amount_required' => $validated['amount_required'] ?? false,
                'is_bulk_enabled' => $validated['is_bulk_enabled'] ?? false,
                'is_field_locked' => $validated['is_field_locked'] ?? false,
                'file_path' => $path,
                'file_hash' => $hash,
                'is_public' => $validated['is_public'] ?? false,
                'required_signature_level' => $validated['required_signature_level'] ?? 'SIMPLE',
                'status' => 'DRAFT',
            ]);

            return response()->json($template, 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Template creation failed: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Failed to create template: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get template details with fields.
     */
    public function show(Request $request, $id)
    {
        $template = Template::availableTo($request->user()->id)
            ->with('fields')
            ->findOrFail($id);

        return response()->json($template);
    }

    /**
     * Update a template.
     */
    public function update(Request $request, $id)
    {
        $template = Template::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'nullable|boolean',
        ]);

        $template->update($validated);

        return response()->json($template);
    }

    /**
     * Delete a template.
     */
    public function destroy(Request $request, $id)
    {
        $template = Template::where('user_id', $request->user()->id)
            ->findOrFail($id);

        // Soft delete
        $template->delete();

        return response()->json(['message' => 'Template deleted successfully']);
    }

    /**
     * Add or update template fields.
     */
    public function storeFields(Request $request, $id)
    {
        $template = Template::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*.type' => 'required|in:signature,initials,date,text,checkbox',
            'fields.*.organizational_role_id' => 'nullable|uuid|exists:organizational_roles,id',
            'fields.*.fill_mode' => 'nullable|in:PRE_FILL,SIGNER_FILL',
            'fields.*.signer_role' => 'nullable|string',
            'fields.*.signer_email' => 'nullable|string',
            'fields.*.page_number' => 'required|integer|min:1',
            'fields.*.x_position' => 'required|numeric|min:0',
            'fields.*.y_position' => 'required|numeric|min:0',
            'fields.*.width' => 'required|numeric|min:1',
            'fields.*.height' => 'required|numeric|min:1',
            'fields.*.required' => 'nullable|boolean',
            'fields.*.label' => 'nullable|string|max:100',
        ]);

        // Replace existing fields
        TemplateField::where('template_id', $id)->delete();

        $fields = [];
        foreach ($validated['fields'] as $fieldData) {
            $fields[] = TemplateField::create([
                'template_id' => $id,
                ...$fieldData,
                'type' => strtolower($fieldData['type']), // Force lowercase to satisfy DB constraint
            ]);
        }

        return response()->json([
            'message' => 'Template fields saved successfully.',
            'fields' => $fields,
        ]);
    }

    /**
     * Add roles to template.
     */
    public function storeRoles(Request $request, $id)
    {
        $template = Template::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*.organizational_role_id' => 'required|uuid|exists:organizational_roles,id',
            'roles.*.signing_order' => 'required|integer|min:1',
            'roles.*.is_required' => 'required|boolean',
            'roles.*.role' => 'nullable|string', // Legacy
            'roles.*.action' => 'nullable|string', // Legacy
        ]);

        try {
            $this->templateService->addRoles($template, $validated['roles']);

            return response()->json([
                'message' => 'Roles added successfully',
                'template' => $template->fresh(['roles'])
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Add field mappings to template.
     */
    public function storeFieldMappings(Request $request, $id)
    {
        $template = Template::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'mappings' => 'required|array',
            'mappings.*.role' => 'required|string|max:50',
            'mappings.*.page' => 'required|integer|min:1',
            'mappings.*.x' => 'required|numeric|between:0,1',
            'mappings.*.y' => 'required|numeric|between:0,1',
            'mappings.*.width' => 'required|numeric|between:0,1',
            'mappings.*.height' => 'required|numeric|between:0,1',
        ]);

        try {
            $this->templateService->addFieldMappings($template, $validated['mappings']);

            return response()->json([
                'message' => 'Field mappings added successfully',
                'template' => $template->fresh(['fieldMappings'])
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Add financial thresholds to template.
     */
    public function storeThresholds(Request $request, $id)
    {
        $template = Template::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'thresholds' => 'required|array',
            'thresholds.*.min_amount' => 'required|numeric|min:0',
            'thresholds.*.max_amount' => 'nullable|numeric|gt:thresholds.*.min_amount',
            'thresholds.*.required_roles' => 'required|array',
            'thresholds.*.required_roles.*' => 'string|max:50',
        ]);

        try {
            $this->templateService->addThresholds($template, $validated['thresholds']);

            return response()->json([
                'message' => 'Thresholds added successfully',
                'template' => $template->fresh(['thresholds'])
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Submit template for review.
     */
    public function submitForReview(Request $request, $id)
    {
        $template = Template::where('user_id', $request->user()->id)
            ->findOrFail($id);

        try {
            $this->templateService->submitForReview($template);

            return response()->json([
                'message' => 'Template submitted for review',
                'template' => $template->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Approve template (requires approval permission).
     */
    public function approve(Request $request, $id)
    {
        // TODO: Add authorization check for approver role
        $template = Template::where('status', 'REVIEW')->findOrFail($id);

        try {
            $this->templateService->approveTemplate($template, $request->user());

            return response()->json([
                'message' => 'Template approved',
                'template' => $template->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Activate template (make available for use).
     * Works on any template - skips review workflow.
     */
    public function activate(Request $request, $id)
    {
        $template = Template::findOrFail($id);

        try {
            // Directly set to ACTIVE status, bypassing review workflow
            $template->status = 'ACTIVE';
            $template->save();

            return response()->json([
                'message' => 'Template activated',
                'template' => $template->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }



    /**
     * Archive template.
     */
    public function archive(Request $request, $id)
    {
        $template = Template::where('user_id', $request->user()->id)
            ->findOrFail($id);

        try {
            $this->templateService->archiveTemplate($template);

            return response()->json([
                'message' => 'Template archived',
                'template' => $template->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Get threshold matrix for template.
     */
    public function getThresholdMatrix(Request $request, $id)
    {
        $template = Template::availableTo($request->user()->id)
            ->with('thresholds')
            ->findOrFail($id);

        $matrix = $this->thresholdService->getThresholdMatrix($template);

        return response()->json([
            'template_id' => $template->id,
            'amount_required' => $template->amount_required,
            'thresholds' => $matrix
        ]);
    }

    /**
     * Get template versions.
     */
    public function getVersions(Request $request, $id)
    {
        $template = Template::findOrFail($id);

        // Get all versions of this template (same name, different versions)
        $versions = Template::where('name', $template->name)
            ->orderBy('version', 'desc')
            ->get(['id', 'version', 'status', 'created_at', 'approved_at']);

        return response()->json($versions);
    }
    /**
     * Create a new version of the template.
     */
    public function createNewVersion(Request $request, $id)
    {
        $template = Template::where('user_id', $request->user()->id)
            ->findOrFail($id);

        try {
            $newVersion = $this->templateService->createVersion($template, [
                'name' => $request->input('name', $template->name), // Can optionally rename
                'description' => $request->input('description', $template->description),
            ]);

            return response()->json([
                'message' => 'New version created successfully',
                'template' => $newVersion
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Stream the template PDF file.
     */
    public function streamPdf(Request $request, $id)
    {
        $template = Template::availableTo($request->user()->id)->findOrFail($id);

        if (!Storage::disk('minio')->exists($template->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('minio');

        return $disk->response($template->file_path, $template->name . '.pdf');
    }

    /**
     * Clone a template.
     */
    public function clone(Request $request, $id)
    {
        $template = Template::availableTo($request->user()->id)->findOrFail($id);

        try {
            $clone = $template->replicate(['id', 'created_at', 'updated_at']);
            $clone->name = $template->name . ' (Copy)';
            $clone->user_id = $request->user()->id;
            $clone->status = 'DRAFT';
            $clone->usage_count = 0;
            $clone->last_used_at = null;
            $clone->save();

            // Clone fields
            foreach ($template->fields as $field) {
                $newField = $field->replicate(['id', 'created_at', 'updated_at']);
                $newField->template_id = $clone->id;
                $newField->save();
            }

            // Clone roles
            foreach ($template->roles as $role) {
                $newRole = $role->replicate(['id', 'created_at', 'updated_at']);
                $newRole->template_id = $clone->id;
                $newRole->save();
            }

            return response()->json([
                'message' => 'Template cloned successfully',
                'template' => $clone->fresh(['fields', 'roles'])
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a new version of the template.
     */
    public function createVersion(Request $request, $id)
    {
        $template = Template::where('user_id', $request->user()->id)
            ->findOrFail($id);

        try {
            $newVersion = $this->templateService->createVersion($template, [
                'name' => $template->name . ' v' . ($template->version + 1),
                'user_id' => $request->user()->id
            ]);

            return response()->json([
                'message' => 'New version created successfully',
                'template' => $newVersion
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Apply template to a document (single document flow).
     */
    /**
     * Apply template to a document (create instance).
     */
    public function apply(Request $request, $id)
    {
        $template = Template::availableTo($request->user()->id)
            ->with(['fields', 'roles', 'roles.organizationalRole'])
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'assignments' => 'required|array',
            'assignments.*.template_role_id' => 'required|uuid',
            'assignments.*.user_id' => 'nullable|uuid', // Can be null if using email/name only
            'assignments.*.email' => 'required|email',
            'assignments.*.name' => 'required|string',
            'field_values' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            // 1. Create Document
            $document = \App\Models\Document::create([
                'user_id' => $request->user()->id,
                'title' => $validated['title'],
                'status' => 'DRAFT',
                'file_path' => $template->file_path, // Copy logic needed if actual file copy required
                'file_hash' => $template->file_hash,
                'mime_type' => 'application/pdf', // Assumption
                'size' => 0, // Should be actual size
                'sequential_signing' => true, // Enforce by default for templates?
            ]);

            // Copy file physically if using MinIO
            // (omitted for brevity, handled in DocumentController usually)

            // 2. Create Signers (Assignments)
            $signerMap = []; // template_role_id => document_signer_id

            foreach ($validated['assignments'] as $assignment) {
                // Find stats from template role
                $templateRole = $template->roles->firstWhere('id', $assignment['template_role_id']);
                $signingOrder = $templateRole ? $templateRole->signing_order : 1;

                $signer = \App\Models\DocumentSigner::create([
                    'document_id' => $document->id,
                    'user_id' => $assignment['user_id'] ?? null,
                    'email' => $assignment['email'],
                    'name' => $assignment['name'],
                    'organizational_role_id' => $templateRole?->organizational_role_id,
                    'signing_order' => $signingOrder,
                ]);

                $signerMap[$assignment['template_role_id']] = $signer;
            }

            // 3. Create Fields & Populate Data
            foreach ($template->fields as $field) {
                $signer = null;
                // Find matching signer based on organizational role or legacy logic
                // Here we match by the template_role relationship implicitly or by org role ID
                // But fields link to organizational_role_id directly.

                if ($field->organizational_role_id) {
                    // Find the signer assigned to this role
                    // assignments input linked template_role_id, we need to map back
                    // Let's assume we search signers by org role id
                    $signer = \App\Models\DocumentSigner::where('document_id', $document->id)
                        ->where('organizational_role_id', $field->organizational_role_id)
                        ->first();
                }

                $initialValue = null;
                if ($field->fill_mode === 'PRE_FILL' && isset($validated['field_values'][$field->id])) {
                    $initialValue = $validated['field_values'][$field->id];
                }

                \App\Models\DocumentField::create([
                    'document_id' => $document->id,
                    'document_signer_id' => $signer?->id,
                    'signer_email' => $signer?->email,
                    'role' => $field->signer_role, // Keep for legacy
                    'organizational_role_id' => $field->organizational_role_id,
                    'type' => strtoupper($field->type),
                    'page_number' => $field->page_number,
                    'x_position' => $field->x_position,
                    'y_position' => $field->y_position,
                    'width' => $field->width,
                    'height' => $field->height,
                    'required' => $field->required,
                    'label' => $field->label,
                    'fill_mode' => $field->fill_mode,
                    'text_value' => $initialValue, // Pre-fill value
                ]);
            }

            // Update stats
            $template->increment('usage_count');
            $template->update(['last_used_at' => now()]);

            DB::commit();

            return response()->json([
                'message' => 'Document created successfully',
                'document' => $document->fresh()
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }


    /**
     * Get available categories.
     */
    public function categories()
    {
        return response()->json([
            'Contract',
            'HR',
            'Finance',
            'Legal',
            'Internal',
            'Other'
        ]);
    }

    /**
     * Get most used templates.
     */
    public function mostUsed(Request $request)
    {
        $templates = Template::availableTo($request->user()->id)
            ->active()
            ->orderBy('usage_count', 'desc')
            ->limit(10)
            ->get();

        return response()->json($templates);
    }

    /**
     * Get recently used templates.
     */
    public function recentlyUsed(Request $request)
    {
        $templates = Template::availableTo($request->user()->id)
            ->active()
            ->whereNotNull('last_used_at')
            ->orderBy('last_used_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json($templates);
    }
}

