<?php

namespace Database\Factories;

use App\Models\Advertiser;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Advertiser>
 */
class AdvertiserFactory extends Factory
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
            'name' => fake()->company(),
            'advertiser_type' => fake()->randomElement(['api', 'mock', 'redirect']),
            'url' => fake()->url(),
            'api_key' => fake()->uuid(),
            'is_active' => true,
            'daily_cap' => fake()->numberBetween(100, 10000),
            'hourly_cap' => fake()->numberBetween(10, 500),
            'default_deal_type' => fake()->randomElement(['cpa', 'cpl', 'revshare']),
            'meta' => [],
            'synced_at' => now(),
        ];
    }
}
