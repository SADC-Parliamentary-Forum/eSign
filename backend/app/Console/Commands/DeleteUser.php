<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class DeleteUser extends Command
{
    protected $signature = 'user:delete 
        {email? : Email of the user}
        {--force : Permanently delete the user}';

    protected $description = 'Delete a user safely (soft delete by default)';

    public function handle()
    {
        $email = $this->argument('email') ?? $this->ask('User email');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error('User not found.');
            return 1;
        }

        if (!$this->option('force')) {
            if (!$this->confirm("Are you sure you want to delete {$user->email}?")) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        if ($this->option('force')) {
            $user->forceDelete();
            $this->warn("User permanently deleted.");
        } else {
            $user->delete();
            $this->info("User soft deleted.");
        }

        return 0;
    }
}
