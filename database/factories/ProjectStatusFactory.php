<?php

namespace Database\Factories;

use App\Models\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProjectStatus>
 */
class ProjectStatusFactory extends Factory
{
    protected $model = ProjectStatus::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Planning',
                'In Progress',
                'On Hold',
                'Completed',
                'Cancelled',
            ]),
        ];
    }
}
