<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tasks = Task::all();
        $users = User::all();

        if ($users->isEmpty()) {
            return;
        }

        foreach ($tasks as $task) {
            // Assign 1-3 random users to each task
            $assigneeCount = fake()->numberBetween(1, min(3, $users->count()));
            $assignees = $users->random($assigneeCount);
            $assigner = $users->random();

            foreach ($assignees as $assignee) {
                // Avoid duplicate assignments
                if (! TaskAssignment::where('task_id', $task->id)->where('user_id', $assignee->id)->exists()) {
                    TaskAssignment::create([
                        'task_id' => $task->id,
                        'user_id' => $assignee->id,
                        'assigned_by' => $assigner->id,
                        'assigned_date' => fake()->dateTimeBetween('-1 month', 'now'),
                    ]);
                }
            }
        }
    }
}
