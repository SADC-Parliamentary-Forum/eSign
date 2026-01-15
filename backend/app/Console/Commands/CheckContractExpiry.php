<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Notifications\ContractExpiringNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckContractExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:check-expiry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for contracts expiring soon and notify owners';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expiring contracts...');

        // Find contracts expiring in exactly 30, 14, or 7 days
        $contracts = Contract::where('status', 'active')
            ->whereIn('end_date', [
                    now()->addDays(30)->toDateString(),
                    now()->addDays(14)->toDateString(),
                    now()->addDays(7)->toDateString(),
                ])
            ->with('document.user')
            ->get();

        foreach ($contracts as $contract) {
            $user = $contract->document->user; // The contract owner
            if ($user) {
                $user->notify(new ContractExpiringNotification($contract));
                $this->info("Notified {$user->email} for Contract {$contract->reference_number}");
            }
        }

        $this->info('Done.');
    }
}
