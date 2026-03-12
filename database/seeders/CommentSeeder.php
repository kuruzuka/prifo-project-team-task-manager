<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
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

        // Realistic comment templates
        $commentTemplates = [
            'I\'ve started working on this. Will update when I have more progress.',
            'Can someone clarify the requirements for this task?',
            'This is blocked by the API integration. Need to wait for that to be completed.',
            'Just completed the first phase. Moving on to the next step.',
            'Found a bug while testing. Will need some additional time to fix.',
            'Great progress so far! Keep up the good work.',
            'I\'ve pushed the changes to the feature branch for review.',
            'Can we schedule a quick sync to discuss this?',
            'Updated the documentation to reflect the new changes.',
            'This looks good to me. Ready for QA testing.',
            'Added some unit tests to cover the edge cases.',
            'The design mockups have been uploaded to Figma.',
        ];

        foreach ($tasks as $task) {
            // Create 2 comments per task
            $commentsToCreate = 2;

            for ($i = 0; $i < $commentsToCreate; $i++) {
                Comment::create([
                    'task_id' => $task->id,
                    'user_id' => $users->random()->id,
                    'comment_text' => fake()->randomElement($commentTemplates),
                ]);
            }
        }
    }
}
