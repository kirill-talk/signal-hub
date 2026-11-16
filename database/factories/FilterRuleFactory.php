<?php

namespace Database\Factories;

use App\Models\FilterRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FilterRule>
 */
class FilterRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source_id' => null,
            'topic_id' => null,
            'type' => fake()->randomElement(['include', 'exclude', 'highlight']),
            'match_field' => fake()->randomElement(['title', 'summary', 'both']),
            'keywords' => fake()->words(3),
            'is_active' => true,
        ];
    }
}
