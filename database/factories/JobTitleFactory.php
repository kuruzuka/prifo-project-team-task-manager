<?php

namespace Database\Factories;

use App\Models\JobTitle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JobTitle>
 */
class JobTitleFactory extends Factory
{
    protected $model = JobTitle::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jobTitles = [
            'Software Engineer',
            'Senior Software Engineer',
            'Lead Developer',
            'Product Manager',
            'Project Manager',
            'UX Designer',
            'UI Designer',
            'DevOps Engineer',
            'QA Engineer',
            'Data Analyst',
            'Technical Lead',
            'Engineering Manager',
        ];

        return [
            'name' => fake()->unique()->randomElement($jobTitles),
            'description' => fake()->sentence(),
        ];
    }
}
