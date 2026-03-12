<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = TaskStatus::all();
        $projects = Project::all();

        // Predefined task templates for realistic data
        $taskTemplates = [
            ['title' => 'Set up development environment', 'priority' => 'high'],
            ['title' => 'Create database schema', 'priority' => 'high'],
            ['title' => 'Implement user authentication', 'priority' => 'critical'],
            ['title' => 'Design wireframes', 'priority' => 'medium'],
            ['title' => 'Build REST API endpoints', 'priority' => 'high'],
            ['title' => 'Write unit tests', 'priority' => 'medium'],
            ['title' => 'Configure CI/CD pipeline', 'priority' => 'medium'],
            ['title' => 'Performance optimization', 'priority' => 'low'],
            ['title' => 'Documentation update', 'priority' => 'low'],
            ['title' => 'Security audit', 'priority' => 'critical'],
            ['title' => 'UI component library', 'priority' => 'medium'],
            ['title' => 'Integration testing', 'priority' => 'medium'],
        ];

        foreach ($projects as $project) {
            // Create 8 tasks per project
            $selectedTemplates = collect($taskTemplates)->shuffle()->take(8);

            foreach ($selectedTemplates as $template) {
                Task::firstOrCreate(
                    [
                        'project_id' => $project->id,
                        'title' => $template['title'],
                    ],
                    [
                        'description' => fake()->paragraph(2),
                        'priority' => $template['priority'],
                        'progress' => fake()->randomElement([0, 25, 50, 75, 100]),
                        'status_id' => $statuses->random()->id,
                        'due_date' => fake()->dateTimeBetween('now', '+3 months'),
                    ]
                );
            }
        }
    }
}
