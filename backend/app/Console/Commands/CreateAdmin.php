<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-admin {email? : The email of the user} {name? : The name of the user} {--password= : The password (optional, will ask if missing)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new admin user securely';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        if (!$email) {
            $email = $this->ask('Email Address');
        }

        if (User::where('email', $email)->exists()) {
            $this->error('User with this email already exists.');
            return 1;
        }

        $name = $this->argument('name');
        if (!$name) {
            $name = $this->ask('Data Owner Name (Display Name)');
        }

        $password = $this->option('password');
        if (!$password) {
            $password = $this->secret('Password');
            $confirm = $this->secret('Confirm Password');

            if ($password !== $confirm) {
                $this->error('Passwords do not match');
                return 1;
            }
        }

        // Basic Length Check
        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');
            return 1;
        }

        $adminRole = Role::where('name', 'admin')->first();
        if (!$adminRole) {
            $this->error("Role 'admin' not found. Please run RoleSeeder first.");
            return 1;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => $adminRole->id,
            'mfa_enabled' => true, // Enforce MFA for admins by default? Let's say yes for security.
            'email_verified_at' => now(), // Auto-verify admin created via CLI
        ]);

        $this->info("Admin user [{$email}] created successfully.");
        return 0;
    }
}
