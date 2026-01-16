<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Template;
use App\Models\TemplateField;
use App\Services\DocumentService;
use App\Services\TemplateService;
use App\Services\FinancialThresholdService;
use Illuminate\Support\Facades\Storage;

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
            'file' => 'required|file|mimes:pdf|max:20480',
            'is_public' => 'nullable|boolean',
            'required_signature_level' => 'nullable|in:SIMPLE,ADVANCED,QUALIFIED', // Add validation
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
                'file_path' => $path,
                'file_hash' => $hash,
                'is_public' => $validated['is_public'] ?? false,
                'required_signature_level' => $validated['required_signature_level'] ?? 'SIMPLE', // Default SIMPLE
            ]);

            return response()->json($template, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
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
            'fields.*.type' => 'required|in:signature,initials,date,text',
            'fields.*.signer_role' => 'nullable|string|max:50',
            'fields.*.page_number' => 'required|integer|min:1',
            'fields.*.x_position' => 'required|numeric|min:0',
            'fields.*.y_position' => 'required|numeric|min:0',
            'fields.*.width' => 'required|numeric|min:10',
            'fields.*.height' => 'required|numeric|min:10',
            'fields.*.required' => 'nullable|boolean',
            'fields.*.label' => 'nullable|string|max:100',
        ]);

        // Replace existing fields
        TemplateField::where('template_id', $id)->delete();

        $fields = [];
        foreach ($validated['fields'] as $fieldData) {
            $fields[] = TemplateField::create([
                'template_id' => $id,
                ...$fieldData
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
            'roles.*.role' => 'required|string|max:50',
            'roles.*.action' => 'required|in:SIGN,APPROVE,ACKNOWLEDGE,REVIEW',
            'roles.*.required' => 'required|boolean',
            'roles.*.signing_order' => 'required|integer|min:1',
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
     */
    public function activate(Request $request, $id)
    {
        // TODO: Add authorization check for approver role
        $template = Template::where('status', 'APPROVED')->findOrFail($id);

        try {
            $this->templateService->activateTemplate($template);

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
}

