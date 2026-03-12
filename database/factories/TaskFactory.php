<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::inRandomOrder()->first()?->id ?? Project::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(2),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'progress' => fake()->numberBetween(0, 100),
            'status_id' => TaskStatus::inRandomOrder()->first()?->id ?? TaskStatus::factory(),
            'due_date' => fake()->dateTimeBetween('now', '+3 months'),
        ];
    }

    /**
     * Set the task project.
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
        ]);
    }

    /**
     * Set the task status.
     */
    public function withStatus(TaskStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => $status->id,
        ]);
    }

    /**
     * Create a high priority task.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }

    /**
     * Create a completed task.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'progress' => 100,
        ]);
    }
}
