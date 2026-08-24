<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
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
            'uuid' => Str::uuid()->toString(),
            'external_id' => null,
            'direction' => ChatMessage::DIRECTION_OUTBOUND,
            'sender_type' => ChatMessage::SENDER_MASTER,
            'sender_user_id' => null,
            'sender_name' => fake()->name(),
            'body' => fake()->sentence(),
            'status' => ChatMessage::STATUS_DELIVERED,
            'failure_reason' => null,
            'occurred_at' => now(),
            'synced_at' => now(),
        ];
    }
}
