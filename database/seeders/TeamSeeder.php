<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teams = [
            [
                'name' => 'Engineering Team',
                'description' => 'Responsible for building and maintaining the core platform infrastructure and features.',
            ],
            [
                'name' => 'Design Team',
                'description' => 'Creates and maintains the visual design system and user experience.',
            ],
            [
                'name' => 'Product Team',
                'description' => 'Defines product strategy, roadmap, and feature prioritization.',
            ],
        ];

        $users = User::all();

        foreach ($teams as $teamData) {
            $team = Team::firstOrCreate(
                ['name' => $teamData['name']],
                ['description' => $teamData['description']]
            );

            // Assign 5-8 random users to each team
            $teamMembers = $users->random(fake()->numberBetween(5, 8));
            $team->members()->syncWithoutDetaching($teamMembers->pluck('id'));
        }
    }
}
