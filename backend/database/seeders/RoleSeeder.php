<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'display_name' => 'System Administrator',
                'description' => 'Full system access',
                'permissions' => ['*'],
            ],
            [
                'name' => 'finance_officer',
                'display_name' => 'Assistant Finance Officer',
                'description' => 'Uploads invoices, configures signers',
                'permissions' => [
                    'document:upload',
                    'document:view',
                    'document:configure',
                ],
            ],
            [
                'name' => 'finance_manager',
                'display_name' => 'Finance Manager',
                'description' => 'Reviews and approves financial documents',
                'permissions' => [
                    'document:view',
                    'document:sign',
                    'document:reject',
                ],
            ],
            [
                'name' => 'secretary_general',
                'display_name' => 'Secretary General',
                'description' => 'Final approval for high value items',
                'permissions' => [
                    'document:view',
                    'document:sign',
                    'document:sign:gold', // Special permission for strict signing
                ],
            ],
            [
                'name' => 'exco',
                'display_name' => 'Executive Committee',
                'description' => 'Read-only access and high-level signing',
                'permissions' => [
                    'document:view',
                    'document:sign',
                ],
            ],
            [
                'name' => 'auditor',
                'display_name' => 'Auditor',
                'description' => 'Read-only audit access',
                'permissions' => [
                    'document:view',
                    'audit:view',
                    'audit:export',
                ],
            ],
            [
                'name' => 'external_signer',
                'display_name' => 'External Signer',
                'description' => 'External party signing via link',
                'permissions' => [
                    'document:view:assigned',
                    'document:sign:assigned',
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            Role::updateOrCreate(['name' => $roleData['name']], $roleData);
        }

        // Create a default admin user
        $adminRole = Role::where('name', 'admin')->first();
        User::updateOrCreate(
            ['email' => 'admin@sadcpf.org'],
            [
                'name' => 'SADC Admin',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'mfa_enabled' => false,
            ]
        );
    }
}
