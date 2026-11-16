<?php

namespace Tests\Feature;

use App\Livewire\FilterRuleManager;
use App\Models\Entry;
use App\Models\FilterRule;
use App\Models\Source;
use App\Models\Topic;
use App\Models\User;
use App\Services\EntryIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class FilterRuleManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_and_authenticated_users_can_view_rules_page(): void
    {
        $this->get(route('rules.index'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('rules.index'))->assertOk();
    }

    public function test_user_can_create_edit_toggle_and_delete_filter_rules(): void
    {
        $user = User::factory()->create();
        $topic = Topic::factory()->create(['user_id' => $user->id]);
        $source = Source::factory()->create(['user_id' => $user->id, 'topic_id' => $topic->id]);

        Livewire::actingAs($user)
            ->test(FilterRuleManager::class)
            ->set('type', 'exclude')
            ->set('match_field', 'both')
            ->set('source_id', $source->id)
            ->set('topic_id', $topic->id)
            ->set('keywords', ['hiring', ' meme ', 'hiring'])
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $rule = FilterRule::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('exclude', $rule->type);
        $this->assertSame(['hiring', 'meme'], $rule->keywords);
        $this->assertTrue($rule->is_active);

        // Toggle pause/active
        Livewire::actingAs($user)
            ->test(FilterRuleManager::class)
            ->call('toggleActive', $rule->id);

        $this->assertFalse($rule->fresh()->is_active);

        // Edit rule
        Livewire::actingAs($user)
            ->test(FilterRuleManager::class)
            ->call('edit', $rule->id)
            ->set('type', 'highlight')
            ->set('keywords', ['CVE', 'security'])
            ->call('save')
            ->assertHasNoErrors();

        $rule->refresh();
        $this->assertSame('highlight', $rule->type);
        $this->assertSame(['CVE', 'security'], $rule->keywords);

        // Delete rule
        Livewire::actingAs($user)
            ->test(FilterRuleManager::class)
            ->call('delete', $rule->id);

        $this->assertDatabaseMissing('filter_rules', ['id' => $rule->id]);
    }

    public function test_preview_rule_counts_matching_existing_entries(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->create(['user_id' => $user->id]);

        Entry::factory()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'title' => 'Monthly Hiring Thread',
            'summary' => 'Post open jobs here.',
        ]);

        Entry::factory()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'title' => 'Laravel 13.2.0 Released',
            'summary' => 'New queue improvements.',
        ]);

        Entry::factory()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'title' => 'Friday Meme Roundup',
            'summary' => 'Just for fun.',
        ]);

        Livewire::actingAs($user)
            ->test(FilterRuleManager::class)
            ->set('match_field', 'title')
            ->set('keywords', ['hiring', 'meme'])
            ->call('previewRule')
            ->assertSet('previewMatchCount', 2)
            ->assertSee('2');
    }

    public function test_entry_ingestion_applies_noise_and_highlight_rules(): void
    {
        $atom = file_get_contents(base_path('tests/Fixtures/github_releases.atom'));
        $this->assertIsString($atom);

        Http::fake([
            'github.com/*' => Http::response($atom, 200),
        ]);

        $user = User::factory()->create();
        $source = Source::factory()->create([
            'user_id' => $user->id,
            'url' => 'https://github.com/laravel/framework/releases.atom',
            'adapter_type' => 'github',
        ]);

        // v13.1.0 -> matches exclude rule ("RateLimiter") -> noise
        FilterRule::factory()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'type' => 'exclude',
            'match_field' => 'summary',
            'keywords' => ['RateLimiter'],
            'is_active' => true,
        ]);

        // v13.0.9 (Security Patch) -> matches highlight rule ("Security Patch") -> highlighted signal
        FilterRule::factory()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'type' => 'highlight',
            'match_field' => 'title',
            'keywords' => ['Security Patch'],
            'is_active' => true,
        ]);

        $created = app(EntryIngestionService::class)->ingest($source);
        $this->assertSame(2, $created);

        $this->assertDatabaseHas('entries', [
            'source_id' => $source->id,
            'title' => 'v13.1.0',
            'is_noise' => true,
            'is_highlighted' => false,
        ]);

        $this->assertDatabaseHas('entries', [
            'source_id' => $source->id,
            'title' => 'v13.0.9 (Security Patch)',
            'is_noise' => false,
            'is_highlighted' => true,
        ]);
    }
}
