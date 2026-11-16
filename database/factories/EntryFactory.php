<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entry>
 */
class EntryFactory extends Factory
{
    public function definition(): array
    {
        $url = fake()->unique()->url();

        return [
            'user_id' => User::factory(),
            'source_id' => fn (array $attributes) => Source::factory()->state([
                'user_id' => $attributes['user_id'],
            ]),
            'topic_id' => null,
            'external_id_hash' => hash('sha256', fake()->unique()->uuid().':'.$url),
            'title' => fake()->sentence(6),
            'url' => $url,
            'author' => fake()->name(),
            'summary' => fake()->paragraph(3),
            'published_at' => now()->subHours(fake()->numberBetween(1, 72)),
            'is_read' => false,
            'is_bookmarked' => false,
            'is_noise' => false,
            'is_highlighted' => false,
            'raw_payload' => null,
        ];
    }

    public function noise(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_noise' => true,
            'is_highlighted' => false,
        ]);
    }

    public function highlighted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_noise' => false,
            'is_highlighted' => true,
        ]);
    }
}
