<?php

namespace App\Jobs;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ArchiveExpiredDocuments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Running ArchiveExpiredDocuments Job');

        // Find documents that are:
        // 1. COMPLETED or VOIDED
        // 2. Not Archived
        // 3. Not on Legal Hold
        // 4. Past their retention period
        $expiredDocs = Document::whereIn('status', ['COMPLETED', 'VOIDED'])
            ->whereNull('archived_at')
            ->where('is_legal_hold', false)
            // Use safe standard SQL or bindings if possible.
            // Since interval is dynamic (column), we use safe string interpolation assuming column name is safe.
            ->whereRaw("completed_at < NOW() - (COALESCE(retention_period_days, 3650) || ' DAY')::INTERVAL")
            ->get();

        foreach ($expiredDocs as $doc) {
            Log::info("Archiving Document {$doc->id} (Expired). Retention: {$doc->retention_period_days} days.");

            // Archival Action:
            // For MVP, we just mark as archived and soft delete (if using SoftDeletes trait)
            // In real prod, this might move file to Glacier/Cold Storage.
            $doc->update([
                'archived_at' => now(),
                'status' => 'ARCHIVED'
            ]);

            // Optional: $doc->delete(); // Soft delete
        }

        Log::info("Archived " . $expiredDocs->count() . " documents.");
    }
}
