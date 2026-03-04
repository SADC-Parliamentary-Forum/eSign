<?php

namespace App\Http\Controllers;

use App\Models\ComplianceRule;
use App\Models\Document;
use App\Services\ComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplianceController extends Controller
{
    protected ComplianceService $complianceService;

    public function __construct(ComplianceService $complianceService)
    {
        $this->complianceService = $complianceService;
    }

    // -------------------------------------------------------------------------
    // Compliance Rules (Admin CRUD)
    // -------------------------------------------------------------------------

    /**
     * List all compliance rules.
     */
    public function index(): JsonResponse
    {
        $rules = ComplianceRule::orderBy('priority', 'desc')->get();
        return response()->json(['data' => $rules]);
    }

    /**
     * Create a new compliance rule.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:compliance_rules,name',
            'description' => 'nullable|string|max:1000',
            'conditions' => 'required|array',
            'actions' => 'required|array',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0|max:100',
        ]);

        $rule = ComplianceRule::create($validated);

        return response()->json([
            'message' => 'Compliance rule created.',
            'data' => $rule,
        ], 201);
    }

    /**
     * Get a single compliance rule.
     */
    public function show(string $id): JsonResponse
    {
        $rule = ComplianceRule::findOrFail($id);
        return response()->json(['data' => $rule]);
    }

    /**
     * Update a compliance rule.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $rule = ComplianceRule::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:compliance_rules,name,' . $id,
            'description' => 'nullable|string|max:1000',
            'conditions' => 'sometimes|array',
            'actions' => 'sometimes|array',
            'is_active' => 'boolean',
            'priority' => 'integer|min:0|max:100',
        ]);

        $rule->update($validated);

        return response()->json([
            'message' => 'Compliance rule updated.',
            'data' => $rule->fresh(),
        ]);
    }

    /**
     * Delete a compliance rule.
     */
    public function destroy(string $id): JsonResponse
    {
        $rule = ComplianceRule::findOrFail($id);
        $rule->delete();

        return response()->json(['message' => 'Compliance rule deleted.']);
    }

    // -------------------------------------------------------------------------
    // Document Compliance Actions
    // -------------------------------------------------------------------------

    /**
     * Evaluate all active compliance rules against a document.
     */
    public function evaluate(Request $request, string $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        if ($request->user()->id !== $document->user_id && !$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $this->complianceService->evaluateRules($document);

        return response()->json([
            'message' => 'Compliance rules evaluated.',
            'document' => $document->fresh()->only([
                'id',
                'title',
                'status',
                'retention_period_days',
                'is_legal_hold',
                'jurisdiction',
            ]),
        ]);
    }

    /**
     * Toggle legal hold on a document.
     */
    public function toggleLegalHold(Request $request, string $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        $validated = $request->validate([
            'hold' => 'required|boolean',
            'reason' => 'nullable|string|max:1000',
        ]);

        $this->complianceService->toggleLegalHold($document, $validated['hold'], $validated['reason'] ?? null);

        $action = $validated['hold'] ? 'placed on' : 'removed from';

        return response()->json([
            'message' => "Document {$action} legal hold.",
            'is_legal_hold' => $document->fresh()->is_legal_hold,
        ]);
    }

    /**
     * Get retention/archive compliance status for a document.
     */
    public function status(Request $request, string $id): JsonResponse
    {
        $document = Document::findOrFail($id);

        if ($request->user()->id !== $document->user_id && !$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $shouldArchive = $this->complianceService->shouldArchive($document);
        $retentionDays = $document->retention_period_days ?? 3650;
        $retentionExpiry = $document->completed_at
            ? $document->completed_at->addDays($retentionDays)
            : null;

        return response()->json([
            'document_id' => $document->id,
            'status' => $document->status,
            'is_legal_hold' => $document->is_legal_hold,
            'legal_hold_reason' => $document->legal_hold_reason,
            'retention_days' => $retentionDays,
            'retention_expires' => $retentionExpiry?->toIso8601String(),
            'should_archive' => $shouldArchive,
            'completed_at' => $document->completed_at?->toIso8601String(),
            'jurisdiction' => $document->jurisdiction,
        ]);
    }
}
