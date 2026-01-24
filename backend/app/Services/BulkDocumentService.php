<?php

namespace App\Services;

use App\Models\Template;
use App\Models\BulkBatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class BulkDocumentService
{
    protected $templateService;
    protected $documentService;

    public function __construct(TemplateService $templateService, DocumentService $documentService)
    {
        $this->templateService = $templateService;
        $this->documentService = $documentService;
    }

    /**
     * Process a bulk creation request from a CSV file.
     */
    public function processBatch(User $user, Template $template, UploadedFile $file): BulkBatch
    {
        // 1. Create Batch Record
        $path = $file->store('bulk-imports');

        $batch = BulkBatch::create([
            'template_id' => $template->id,
            'user_id' => $user->id,
            'status' => 'PROCESSING',
            'source_file_path' => $path,
            'total_count' => 0
        ]);

        // 2. Parse CSV
        $rows = $this->parseCsv($file->getRealPath());
        $batch->update(['total_count' => count($rows)]);

        // 3. Validate Headers against Template Roles
        // Convention: RoleName_Name, RoleName_Email
        // Also support field filling? Field_Label?
        // For phase 1, focus on Signer Assignments.

        // 4. Process Rows
        $success = 0;
        $errors = 0;
        $errorDetails = [];

        foreach ($rows as $index => $row) {
            try {
                $this->createDocumentFromRow($user, $template, $row, $batch->id);
                $success++;
            } catch (\Exception $e) {
                $errors++;
                $errorDetails[] = [
                    'row' => $index + 2, // 1-based + header
                    'error' => $e->getMessage(),
                    'data' => $row
                ];
                Log::error("Bulk create failed for row $index: " . $e->getMessage());
            }

            // Update progress occasionally or at end
            if ($index % 10 === 0) {
                $batch->update([
                    'processed_count' => $index + 1,
                    'success_count' => $success,
                    'error_count' => $errors
                ]);
            }
        }

        $batch->update([
            'status' => $errors > 0 ? 'COMPLETED_WITH_ERRORS' : 'COMPLETED',
            'processed_count' => count($rows),
            'success_count' => $success,
            'error_count' => $errors,
            'errors' => $errorDetails
        ]);

        return $batch;
    }

    protected function parseCsv($filePath): array
    {
        $csv = array_map('str_getcsv', file($filePath));
        $header = array_shift($csv);

        // Sanitize headers (trim, lowercase?)
        // Let's keep original case for role matching but trim
        $header = array_map('trim', $header);

        $data = [];
        foreach ($csv as $row) {
            if (count($row) !== count($header))
                continue; // Skip malformed
            $data[] = array_combine($header, $row);
        }

        return $data;
    }

    protected function createDocumentFromRow(User $user, Template $template, array $row, string $batchId)
    {
        // Map CSV columns to assignments
        // Assignments format expected by TemplateController::apply / TemplateService::createInstance
        // assignments: [{ template_role_id: uuid, name: string, email: string }]

        $assignments = [];
        foreach ($template->roles as $role) {
            // Find columns for this role
            // Expected: "Role Name_Name" and "Role Name_Email"
            // Note: Spaces in role names in DB? Yes.
            // CSV might use underscores?

            $roleName = $role->role ?? 'Signer ' . $role->signing_order; // Fallback

            // Try Exact Match
            $nameKey = $roleName . '_Name';
            $emailKey = $roleName . '_Email';

            // Case insensitive search in keys?
            // For now simple match

            if (isset($row[$nameKey]) && isset($row[$emailKey])) {
                $assignments[] = [
                    'template_role_id' => $role->id,
                    'name' => $row[$nameKey],
                    'email' => $row[$emailKey],
                    'user_id' => null // Resolve simple user lookup if needed?
                ];
            } else {
                if ($role->is_required) {
                    throw new \Exception("Missing Name/Email for required role: {$roleName}");
                }
            }
        }

        if (empty($assignments)) {
            throw new \Exception("No valid role assignments found in row.");
        }

        // Delegate to existing logic?
        // We can replicate logic or call a service method if extracted.
        // Since TemplateController::apply logic is inline, I should probably extract it to TemplateService first
        // OR duplicate it here for "Bulk" specific needs (speed, batch ID).
        // Let's duplicate core "Create from Template" logic slightly modified for Bulk.

        DB::transaction(function () use ($user, $template, $assignments, $batchId) {
            // 1. Create Doc
            $document = \App\Models\Document::create([
                'user_id' => $user->id,
                'title' => $template->name, // Maybe append unique ID from row?
                'status' => 'DRAFT', // or SENT directly? PRD says independent lifecycle.
                'file_path' => $template->file_path,
                'file_hash' => $template->file_hash,
                'mime_type' => 'application/pdf',
                'size' => 0, // Should be actual size, but using 0 for bulk draft as per template logic
                'sequential_signing' => ($template->workflow_type === 'SEQUENTIAL'),
                'bulk_batch_id' => $batchId,
                // ... other fields
            ]);

            // 2. Add Signers
            foreach ($assignments as $assign) {
                $templateRole = $template->roles->firstWhere('id', $assign['template_role_id']);
                \App\Models\DocumentSigner::create([
                    'document_id' => $document->id,
                    'email' => $assign['email'],
                    'name' => $assign['name'],
                    'organizational_role_id' => $templateRole->organizational_role_id,
                    'signing_order' => $templateRole->signing_order,
                ]);
            }

            // 3. Copy Fields
            foreach ($template->fields as $field) {
                // Match signer
                $signer = \App\Models\DocumentSigner::where('document_id', $document->id)
                    ->where('organizational_role_id', $field->organizational_role_id)
                    ->first();

                \App\Models\DocumentField::create([
                    'document_id' => $document->id,
                    'document_signer_id' => $signer?->id,
                    'signer_email' => $signer?->email,
                    'type' => strtoupper($field->type),
                    'page_number' => $field->page_number,
                    'x_position' => $field->x_position,
                    'y_position' => $field->y_position,
                    'width' => $field->width,
                    'height' => $field->height,
                    'required' => $field->required,
                    'label' => $field->label,
                ]);
            }

            // 4. Auto-Send? 
            // Depending on requirement. Usually Bulk = Send Immediately?
            // Let's leave as DRAFT for safety or make it configurable. 
            // PRD says "Execution creation < 2s".
            // If we send, we trigger emails which is slow.
            // Better: Create as DRAFT, then have a "Start Batch" or async queue to send.
            // OR: Create as SENT but queue email jobs.

            $document->update(['status' => 'IN_PROGRESS', 'sent_at' => now()]);

            // Queue Notification (Todo)
        });
    }
}
