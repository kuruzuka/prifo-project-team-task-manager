<?php

namespace Database\Seeders;

use App\Models\TaskStatus;
use Illuminate\Database\Seeder;

class TaskStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            'To Do',
            'In Progress',
            'In Review',
            'Done',
            'Blocked',
        ];

        foreach ($statuses as $status) {
            TaskStatus::firstOrCreate(['name' => $status]);
        }
    }
}
