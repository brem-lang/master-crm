<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
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
            'request_id' => fake()->uuid(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->safeEmail(),
            'mobile' => fake()->e164PhoneNumber(),
            'country_code' => fake()->countryCode(),
            'ip_address' => fake()->ipv4(),
            'status' => fake()->randomElement(['new', 'contacted', 'rejected', 'converted']),
            'affiliate_name' => fake()->company(),
            'is_ftd' => fake()->boolean(),
            'offer_name' => fake()->words(2, true),
            'meta' => [],
            'lead_created_at' => fake()->dateTimeBetween('-30 days'),
            'synced_at' => now(),
        ];
    }
}
