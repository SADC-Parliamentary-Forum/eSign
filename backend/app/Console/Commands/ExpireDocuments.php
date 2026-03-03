<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Notifications\DocumentExpiredNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire overdue in-progress documents and notify their owners';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for expired documents...');

        $expiredIds = Document::where('status', 'IN_PROGRESS')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->pluck('id');

        if ($expiredIds->isEmpty()) {
            $this->info('No expired documents found.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($expiredIds as $documentId) {
            try {
                /** @var Document $document */
                $document = Document::with('user')->find($documentId);
                if (!$document) {
                    continue;
                }

                $document->update(['status' => 'VOIDED']);

                // Notify the document owner
                if ($document->user) {
                    try {
                        $document->user->notify(
                            new DocumentExpiredNotification($document)
                        );
                    } catch (\Exception $e) {
                        Log::warning("Failed to notify owner of expired document {$document->id}: " . $e->getMessage());
                    }
                }

                $count++;
                $this->line("  Expired: [{$document->id}] {$document->title}");
            } catch (\Exception $e) {
                Log::error("Failed to expire document {$documentId}: " . $e->getMessage());
                $this->error("  Failed: [{$documentId}] " . $e->getMessage());
            }
        }

        $this->info("Done. Expired {$count} document(s).");
        return self::SUCCESS;
    }
}
