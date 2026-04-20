<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            OrganizationalStructureSeeder::class,
        ]);

        if (!app()->environment(['local', 'development'])) {
            return;
        }

        $adminRole = Role::where('name', 'admin')->first();
        if (!$adminRole) {
            return;
        }

        $adminEmail = env('DEV_ADMIN_EMAIL', 'admin@esign.local');
        $adminPassword = env('DEV_ADMIN_PASSWORD', 'Admin@12345');

        User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => env('DEV_ADMIN_NAME', 'Dev Admin'),
                'password' => Hash::make($adminPassword),
                'role_id' => $adminRole->id,
                'status' => 'ACTIVE',
                'mfa_enabled' => false,
                'email_verified_at' => now(),
            ]
        );
    }
}
