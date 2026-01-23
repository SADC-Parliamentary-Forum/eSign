<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\OrganizationalRole;

class OrganizationalStructureSeeder extends Seeder
{
    public function run(): void
    {
        // Departments
        $departments = [
            ['name' => 'Executive Office', 'code' => 'EXEC', 'description' => 'Secretary General and Deputy SG'],
            ['name' => 'Finance', 'code' => 'FIN', 'description' => 'Finance and Accounts'],
            ['name' => 'Human Resources', 'code' => 'HR', 'description' => 'Human Resources Management'],
            ['name' => 'ICT', 'code' => 'ICT', 'description' => 'Information and Communications Technology'],
            ['name' => 'Legal', 'code' => 'LEGAL', 'description' => 'Legal Affairs'],
            ['name' => 'Administration', 'code' => 'ADMIN', 'description' => 'General Administration'],
            ['name' => 'Programmes', 'code' => 'PROG', 'description' => 'Programme Management'],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(['code' => $dept['code']], $dept);
        }

        // Organizational Roles (ordered by level - 1 is highest)
        $roles = [
            ['name' => 'Secretary General', 'code' => 'SG', 'level' => 1, 'description' => 'Chief Executive Officer'],
            ['name' => 'Deputy Secretary General', 'code' => 'DSG', 'level' => 2, 'description' => 'Deputy to the SG'],
            ['name' => 'Director Finance', 'code' => 'DIR_FIN', 'level' => 3, 'description' => 'Head of Finance'],
            ['name' => 'Director HR', 'code' => 'DIR_HR', 'level' => 3, 'description' => 'Head of Human Resources'],
            ['name' => 'Director ICT', 'code' => 'DIR_ICT', 'level' => 3, 'description' => 'Head of ICT'],
            ['name' => 'Director Programmes', 'code' => 'DIR_PROG', 'level' => 3, 'description' => 'Head of Programmes'],
            ['name' => 'Director Legal', 'code' => 'DIR_LEGAL', 'level' => 3, 'description' => 'Head of Legal Affairs'],
            ['name' => 'Director Administration', 'code' => 'DIR_ADMIN', 'level' => 3, 'description' => 'Head of Administration'],
            ['name' => 'Deputy Director', 'code' => 'DEP_DIR', 'level' => 4, 'description' => 'Deputy Director'],
            ['name' => 'Manager', 'code' => 'MGR', 'level' => 5, 'description' => 'Department Manager'],
            ['name' => 'Senior Officer', 'code' => 'SNR_OFF', 'level' => 6, 'description' => 'Senior Officer'],
            ['name' => 'Officer', 'code' => 'OFF', 'level' => 7, 'description' => 'Officer'],
            ['name' => 'Assistant', 'code' => 'ASST', 'level' => 8, 'description' => 'Assistant'],
            ['name' => 'Requester', 'code' => 'REQ', 'level' => 10, 'description' => 'Document Requester/Initiator'],
        ];

        foreach ($roles as $role) {
            OrganizationalRole::firstOrCreate(['code' => $role['code']], $role);
        }

        $this->command->info('Organizational structure seeded successfully!');
    }
}
