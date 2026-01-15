<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Template;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkflowEngine
{
    /**
     * Create workflow from template.
     */
    public function createWorkflowFromTemplate(
        Document $document,
        Template $template,
        array $userAssignments
    ): Workflow {
        if (!$template->isActive()) {
            throw new \RuntimeException('Cannot create workflow from inactive template');
        }

        DB::beginTransaction();
        try {
            // Create workflow
            $workflow = Workflow::create([
                'document_id' => $document->id,
                'type' => $template->workflow_type,
                'status' => 'ACTIVE',
            ]);

            // Create workflow steps from template roles
            $templateRoles = $template->roles()->orderBy('signing_order')->get();

            foreach ($templateRoles as $templateRole) {
                if (!isset($userAssignments[$templateRole->role])) {
                    throw new \RuntimeException("User assignment missing for role: {$templateRole->role}");
                }

                $userId = $userAssignments[$templateRole->role];

                WorkflowStep::create([
                    'workflow_id' => $workflow->id,
                    'role' => $templateRole->role,
                    'assigned_user_id' => $userId,
                    'signing_order' => $templateRole->signing_order,
                    'status' => 'PENDING',
                ]);
            }

            // Update document with workflow
            $document->update(['workflow_id' => $workflow->id]);

            DB::commit();

            Log::info('Workflow created from template', [
                'workflow_id' => $workflow->id,
                'document_id' => $document->id,
                'template_id' => $template->id
            ]);

            return $workflow->fresh(['steps']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create workflow from template', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create custom workflow.
     */
    public function createCustomWorkflow(Document $document, array $steps, string $type = 'SEQUENTIAL'): Workflow
    {
        DB::beginTransaction();
        try {
            $workflow = Workflow::create([
                'document_id' => $document->id,
                'type' => $type,
                'status' => 'ACTIVE',
            ]);

            foreach ($steps as $stepData) {
                WorkflowStep::create([
                    'workflow_id' => $workflow->id,
                    'role' => $stepData['role'],
                    'assigned_user_id' => $stepData['user_id'],
                    'signing_order' => $stepData['signing_order'] ?? 1,
                    'status' => 'PENDING',
                ]);
            }

            $document->update(['workflow_id' => $workflow->id]);

            DB::commit();

            Log::info('Custom workflow created', ['workflow_id' => $workflow->id, 'document_id' => $document->id]);

            return $workflow->fresh(['steps']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create custom workflow', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Advance workflow after step completion.
     */
    public function advanceWorkflow(Workflow $workflow): void
    {
        // Check if workflow is completed
        if ($this->checkCompletion($workflow)) {
            $workflow->markAsCompleted();

            // Mark document as completed
            if ($workflow->document) {
                $workflow->document->markAsCompleted();
            }

            Log::info('Workflow completed', ['workflow_id' => $workflow->id]);
        }
    }

    /**
     * Handle step completion.
     */
    public function handleStepCompletion(WorkflowStep $step): void
    {
        $step->markAsSigned();

        Log::info('Workflow step completed', [
            'step_id' => $step->id,
            'workflow_id' => $step->workflow_id,
            'user_id' => $step->assigned_user_id
        ]);

        // Advance workflow
        $this->advanceWorkflow($step->workflow);
    }

    /**
     * Handle step decline.
     */
    public function handleStepDecline(WorkflowStep $step, string $reason): void
    {
        DB::beginTransaction();
        try {
            $step->decline($reason);

            // Cancel the workflow
            $this->cancelWorkflow($step->workflow, "Step declined: {$reason}");

            // Update document status
            if ($step->workflow->document) {
                $step->workflow->document->update(['status' => 'VOIDED']);
            }

            DB::commit();

            Log::warning('Workflow step declined', [
                'step_id' => $step->id,
                'workflow_id' => $step->workflow_id,
                'reason' => $reason
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cancel workflow.
     */
    public function cancelWorkflow(Workflow $workflow, string $reason): void
    {
        $workflow->cancel($reason);

        Log::info('Workflow cancelled', ['workflow_id' => $workflow->id, 'reason' => $reason]);
    }

    /**
     * Check if workflow is completed.
     */
    public function checkCompletion(Workflow $workflow): bool
    {
        // Check if all pending steps are signed
        $pendingCount = $workflow->steps()->where('status', 'PENDING')->count();
        $signedCount = $workflow->steps()->where('status', 'SIGNED')->count();

        return $pendingCount === 0 && $signedCount > 0;
    }

    /**
     * Get current signable steps based on workflow type.
     */
    public function getCurrentSteps(Workflow $workflow): \Illuminate\Database\Eloquent\Collection
    {
        return $workflow->currentSteps()->get();
    }

    /**
     * Check if user can sign at current workflow state.
     */
    public function canUserSign(Workflow $workflow, User $user): bool
    {
        $currentSteps = $this->getCurrentSteps($workflow);

        return $currentSteps->contains('assigned_user_id', $user->id);
    }

    /**
     * Get next steps after current step completion (for sequential workflows).
     */
    public function getNextSteps(Workflow $workflow): \Illuminate\Database\Eloquent\Collection
    {
        if ($workflow->type === 'SEQUENTIAL') {
            $currentOrder = $workflow->steps()
                ->where('status', 'PENDING')
                ->min('signing_order');

            if ($currentOrder !== null) {
                $nextOrder = $workflow->steps()
                    ->where('signing_order', '>', $currentOrder)
                    ->min('signing_order');

                if ($nextOrder !== null) {
                    return $workflow->steps()
                        ->where('signing_order', $nextOrder)
                        ->get();
                }
            }
        }

        return collect();
    }
}
