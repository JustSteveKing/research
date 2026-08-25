<?php

namespace Database\Factories;

use App\Models\ResearchRun;
use App\Models\RunEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RunEvent>
 */
class RunEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'research_run_id' => ResearchRun::factory(),
            'sequence' => fake()->numberBetween(0, 50),
            'event' => 'searching:start',
            'message' => fake()->sentence(),
            'data' => [],
        ];
    }
}
