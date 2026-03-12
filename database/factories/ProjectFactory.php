<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-2 months', '+1 month');
        $endDate = fake()->dateTimeBetween($startDate, '+6 months');

        return [
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'status_id' => ProjectStatus::inRandomOrder()->first()?->id ?? ProjectStatus::factory(),
            'manager_id' => User::inRandomOrder()->first()?->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    /**
     * Set the project status.
     */
    public function withStatus(ProjectStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => $status->id,
        ]);
    }

    /**
     * Set the project manager.
     */
    public function withManager(User $manager): static
    {
        return $this->state(fn (array $attributes) => [
            'manager_id' => $manager->id,
        ]);
    }
}
