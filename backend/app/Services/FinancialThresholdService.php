<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Template;
use App\Models\TemplateThreshold;
use Illuminate\Support\Facades\Log;

class FinancialThresholdService
{
    /**
     * Validate that amount meets template requirements.
     */
    public function validateAmount(Template $template, float|string|null $amount): bool
    {
        // If template doesn't require amount, validation passes
        if (!$template->requiresAmount()) {
            return true;
        }

        // If template requires amount but none provided, fail
        if ($amount === null) {
            return false;
        }

        $amount = (float) $amount;

        // Check if amount falls within any defined threshold
        if ($template->thresholds()->count() > 0) {
            $threshold = $template->getThresholdForAmount($amount);
            return $threshold !== null;
        }

        // If no thresholds defined but amount required, any amount is valid
        return true;
    }

    /**
     * Get required roles for a given amount.
     */
    public function getRequiredRoles(Template $template, float|string $amount): array
    {
        if (!$template->requiresAmount()) {
            // Return all required roles from template
            return $template->roles()
                ->where('required', true)
                ->pluck('role')
                ->toArray();
        }

        $amount = (float) $amount;
        $threshold = $template->getThresholdForAmount($amount);

        if ($threshold) {
            return $threshold->required_roles;
        }

        // Fallback to template's default roles
        return $template->roles()
            ->where('required', true)
            ->pluck('role')
            ->toArray();
    }

    /**
     * Enforce thresholds when creating document.
     */
    public function enforceThresholds(Document $document): array
    {
        if (!$document->template) {
            return [];
        }

        $template = $document->template;

        if (!$template->requiresAmount()) {
            return $template->roles()
                ->where('required', true)
                ->pluck('role')
                ->toArray();
        }

        if (!$document->amount) {
            throw new \RuntimeException('Document amount is required for this template');
        }

        $requiredRoles = $this->getRequiredRoles($template, (float) $document->amount);

        if (empty($requiredRoles)) {
            throw new \RuntimeException('No valid threshold found for amount: ' . $document->amount);
        }

        Log::info('Financial threshold enforced', [
            'document_id' => $document->id,
            'amount' => $document->amount,
            'required_roles' => $requiredRoles
        ]);

        return $requiredRoles;
    }

    /**
     * Recalculate workflow when amount changes.
     */
    public function recalculateWorkflow(Document $document, float|string $newAmount): array
    {
        $oldAmount = $document->amount;
        $template = $document->template;

        if (!$template->requiresAmount()) {
            return [
                'changed' => false,
                'old_roles' => [],
                'new_roles' => []
            ];
        }

        $oldRoles = $oldAmount ? $this->getRequiredRoles($template, (float) $oldAmount) : [];
        $newRoles = $this->getRequiredRoles($template, (float) $newAmount);

        $changed = $oldRoles !== $newRoles;

        if ($changed) {
            Log::warning('Amount change requires workflow recalculation', [
                'document_id' => $document->id,
                'old_amount' => $oldAmount,
                'new_amount' => $newAmount,
                'old_roles' => $oldRoles,
                'new_roles' => $newRoles
            ]);
        }

        return [
            'changed' => $changed,
            'old_roles' => $oldRoles,
            'new_roles' => $newRoles,
            'added_roles' => array_diff($newRoles, $oldRoles),
            'removed_roles' => array_diff($oldRoles, $newRoles)
        ];
    }

    /**
     * Get threshold details for an amount.
     */
    public function getThresholdDetails(Template $template, float|string $amount): ?array
    {
        $threshold = $template->getThresholdForAmount((float) $amount);

        if (!$threshold) {
            return null;
        }

        return [
            'min_amount' => $threshold->min_amount,
            'max_amount' => $threshold->max_amount,
            'required_roles' => $threshold->required_roles,
            'threshold_id' => $threshold->id
        ];
    }

    /**
     * Check if user assignment satisfies threshold requirements.
     */
    public function validateUserAssignments(Template $template, float|string $amount, array $userAssignments): bool
    {
        $requiredRoles = $this->getRequiredRoles($template, (float) $amount);
        $assignedRoles = array_keys($userAssignments);

        // Check if all required roles are assigned
        foreach ($requiredRoles as $requiredRole) {
            if (!in_array($requiredRole, $assignedRoles)) {
                Log::error('Missing required role assignment', [
                    'template_id' => $template->id,
                    'amount' => $amount,
                    'required_role' => $requiredRole,
                    'assigned_roles' => $assignedRoles
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Get all thresholds for a template with examples.
     */
    public function getThresholdMatrix(Template $template): array
    {
        return $template->thresholds()
            ->orderBy('min_amount')
            ->get()
            ->map(function ($threshold) {
                return [
                    'range' => $this->formatRange($threshold),
                    'min' => $threshold->min_amount,
                    'max' => $threshold->max_amount,
                    'roles' => $threshold->required_roles,
                    'example_amount' => $this->getExampleAmount($threshold)
                ];
            })
            ->toArray();
    }

    /**
     * Format threshold range as human-readable string.
     */
    protected function formatRange(TemplateThreshold $threshold): string
    {
        if ($threshold->max_amount === null) {
            return '> ' . number_format((float) $threshold->min_amount, 2);
        }

        return number_format((float) $threshold->min_amount, 2) . ' - ' . number_format((float) $threshold->max_amount, 2);
    }

    /**
     * Get example amount for threshold (midpoint).
     */
    protected function getExampleAmount(TemplateThreshold $threshold): float
    {
        $min = (float) $threshold->min_amount;

        if ($threshold->max_amount === null) {
            return $min * 1.5; // 50% above minimum
        }

        $max = (float) $threshold->max_amount;
        return ($min + $max) / 2;
    }
}
