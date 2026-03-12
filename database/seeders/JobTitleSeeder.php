<?php

namespace Database\Seeders;

use App\Models\JobTitle;
use Illuminate\Database\Seeder;

class JobTitleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jobTitles = [
            [
                'name' => 'Software Engineer',
                'description' => 'Designs, develops, and maintains software applications.',
            ],
            [
                'name' => 'Senior Software Engineer',
                'description' => 'Leads development of complex features and mentors junior engineers.',
            ],
            [
                'name' => 'Lead Developer',
                'description' => 'Oversees technical direction and architecture decisions for projects.',
            ],
            [
                'name' => 'Product Manager',
                'description' => 'Defines product vision and roadmap, prioritizes features.',
            ],
            [
                'name' => 'Project Manager',
                'description' => 'Plans, executes, and closes projects while managing resources.',
            ],
            [
                'name' => 'UX Designer',
                'description' => 'Creates user-centered designs and improves user experience.',
            ],
            [
                'name' => 'UI Designer',
                'description' => 'Designs visual interfaces and maintains design systems.',
            ],
            [
                'name' => 'DevOps Engineer',
                'description' => 'Manages CI/CD pipelines, infrastructure, and deployment.',
            ],
            [
                'name' => 'QA Engineer',
                'description' => 'Ensures software quality through testing and automation.',
            ],
            [
                'name' => 'Data Analyst',
                'description' => 'Analyzes data to provide insights and support decision-making.',
            ],
            [
                'name' => 'Technical Lead',
                'description' => 'Guides technical implementation and coordinates with stakeholders.',
            ],
            [
                'name' => 'Engineering Manager',
                'description' => 'Manages engineering teams and drives technical excellence.',
            ],
        ];

        foreach ($jobTitles as $jobTitle) {
            JobTitle::firstOrCreate(
                ['name' => $jobTitle['name']],
                ['description' => $jobTitle['description']]
            );
        }
    }
}
