<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Document;
use App\Services\DocumentService;

class RestampDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'esign:restamp {id? : The ID of the document to restamp} {--all : Restamp all completed documents}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-apply signatures to completed documents (fixes missing stamps)';

    /**
     * Execute the console command.
     */
    public function handle(DocumentService $documentService)
    {
        $id = $this->argument('id');
        $all = $this->option('all');

        if (!$id && !$all) {
            $this->error('Please provide a document ID or use --all.');
            return 1;
        }

        $query = Document::where('status', 'COMPLETED');

        if ($id) {
            $query->where('id', $id);
        }

        $documents = $query->get();

        if ($documents->isEmpty()) {
            $this->info('No matching completed documents found.');
            return 0;
        }

        $bar = $this->output->createProgressBar($documents->count());
        $bar->start();

        foreach ($documents as $document) {
            try {
                // $this->info(" Restamping document: {$document->id} - {$document->title}");
                $documentService->finalizeDocument($document);
            } catch (\Exception $e) {
                $this->error(" Failed to restamp {$document->id}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Restamping complete.');
        return 0;
    }
}
