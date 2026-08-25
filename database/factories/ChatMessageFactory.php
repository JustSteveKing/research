<?php

namespace Database\Factories;

use App\Enums\ChatRole;
use App\Models\ChatMessage;
use App\Models\Thread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'thread_id' => Thread::factory(),
            'role' => ChatRole::User,
            'content' => fake()->sentence(),
            'citations' => [],
        ];
    }
}
