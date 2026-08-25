<?php

namespace Database\Factories;

use App\Models\Source;
use App\Models\Thread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = fake()->unique()->url();

        return [
            'thread_id' => Thread::factory(),
            'url' => $url,
            'domain' => parse_url($url, PHP_URL_HOST),
            'title' => fake()->sentence(5),
            'summary' => fake()->paragraph(),
            'relevance' => 'high',
            'reliability' => 'medium',
            'claims' => [fake()->sentence(), fake()->sentence()],
            'cited_count' => 1,
        ];
    }
}
