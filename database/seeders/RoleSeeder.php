<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'description' => 'Full system access with all administrative privileges',
            ],
            [
                'name' => 'Developer',
                'description' => 'Internal developer with administrative and documentation access',
            ],
            [
                'name' => 'Manager',
                'description' => 'Project and team management capabilities',
            ],
            [
                'name' => 'Member',
                'description' => 'Standard team member with basic access',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']]
            );
        }
    }
}
