<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Role;

class PromoteUserToAdmin extends Command
{
    protected $signature = 'user:promote-admin {email? : User email} {--force : Bypass confirmation}';

    protected $description = 'Upgrade a user to Administrator';

    public function handle()
    {
        $email = $this->argument('email') ?? $this->ask('User email');

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error('User not found.');
            return 1;
        }

        $adminRole = Role::where('name', 'admin')->first();
        if (!$adminRole) {
            $this->error("Admin role not found. Run RoleSeeder.");
            return 1;
        }

        if ($user->role_id === $adminRole->id) {
            $this->info('User is already an administrator.');
            return 0;
        }

        if (!$this->option('force')) {
            if (!$this->confirm("Promote {$email} to ADMINISTRATOR?")) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $user->update([
            'role_id' => $adminRole->id,
            'mfa_enabled' => true,
        ]);

        // Invalidate cache
        \Illuminate\Support\Facades\Cache::forget("user.{$user->id}");

        $this->info("User promoted to Administrator.");
        return 0;
    }
}
