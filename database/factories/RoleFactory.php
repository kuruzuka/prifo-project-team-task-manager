<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'description' => fake()->sentence(),
        ];
    }

    /**
     * Admin role state.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Admin',
            'description' => 'Full system access with all administrative privileges',
        ]);
    }

    /**
     * Manager role state.
     */
    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Manager',
            'description' => 'Project and team management capabilities',
        ]);
    }

    /**
     * Member role state.
     */
    public function member(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Member',
            'description' => 'Standard team member with basic access',
        ]);
    }
}
