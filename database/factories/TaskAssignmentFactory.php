<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskAssignment>
 */
class TaskAssignmentFactory extends Factory
{
    protected $model = TaskAssignment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::inRandomOrder()->first()?->id ?? Task::factory(),
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'assigned_by' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'assigned_date' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Set the assigned task.
     */
    public function forTask(Task $task): static
    {
        return $this->state(fn (array $attributes) => [
            'task_id' => $task->id,
        ]);
    }

    /**
     * Set the assignee.
     */
    public function toUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Set who assigned the task.
     */
    public function assignedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_by' => $user->id,
        ]);
    }
}
