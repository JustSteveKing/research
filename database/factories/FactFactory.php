<?php

namespace Database\Factories;

use App\Models\Fact;
use App\Models\Thread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fact>
 */
class FactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'thread_id' => Thread::factory(),
            'statement' => fake()->sentence(12),
            'tags' => [fake()->word()],
            'confidence' => 0.8,
            'embedding' => null,
            'asserted_at' => now(),
        ];
    }

    public function pinned(): static
    {
        return $this->state(fn (): array => ['pinned_at' => now()]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (): array => ['dismissed_at' => now()]);
    }

    public function stale(): static
    {
        return $this->state(fn (): array => [
            'asserted_at' => now()->subDays((int) config('research.stale_after_days') + 30),
        ]);
    }

    /**
     * @param  array<int, float>  $vector
     */
    public function withEmbedding(array $vector): static
    {
        return $this->state(fn (): array => ['embedding' => $vector]);
    }
}
