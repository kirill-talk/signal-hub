<?php

namespace Database\Factories;

use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'topic_id' => null,
            'name' => fake()->company().' Feed',
            'url' => fake()->unique()->url().'/feed.xml',
            'adapter_type' => fake()->randomElement(['rss', 'github', 'json']),
            'fetch_interval_minutes' => fake()->randomElement([15, 30, 60, 180]),
            'last_fetched_at' => now()->subMinutes(fake()->numberBetween(5, 120)),
            'etag' => null,
            'last_modified' => null,
            'status' => 'active',
            'error_count' => 0,
            'last_error' => null,
        ];
    }

    public function failing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failing',
            'error_count' => 3,
            'last_error' => 'HTTP 503 Service Unavailable',
        ]);
    }
}
