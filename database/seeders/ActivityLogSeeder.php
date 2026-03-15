<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class ActivityLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $tasks = Task::withoutGlobalScopes()->with(['project', 'assignedUsers'])->get();
        $projects = Project::withoutGlobalScopes()->get();
        $teams = Team::withoutGlobalScopes()->with('members')->get();
        $taskStatuses = TaskStatus::all();

        if ($users->isEmpty()) {
            return;
        }

        // Seed task_created activities
        foreach ($tasks->take(15) as $task) {
            ActivityLog::create([
                'loggable_type' => Task::class,
                'loggable_id' => $task->id,
                'activity_type' => 'task_created',
                'metadata' => [
                    'task_title' => $task->title,
                    'project_id' => $task->project_id,
                    'project_name' => $task->project?->name,
                ],
                'actor_id' => $users->random()->id,
            ]);
        }

        // Seed task_assigned activities
        foreach ($tasks->take(20) as $task) {
            $assignee = $task->assignedUsers->first() ?? $users->random();
            ActivityLog::create([
                'loggable_type' => Task::class,
                'loggable_id' => $task->id,
                'activity_type' => 'task_assigned',
                'metadata' => [
                    'assignee_id' => $assignee->id,
                    'assignee_name' => $assignee->first_name . ' ' . $assignee->last_name,
                    'task_title' => $task->title,
                ],
                'actor_id' => $users->random()->id,
            ]);
        }

        // Seed status_changed activities for tasks
        foreach ($tasks->take(25) as $task) {
            $oldStatus = $taskStatuses->random();
            $newStatus = $taskStatuses->where('id', '!=', $oldStatus->id)->random();

            ActivityLog::create([
                'loggable_type' => Task::class,
                'loggable_id' => $task->id,
                'activity_type' => 'status_changed',
                'metadata' => [
                    'old_status' => $oldStatus->name,
                    'new_status' => $newStatus->name,
                    'task_title' => $task->title,
                ],
                'actor_id' => $users->random()->id,
            ]);
        }

        // Seed comment_added activities
        $comments = Comment::with(['task', 'user'])->get();
        foreach ($comments->take(30) as $comment) {
            ActivityLog::create([
                'loggable_type' => Task::class,
                'loggable_id' => $comment->task_id,
                'activity_type' => 'comment_added',
                'metadata' => [
                    'comment_preview' => substr($comment->comment_text, 0, 100),
                    'task_title' => $comment->task?->title,
                ],
                'actor_id' => $comment->user_id,
            ]);
        }

        // Seed member_added_to_team activities
        foreach ($teams as $team) {
            foreach ($team->members->take(3) as $member) {
                ActivityLog::create([
                    'loggable_type' => User::class,
                    'loggable_id' => $member->id,
                    'activity_type' => 'member_added_to_team',
                    'metadata' => [
                        'team_id' => $team->id,
                        'team_name' => $team->name,
                        'member_name' => $member->first_name . ' ' . $member->last_name,
                    ],
                    'actor_id' => $users->random()->id,
                ]);
            }
        }

        // Seed project activities
        foreach ($projects as $project) {
            // Project created
            ActivityLog::create([
                'loggable_type' => Project::class,
                'loggable_id' => $project->id,
                'activity_type' => 'project_created',
                'metadata' => [
                    'project_name' => $project->name,
                ],
                'actor_id' => $users->random()->id,
            ]);

            // Project status changed
            ActivityLog::create([
                'loggable_type' => Project::class,
                'loggable_id' => $project->id,
                'activity_type' => 'status_changed',
                'metadata' => [
                    'old_status' => 'Planning',
                    'new_status' => $project->status?->name ?? 'In Progress',
                    'project_name' => $project->name,
                ],
                'actor_id' => $users->random()->id,
            ]);
        }
    }
}
