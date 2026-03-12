<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskActivityLog>
 */
class TaskActivityLogFactory extends Factory
{
    protected $model = TaskActivityLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::inRandomOrder()->first()?->id ?? Task::factory(),
            'activity_type' => fake()->randomElement([
                'created',
                'updated',
                'status_changed',
                'assigned',
                'priority_changed',
                'progress_updated',
            ]),
            'metadata' => null,
            'actor_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
        ];
    }

    /**
     * Set the task.
     */
    public function forTask(Task $task): static
    {
        return $this->state(fn (array $attributes) => [
            'task_id' => $task->id,
        ]);
    }

    /**
     * Set the actor.
     */
    public function byActor(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'actor_id' => $user->id,
        ]);
    }

    /**
     * Status change activity.
     */
    public function statusChanged(string $oldStatus, string $newStatus): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_type' => 'status_changed',
            'metadata' => [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ],
        ]);
    }

    /**
     * Task assigned activity.
     */
    public function assigned(User $assignee): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_type' => 'assigned',
            'metadata' => [
                'assignee_id' => $assignee->id,
                'assignee_name' => $assignee->first_name . ' ' . $assignee->last_name,
            ],
        ]);
    }

    /**
     * Progress updated activity.
     */
    public function progressUpdated(int $oldProgress, int $newProgress): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_type' => 'progress_updated',
            'metadata' => [
                'old_progress' => $oldProgress,
                'new_progress' => $newProgress,
            ],
        ]);
    }
}
