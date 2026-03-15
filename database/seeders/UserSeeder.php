<?php

namespace Database\Seeders;

use App\Models\JobTitle;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::where('name', 'Admin')->first();
        $developerRole = Role::where('name', 'Developer')->first();
        $managerRole = Role::where('name', 'Manager')->first();
        $memberRole = Role::where('name', 'Member')->first();

        // Get job titles for assigning
        $engineeringManager = JobTitle::where('name', 'Engineering Manager')->first();
        $softwareEngineer = JobTitle::where('name', 'Software Engineer')->first();
        $projectManager = JobTitle::where('name', 'Project Manager')->first();
        $jobTitles = JobTitle::all();

        // Create admin user with Engineering Manager title
        $admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@test.com',
            'job_title_id' => $engineeringManager?->id,
        ]);
        if ($adminRole) {
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }

        // Create developer user with Software Engineer title
        $developer = User::factory()->create([
            'first_name' => 'Developer',
            'last_name' => 'User',
            'email' => 'developer@test.com',
            'password' => 'password', // Password hashing is handled by user factory/model casts if set up, or manually
            'job_title_id' => $softwareEngineer?->id ?? $jobTitles->random()?->id,
        ]);
        if ($developerRole) {
            $developer->roles()->syncWithoutDetaching([$developerRole->id]);
        }

        // Create 3 managers with Project Manager titles
        $managers = User::factory(3)->create([
            'job_title_id' => $projectManager?->id,
        ]);
        foreach ($managers as $manager) {
            if ($managerRole) {
                $manager->roles()->syncWithoutDetaching([$managerRole->id]);
            }
        }

        // Create 12 regular members with random job titles
        $members = User::factory(12)->create();
        foreach ($members as $member) {
            if ($memberRole) {
                $member->roles()->syncWithoutDetaching([$memberRole->id]);
            }
            // Assign a random job title if not already set
            if ($member->job_title_id === null && $jobTitles->isNotEmpty()) {
                $member->update(['job_title_id' => $jobTitles->random()->id]);
            }
        }

        // Update existing users without job titles
        User::whereNull('job_title_id')
            ->each(function (User $user) use ($jobTitles) {
                if ($jobTitles->isNotEmpty()) {
                    $user->update(['job_title_id' => $jobTitles->random()->id]);
                }
            });
    }
}
