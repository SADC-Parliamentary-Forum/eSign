<?php

namespace App\Services;

use App\Models\ComplianceRule;
use App\Models\Document;
use Illuminate\Support\Facades\Log;

class ComplianceService
{
    /**
     * Evaluate compliance rules for a document and apply actions.
     */
    public function evaluateRules(Document $document)
    {
        $rules = ComplianceRule::active()->get();

        foreach ($rules as $rule) {
            if ($rule->matches($document)) {
                $this->applyActions($document, $rule->actions, $rule->name);
            }
        }
    }

    /**
     * Apply actions from a rule.
     */
    protected function applyActions(Document $document, array $actions, string $ruleName)
    {
        Log::info("Applying Compliance Rule: {$ruleName} to Document {$document->id}");

        if (isset($actions['retention_days'])) {
            $document->update(['retention_period_days' => $actions['retention_days']]);
        }

        // Add more actions here (e.g. notify_email, flag_risk, etc.)
    }

    /**
     * Toggle Legal Hold status.
     */
    public function toggleLegalHold(Document $document, bool $status, ?string $reason = null)
    {
        $document->update([
            'is_legal_hold' => $status,
            'legal_hold_reason' => $status ? $reason : null,
        ]);

        Log::info("Legal Hold " . ($status ? "Enabled" : "Disabled") . " for Document {$document->id}. Reason: {$reason}");
    }

    /**
     * Check if document should be archived (Retention Policy).
     */
    public function shouldArchive(Document $document): bool
    {
        if ($document->is_legal_hold) {
            return false;
        }

        if ($document->status !== 'COMPLETED') {
            return false; // Only archive completed docs
        }

        if (!$document->completed_at) {
            return false;
        }

        // Use document specific retention or default (10 years)
        $days = $document->retention_period_days ?? 3650;

        return $document->completed_at->addDays($days)->isPast();
    }
}
