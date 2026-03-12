<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $teamTypes = [
            'Engineering',
            'Design',
            'Marketing',
            'Sales',
            'Product',
            'Operations',
            'Support',
            'Research',
            'Quality Assurance',
            'DevOps',
        ];

        return [
            'name' => fake()->unique()->randomElement($teamTypes) . ' Team',
            'description' => fake()->paragraph(),
        ];
    }
}
