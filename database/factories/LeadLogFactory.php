<?php

namespace Database\Factories;

use App\Models\LeadLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadLog>
 */
class LeadLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'offer_name' => fake()->words(2, true),
            'data' => ['country' => fake()->countryCode(), 'source' => fake()->word()],
            'ip_address' => fake()->ipv4(),
        ];
    }
}
