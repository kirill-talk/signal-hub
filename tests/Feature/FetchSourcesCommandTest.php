<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Source;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchSourcesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sources_fetch_command_ingests_entries_without_duplicates(): void
    {
        $atom = file_get_contents(base_path('tests/Fixtures/github_releases.atom'));
        $this->assertIsString($atom);

        Http::fake([
            'github.com/*' => Http::response($atom, 200),
        ]);

        $user = User::factory()->create();
        $topic = Topic::factory()->create([
            'user_id' => $user->id,
            'name' => 'Backend',
            'slug' => 'backend',
        ]);

        $source = Source::factory()->create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'name' => 'Laravel Releases',
            'url' => 'https://github.com/laravel/framework/releases.atom',
            'adapter_type' => 'github',
            'last_fetched_at' => null,
        ]);

        $this->artisan('sources:fetch', ['source_id' => $source->id])
            ->expectsOutputToContain('2 new entries saved.')
            ->assertSuccessful();

        $this->assertDatabaseCount('entries', 2);

        $entry = Entry::firstOrFail();
        $this->assertSame($topic->id, $entry->topic_id);
        $this->assertSame($user->id, $entry->user_id);

        // Running a second time must not create duplicate records
        $this->artisan('sources:fetch', ['source_id' => $source->id])
            ->expectsOutputToContain('0 new entries saved.')
            ->assertSuccessful();

        $this->assertDatabaseCount('entries', 2);

        $source->refresh();
        $this->assertSame('active', $source->status);
        $this->assertSame(0, $source->error_count);
        $this->assertNotNull($source->last_fetched_at);
    }

    public function test_sources_fetch_command_records_failure_when_http_request_fails(): void
    {
        Http::fake([
            'broken.example.com/*' => Http::response('Server Error', 500),
        ]);

        $source = Source::factory()->create([
            'name' => 'Broken Feed',
            'url' => 'https://broken.example.com/rss.xml',
            'adapter_type' => 'rss',
            'status' => 'active',
            'error_count' => 0,
        ]);

        $this->artisan('sources:fetch', ['source_id' => $source->id])
            ->expectsOutputToContain('Failed:')
            ->assertSuccessful();

        $source->refresh();
        $this->assertSame('failing', $source->status);
        $this->assertSame(1, $source->error_count);
        $this->assertNotNull($source->last_error);
        $this->assertDatabaseCount('entries', 0);
    }
}
