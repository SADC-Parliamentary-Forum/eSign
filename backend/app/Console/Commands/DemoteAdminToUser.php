<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Role;

class DemoteAdminToUser extends Command
{
    protected $signature = 'user:demote-admin {email? : Admin email} {--force : Bypass confirmation}';

    protected $description = 'Downgrade an administrator to normal user';

    public function handle()
    {
        $email = $this->argument('email') ?? $this->ask('Admin email');

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error('User not found.');
            return 1;
        }

        if (!$this->option('force')) {
            if (!$this->confirm("Demote {$email} to NORMAL USER?")) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $user->update([
            'role_id' => null, // Normal users have no specific role
            'mfa_enabled' => false,
        ]);

        // Invalidate cache
        \Illuminate\Support\Facades\Cache::forget("user.{$user->id}");

        $this->info("User downgraded to normal user.");
        return 0;
    }
}
