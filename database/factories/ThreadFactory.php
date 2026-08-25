<?php

namespace Database\Factories;

use App\Enums\ResearchMode;
use App\Enums\ThreadStatus;
use App\Models\Thread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Thread>
 */
class ThreadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $question = fake()->sentence().'?';

        return [
            'title' => str(fake()->sentence(4))->rtrim('.')->toString(),
            'seed_question' => $question,
            'status' => ThreadStatus::Idle,
            'mode' => ResearchMode::Fast,
            'credit_budget' => 2000,
            'credits_reserved' => 0,
            'credits_spent' => 0,
            'auto_follow_up' => true,
            'max_depth' => 3,
        ];
    }

    public function autopilotOff(): static
    {
        return $this->state(fn (): array => ['auto_follow_up' => false]);
    }

    public function budget(int $credits): static
    {
        return $this->state(fn (): array => ['credit_budget' => $credits]);
    }
}
