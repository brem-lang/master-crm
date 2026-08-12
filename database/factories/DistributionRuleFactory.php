<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DistributionRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DistributionRule>
 */
class DistributionRuleFactory extends Factory
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
            'external_id' => fake()->unique()->uuid(),
            'affiliate_id' => fake()->uuid(),
            'advertiser_id' => fake()->uuid(),
            'country_code' => fake()->countryCode(),
            'weight' => fake()->numberBetween(1, 100),
            'daily_cap' => fake()->numberBetween(100, 1000),
            'hourly_cap' => fake()->numberBetween(10, 100),
            'is_active' => true,
            'priority_type' => fake()->randomElement(['primary', 'fallback']),
            'priority' => fake()->numberBetween(1, 200),
            'start_time' => null,
            'end_time' => null,
            'weekly_schedule' => null,
            'timezone' => null,
            'meta' => [],
            'synced_at' => now(),
        ];
    }
}
