<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = ProjectStatus::all();
        $teams = Team::all();

        // Get users with Manager role to assign as project managers
        $managerRole = Role::where('name', 'Manager')->first();
        $managers = $managerRole
            ? User::whereHas('roles', fn ($q) => $q->where('roles.id', $managerRole->id))->get()
            : User::all();

        // Fallback to any user if no managers exist
        if ($managers->isEmpty()) {
            $managers = User::all();
        }

        $projects = [
            [
                'name' => 'Website Redesign',
                'description' => 'Complete overhaul of the company website with modern design and improved UX.',
            ],
            [
                'name' => 'Mobile App Development',
                'description' => 'Build a native mobile application for iOS and Android platforms.',
            ],
            [
                'name' => 'API Integration',
                'description' => 'Integrate third-party APIs for payment processing and analytics.',
            ],
            [
                'name' => 'Customer Portal',
                'description' => 'Self-service portal for customers to manage their accounts and subscriptions.',
            ],
            [
                'name' => 'Data Analytics Dashboard',
                'description' => 'Real-time analytics dashboard for business intelligence and reporting.',
            ],
        ];

        foreach ($projects as $projectData) {
            $startDate = fake()->dateTimeBetween('-2 months', 'now');
            $endDate = fake()->dateTimeBetween($startDate, '+4 months');

            $project = Project::withoutGlobalScopes()->firstOrCreate(
                ['name' => $projectData['name']],
                [
                    'description' => $projectData['description'],
                    'status_id' => $statuses->random()->id,
                    'manager_id' => $managers->isNotEmpty() ? $managers->random()->id : null,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]
            );

            // Update manager if project already exists but has no manager
            if ($project->wasRecentlyCreated === false && $project->manager_id === null && $managers->isNotEmpty()) {
                $project->update(['manager_id' => $managers->random()->id]);
            }

            // Assign 1-2 teams to each project
            if ($teams->isNotEmpty()) {
                $projectTeams = $teams->random(fake()->numberBetween(1, min(2, $teams->count())));
                $project->teams()->syncWithoutDetaching($projectTeams->pluck('id'));
            }
        }
    }
}
