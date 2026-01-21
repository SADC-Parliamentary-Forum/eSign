<?php

namespace App\Services;

use App\Models\Template;
use App\Models\TemplateRole;
use App\Models\TemplateFieldMapping;
use App\Models\TemplateThreshold;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TemplateService
{
    /**
     * Create a new template.
     */
    public function createTemplate(array $data): Template
    {
        DB::beginTransaction();
        try {
            $template = Template::create([
                'user_id' => $data['user_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'file_path' => $data['file_path'],
                'file_hash' => $data['file_hash'] ?? hash_file('sha256', $data['file_path']),
                'workflow_type' => $data['workflow_type'] ?? 'SEQUENTIAL',
                'is_public' => $data['is_public'] ?? false,
                'amount_required' => $data['amount_required'] ?? false,
                'status' => 'DRAFT', // Templates start as draft
                'version' => 1,
            ]);

            // Generate document fingerprint for AI matching
            if (isset($data['file_path'])) {
                $fingerprint = $this->generateDocumentFingerprint($data['file_path']);
                $template->update(['document_fingerprint' => $fingerprint]);
            }

            DB::commit();

            Log::info('Template created', ['template_id' => $template->id, 'user_id' => $data['user_id']]);

            return $template->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create template', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Update template.
     */
    public function updateTemplate(Template $template, array $data): Template
    {
        // Prevent updates to non-draft templates without proper workflow
        if ($template->status !== 'DRAFT' && !isset($data['allow_update'])) {
            throw new \RuntimeException('Cannot update template in ' . $template->status . ' status');
        }

        $template->update($data);

        Log::info('Template updated', ['template_id' => $template->id]);

        return $template->fresh();
    }

    /**
     * Add roles to template.
     */
    public function addRoles(Template $template, array $roles): void
    {
        DB::beginTransaction();
        try {
            foreach ($roles as $roleData) {
                TemplateRole::create([
                    'template_id' => $template->id,
                    'role' => $roleData['role'],
                    'action' => $roleData['action'] ?? 'SIGN',
                    'required' => $roleData['required'] ?? true,
                    'signing_order' => $roleData['signing_order'] ?? 1,
                ]);
            }

            DB::commit();
            Log::info('Roles added to template', ['template_id' => $template->id, 'roles_count' => count($roles)]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add roles to template', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Add field mappings to template.
     */
    public function addFieldMappings(Template $template, array $mappings): void
    {
        DB::beginTransaction();
        try {
            foreach ($mappings as $mapping) {
                TemplateFieldMapping::create([
                    'template_id' => $template->id,
                    'role' => $mapping['role'],
                    'page' => $mapping['page'] ?? 1,
                    'x' => $mapping['x'],
                    'y' => $mapping['y'],
                    'width' => $mapping['width'],
                    'height' => $mapping['height'],
                ]);
            }

            DB::commit();
            Log::info('Field mappings added to template', ['template_id' => $template->id, 'mappings_count' => count($mappings)]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add field mappings to template', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Add financial thresholds to template.
     */
    public function addThresholds(Template $template, array $thresholds): void
    {
        DB::beginTransaction();
        try {
            foreach ($thresholds as $threshold) {
                TemplateThreshold::create([
                    'template_id' => $template->id,
                    'min_amount' => $threshold['min_amount'],
                    'max_amount' => $threshold['max_amount'] ?? null,
                    'required_roles' => $threshold['required_roles'],
                ]);
            }

            DB::commit();
            Log::info('Thresholds added to template', ['template_id' => $template->id, 'thresholds_count' => count($thresholds)]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add thresholds to template', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Submit template for review.
     */
    public function submitForReview(Template $template): void
    {
        if ($template->status !== 'DRAFT') {
            throw new \RuntimeException('Only draft templates can be submitted for review');
        }

        // Validate template has necessary components
        if ($template->roles()->count() === 0) {
            throw new \RuntimeException('Template must have at least one role');
        }

        if ($template->fieldMappings()->count() === 0) {
            throw new \RuntimeException('Template must have at least one field mapping');
        }

        $template->update(['status' => 'REVIEW']);

        Log::info('Template submitted for review', ['template_id' => $template->id]);
    }

    /**
     * Approve template.
     */
    public function approveTemplate(Template $template, User $approver): void
    {
        if ($template->status !== 'REVIEW') {
            throw new \RuntimeException('Only templates in review can be approved');
        }

        $template->update([
            'status' => 'APPROVED',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        Log::info('Template approved', ['template_id' => $template->id, 'approver_id' => $approver->id]);
    }

    /**
     * Activate template (make it available for use).
     */
    public function activateTemplate(Template $template): void
    {
        if ($template->status !== 'APPROVED') {
            throw new \RuntimeException('Only approved templates can be activated');
        }

        $template->update(['status' => 'ACTIVE']);

        Log::info('Template activated', ['template_id' => $template->id]);
    }

    /**
     * Archive template.
     */
    public function archiveTemplate(Template $template): void
    {
        $template->update(['status' => 'ARCHIVED']);

        Log::info('Template archived', ['template_id' => $template->id]);
    }

    /**
     * Generate document fingerprint for AI matching.
     * This is a simplified version. Real implementation would analyze PDF structure.
     */
    protected function generateDocumentFingerprint(string $filePath): string
    {
        // In production, this would:
        // 1. Extract page dimensions
        // 2. Identify text blocks (hashed)
        // 3. Calculate layout geometry
        // 4. Create composite fingerprint

        // Simplified version using file hash
        return hash_file('sha256', $filePath);
    }

    /**
     * Create new version of template.
     */
    public function createVersion(Template $originalTemplate, array $changes): Template
    {
        DB::beginTransaction();
        try {
            $newTemplate = $originalTemplate->replicate();
            $newTemplate->version = $originalTemplate->version + 1;
            $newTemplate->status = 'DRAFT';
            $newTemplate->fill($changes);
            $newTemplate->save();

            // Copy roles
            foreach ($originalTemplate->roles as $role) {
                $newRole = $role->replicate();
                $newRole->template_id = $newTemplate->id;
                $newRole->save();
            }

            // Copy field mappings
            foreach ($originalTemplate->fieldMappings as $mapping) {
                $newMapping = $mapping->replicate();
                $newMapping->template_id = $newTemplate->id;
                $newMapping->save();
            }

            // Copy visual fields (TemplateField)
            foreach ($originalTemplate->fields as $field) {
                $newField = $field->replicate();
                $newField->template_id = $newTemplate->id;
                $newField->save();
            }

            // Copy thresholds
            foreach ($originalTemplate->thresholds as $threshold) {
                $newThreshold = $threshold->replicate();
                $newThreshold->template_id = $newTemplate->id;
                $newThreshold->save();
            }

            DB::commit();

            Log::info('Template version created', [
                'original_id' => $originalTemplate->id,
                'new_id' => $newTemplate->id,
                'version' => $newTemplate->version
            ]);

            return $newTemplate;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create template version', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
