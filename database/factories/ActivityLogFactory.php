<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $loggable = $this->getRandomLoggable();

        return [
            'loggable_type' => get_class($loggable),
            'loggable_id' => $loggable->id,
            'activity_type' => fake()->randomElement([
                'created',
                'updated',
                'status_changed',
                'assigned',
                'comment_added',
            ]),
            'metadata' => null,
            'actor_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
        ];
    }

    /**
     * Get a random loggable model (Task or Project).
     */
    protected function getRandomLoggable(): Model
    {
        if (fake()->boolean() && Task::exists()) {
            return Task::inRandomOrder()->first();
        }

        if (Project::exists()) {
            return Project::inRandomOrder()->first();
        }

        return Task::factory()->create();
    }

    /**
     * Set the loggable model.
     */
    public function forLoggable(Model $model): static
    {
        return $this->state(fn (array $attributes) => [
            'loggable_type' => get_class($model),
            'loggable_id' => $model->id,
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
     * Task created activity.
     */
    public function taskCreated(Task $task, User $actor): static
    {
        return $this->state(fn (array $attributes) => [
            'loggable_type' => Task::class,
            'loggable_id' => $task->id,
            'activity_type' => 'task_created',
            'metadata' => [
                'task_title' => $task->title,
                'project_id' => $task->project_id,
            ],
            'actor_id' => $actor->id,
        ]);
    }

    /**
     * Task assigned activity.
     */
    public function taskAssigned(Task $task, User $assignee, User $actor): static
    {
        return $this->state(fn (array $attributes) => [
            'loggable_type' => Task::class,
            'loggable_id' => $task->id,
            'activity_type' => 'task_assigned',
            'metadata' => [
                'assignee_id' => $assignee->id,
                'assignee_name' => $assignee->first_name . ' ' . $assignee->last_name,
            ],
            'actor_id' => $actor->id,
        ]);
    }

    /**
     * Status changed activity.
     */
    public function statusChanged(Model $model, string $oldStatus, string $newStatus, User $actor): static
    {
        return $this->state(fn (array $attributes) => [
            'loggable_type' => get_class($model),
            'loggable_id' => $model->id,
            'activity_type' => 'status_changed',
            'metadata' => [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ],
            'actor_id' => $actor->id,
        ]);
    }

    /**
     * Comment added activity.
     */
    public function commentAdded(Task $task, User $actor): static
    {
        return $this->state(fn (array $attributes) => [
            'loggable_type' => Task::class,
            'loggable_id' => $task->id,
            'activity_type' => 'comment_added',
            'metadata' => [
                'comment_preview' => fake()->sentence(),
            ],
            'actor_id' => $actor->id,
        ]);
    }

    /**
     * Member added to team activity.
     */
    public function memberAddedToTeam(User $member, User $actor, int $teamId, string $teamName): static
    {
        return $this->state(fn (array $attributes) => [
            'loggable_type' => User::class,
            'loggable_id' => $member->id,
            'activity_type' => 'member_added_to_team',
            'metadata' => [
                'team_id' => $teamId,
                'team_name' => $teamName,
                'member_name' => $member->first_name . ' ' . $member->last_name,
            ],
            'actor_id' => $actor->id,
        ]);
    }
}
