<?php

namespace Database\Factories;

use App\Enums\ResearchMode;
use App\Enums\RunStatus;
use App\Models\ResearchRun;
use App\Models\Thread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResearchRun>
 */
class ResearchRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'thread_id' => Thread::factory(),
            'query' => fake()->sentence().'?',
            'mode' => ResearchMode::Fast,
            'depth' => 0,
            'status' => RunStatus::Queued,
            'credits_cost' => ResearchMode::Fast->creditCost(),
        ];
    }

    public function completed(?string $report = null): static
    {
        return $this->state(fn (): array => [
            'status' => RunStatus::Completed,
            'report' => $report ?? fake()->paragraph(),
            'completed_at' => now(),
        ]);
    }
}
