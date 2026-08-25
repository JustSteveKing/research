<?php

namespace Database\Factories;

use App\Enums\QuestionOrigin;
use App\Enums\QuestionStatus;
use App\Models\FollowUpQuestion;
use App\Models\Thread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FollowUpQuestion>
 */
class FollowUpQuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'thread_id' => Thread::factory(),
            'question' => fake()->sentence().'?',
            'origin' => QuestionOrigin::Ollama,
            'status' => QuestionStatus::Pending,
            'score' => 0.7,
            'rationale' => fake()->sentence(),
        ];
    }
}
