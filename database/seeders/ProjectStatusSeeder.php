<?php

namespace Database\Seeders;

use App\Models\ProjectStatus;
use Illuminate\Database\Seeder;

class ProjectStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            'Planning',
            'In Progress',
            'On Hold',
            'Completed',
            'Cancelled',
        ];

        foreach ($statuses as $status) {
            ProjectStatus::firstOrCreate(['name' => $status]);
        }
    }
}
