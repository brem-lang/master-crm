<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\JobRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobRun>
 */
class JobRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'triggered_by' => fake()->randomElement(['manual', 'scheduled']),
            'success' => true,
            'pulled' => fake()->numberBetween(0, 20),
            'message' => 'Pulled some leads.',
            'attempt' => null,
        ];
    }
}
