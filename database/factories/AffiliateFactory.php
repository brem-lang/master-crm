<?php

namespace Database\Factories;

use App\Models\Affiliate;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Affiliate>
 */
class AffiliateFactory extends Factory
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
            'api_key' => fake()->uuid(),
            'is_active' => true,
            'meta' => [],
            'synced_at' => now(),
        ];
    }
}
