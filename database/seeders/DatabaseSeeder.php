<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Seeders are called in dependency order:
     * 1. Roles, TaskStatuses, ProjectStatuses, JobTitles (no dependencies)
     * 2. Users (depends on roles, job titles)
     * 3. Teams (depends on users)
     * 4. Projects (depends on statuses, teams, users as managers)
     * 5. Tasks (depends on projects, statuses)
     * 6. TaskAssignments (depends on tasks, users)
     * 7. Comments (depends on tasks, users)
     * 8. ActivityLogs (depends on all models)
     */
    public function run(): void
    {
        $this->call([
            // Foundation: statuses, roles, and job titles (no dependencies)
            RoleSeeder::class,
            TaskStatusSeeder::class,
            ProjectStatusSeeder::class,
            JobTitleSeeder::class,

            // Users (depends on roles, job titles)
            UserSeeder::class,

            // Teams (depends on users)
            TeamSeeder::class,

            // Projects (depends on statuses, teams, users as managers)
            ProjectSeeder::class,

            // Tasks (depends on projects, statuses)
            TaskSeeder::class,

            // Task assignments (depends on tasks, users)
            TaskAssignmentSeeder::class,

            // Comments (depends on tasks, users)
            CommentSeeder::class,

            // Activity logs (depends on all models)
            ActivityLogSeeder::class,
        ]);
    }
}
