<?php

namespace Database\Factories;

use App\Models\Contradiction;
use App\Models\Fact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contradiction>
 */
class ContradictionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fact_id' => Fact::factory(),
            'other_fact_id' => Fact::factory(),
            'similarity' => 0.78,
            'note' => fake()->sentence(10),
        ];
    }
}
