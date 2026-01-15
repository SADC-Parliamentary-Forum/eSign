<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Workflow;
use App\Models\Document;
use App\Services\WorkflowEngine;
use Illuminate\Support\Facades\Log;

class WorkflowController extends Controller
{
    protected WorkflowEngine $workflowEngine;

    public function __construct(WorkflowEngine $workflowEngine)
    {
        $this->workflowEngine = $workflowEngine;
    }

    /**
     * Get workflow details.
     */
    public function show(Request $request, $id)
    {
        $workflow = Workflow::with(['document', 'steps.assignedUser'])->findOrFail($id);

        // Authorization: User must be owner of document or assigned to a step
        $user = $request->user();
        $isOwner = $workflow->document->user_id === $user->id;
        $isAssigned = $workflow->steps()->where('assigned_user_id', $user->id)->exists();

        if (!$isOwner && !$isAssigned) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'workflow' => $workflow,
            'current_steps' => $this->workflowEngine->getCurrentSteps($workflow),
            'can_user_sign' => $this->workflowEngine->canUserSign($workflow, $user)
        ]);
    }

    /**
     * Get workflow for a specific document.
     */
    public function getByDocument(Request $request, $documentId)
    {
        $document = Document::with('workflow.steps.assignedUser')->findOrFail($documentId);

        // Authorization: User must be owner or signer
        $user = $request->user();
        $isOwner = $document->user_id === $user->id;
        $isSigner = $document->workflow && $document->workflow->steps()
            ->where('assigned_user_id', $user->id)
            ->exists();

        if (!$isOwner && !$isSigner) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$document->workflow) {
            return response()->json(['message' => 'No workflow found for this document'], 404);
        }

        return response()->json([
            'workflow' => $document->workflow,
            'current_steps' => $this->workflowEngine->getCurrentSteps($document->workflow),
            'can_user_sign' => $this->workflowEngine->canUserSign($document->workflow, $user)
        ]);
    }

    /**
     * Cancel workflow.
     */
    public function cancel(Request $request, $id)
    {
        $workflow = Workflow::with('document')->findOrFail($id);

        // Only document owner can cancel
        if ($workflow->document->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($workflow->status !== 'ACTIVE') {
            return response()->json(['message' => 'Only active workflows can be cancelled'], 400);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        try {
            $this->workflowEngine->cancelWorkflow($workflow, $validated['reason']);

            return response()->json([
                'message' => 'Workflow cancelled successfully',
                'workflow' => $workflow->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Get all workflow steps.
     */
    public function getSteps(Request $request, $id)
    {
        $workflow = Workflow::with('steps.assignedUser')->findOrFail($id);

        // Authorization check
        $user = $request->user();
        $isOwner = $workflow->document->user_id === $user->id;
        $isAssigned = $workflow->steps()->where('assigned_user_id', $user->id)->exists();

        if (!$isOwner && !$isAssigned) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'steps' => $workflow->steps,
            'current_steps' => $this->workflowEngine->getCurrentSteps($workflow)->pluck('id'),
            'completed_count' => $workflow->steps()->where('status', 'SIGNED')->count(),
            'pending_count' => $workflow->steps()->where('status', 'PENDING')->count(),
            'declined_count' => $workflow->steps()->where('status', 'DECLINED')->count()
        ]);
    }

    /**
     * Get user's pending workflow steps.
     */
    public function getUserPending(Request $request)
    {
        $user = $request->user();

        $pendingSteps = \App\Models\WorkflowStep::where('assigned_user_id', $user->id)
            ->where('status', 'PENDING')
            ->with(['workflow.document', 'workflow'])
            ->get();

        // Filter to only show current steps (based on workflow type)
        $currentSteps = $pendingSteps->filter(function ($step) {
            return $this->workflowEngine->getCurrentSteps($step->workflow)->contains($step);
        });

        return response()->json([
            'pending_steps' => $currentSteps->values(),
            'total_count' => $currentSteps->count()
        ]);
    }
}
