<?php

namespace Tests\Unit;

use App\Models\FilterRule;
use App\Models\Source;
use App\Models\Topic;
use App\Models\User;
use App\Services\NoiseFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoiseFilterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_clean_non_highlighted_when_no_rules_exist(): void
    {
        $user = User::factory()->create();
        $service = new NoiseFilterService();

        $result = $service->evaluate($user->id, null, null, 'Laravel 13 Released', '<p>Details</p>');

        $this->assertSame([
            'is_noise' => false,
            'is_highlighted' => false,
        ], $result);
    }

    public function test_include_and_exclude_precedence_with_highlight(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->create(['user_id' => $user->id]);

        FilterRule::factory()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'topic_id' => null,
            'type' => 'include',
            'match_field' => 'both',
            'keywords' => ['laravel', 'security'],
            'is_active' => true,
        ]);

        FilterRule::factory()->create([
            'user_id' => $user->id,
            'source_id' => null,
            'topic_id' => null,
            'type' => 'exclude',
            'match_field' => 'both',
            'keywords' => ['hiring', 'meme'],
            'is_active' => true,
        ]);

        FilterRule::factory()->create([
            'user_id' => $user->id,
            'source_id' => null,
            'topic_id' => null,
            'type' => 'highlight',
            'match_field' => 'title',
            'keywords' => ['CVE', 'security'],
            'is_active' => true,
        ]);

        $service = new NoiseFilterService();

        // Matches include and highlight, does not match exclude -> signal & highlighted
        $this->assertSame(
            ['is_noise' => false, 'is_highlighted' => true],
            $service->evaluate($user->id, $source->id, null, 'Critical SECURITY Patch', '<p>Upgrade now</p>')
        );

        // Matches include, but also matches exclude -> noise & NOT highlighted
        $this->assertSame(
            ['is_noise' => true, 'is_highlighted' => false],
            $service->evaluate($user->id, $source->id, null, 'Laravel Security Meme', '<p>Funny CVE joke</p>')
        );

        // Does not match include -> noise
        $this->assertSame(
            ['is_noise' => true, 'is_highlighted' => false],
            $service->evaluate($user->id, $source->id, null, 'Rust Async Runtime', '<p>Tokyo update</p>')
        );
    }

    public function test_match_field_and_scope_isolation(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $topic = Topic::factory()->create(['user_id' => $user->id]);
        $otherTopic = Topic::factory()->create(['user_id' => $user->id]);

        // Only matches summary in $topic
        FilterRule::factory()->create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'type' => 'exclude',
            'match_field' => 'summary',
            'keywords' => ['sponsored'],
            'is_active' => true,
        ]);

        // Inactive rule should be ignored
        FilterRule::factory()->create([
            'user_id' => $user->id,
            'type' => 'exclude',
            'match_field' => 'title',
            'keywords' => ['paused'],
            'is_active' => false,
        ]);

        // Other user's rule should never apply
        FilterRule::factory()->create([
            'user_id' => $otherUser->id,
            'type' => 'exclude',
            'match_field' => 'title',
            'keywords' => ['laravel'],
            'is_active' => true,
        ]);

        $service = new NoiseFilterService();

        // Keyword in title, NOT summary -> not matched by 'summary' rule
        $this->assertFalse(
            $service->evaluate($user->id, null, $topic->id, 'Sponsored Laravel Release', '<p>Regular notes</p>')['is_noise']
        );

        // Keyword in summary for $topic -> matched as noise
        $this->assertTrue(
            $service->evaluate($user->id, null, $topic->id, 'Weekly Digest', '<p>This post is Sponsored.</p>')['is_noise']
        );

        // Keyword in summary, but different topic -> NOT matched
        $this->assertFalse(
            $service->evaluate($user->id, null, $otherTopic->id, 'Weekly Digest', '<p>This post is Sponsored.</p>')['is_noise']
        );

        // Paused rule and foreign user's rule -> NOT matched
        $this->assertFalse(
            $service->evaluate($user->id, null, $topic->id, 'Paused Laravel Feature', null)['is_noise']
        );
    }
}
