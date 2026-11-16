<?php

namespace Tests\Feature;

use App\Jobs\FetchSourceJob;
use App\Livewire\SourceManager;
use App\Models\Source;
use App\Models\User;
use App\Services\EntryIngestionService;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class PhaseThreeQueueAndRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_manager_fetch_now_dispatches_job_and_blocks_foreign_user(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownSource = Source::factory()->create([
            'user_id' => $user->id,
            'name' => 'Laravel Releases',
        ]);

        $foreignSource = Source::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Private Feed',
        ]);

        Livewire::actingAs($user)
            ->test(SourceManager::class)
            ->call('fetchNow', $ownSource->id)
            ->assertSee('Fetch queued for Laravel Releases.');

        Queue::assertPushed(FetchSourceJob::class, function (FetchSourceJob $job) use ($ownSource): bool {
            return $job->source->id === $ownSource->id;
        });

        Livewire::actingAs($user)
            ->test(SourceManager::class)
            ->call('fetchNow', $foreignSource->id)
            ->assertNotFound();

        Queue::assertPushed(FetchSourceJob::class, 1);
    }

    public function test_schedule_dispatches_only_due_sources(): void
    {
        Queue::fake();

        $neverFetched = Source::factory()->create([
            'name' => 'Never Fetched',
            'last_fetched_at' => null,
            'fetch_interval_minutes' => 60,
        ]);

        $expired = Source::factory()->create([
            'name' => 'Expired Interval',
            'last_fetched_at' => now()->subMinutes(65),
            'fetch_interval_minutes' => 60,
        ]);

        $fresh = Source::factory()->create([
            'name' => 'Fresh Source',
            'last_fetched_at' => now()->subMinutes(10),
            'fetch_interval_minutes' => 60,
        ]);

        $this->artisan('schedule:run')->assertSuccessful();

        Queue::assertPushed(FetchSourceJob::class, 2);
        Queue::assertPushed(FetchSourceJob::class, fn (FetchSourceJob $job): bool => $job->source->id === $neverFetched->id);
        Queue::assertPushed(FetchSourceJob::class, fn (FetchSourceJob $job): bool => $job->source->id === $expired->id);
        Queue::assertNotPushed(FetchSourceJob::class, fn (FetchSourceJob $job): bool => $job->source->id === $fresh->id);
    }

    public function test_fetch_source_job_uses_etag_and_handles_304_not_modified(): void
    {
        $atom = file_get_contents(base_path('tests/Fixtures/github_releases.atom'));
        $this->assertIsString($atom);

        Http::fakeSequence('github.com/*')
            ->push($atom, 200, [
                'ETag' => '"v13-etag"',
                'Last-Modified' => 'Mon, 16 Nov 2026 10:00:00 GMT',
            ])
            ->push('', 304);

        $source = Source::factory()->create([
            'url' => 'https://github.com/laravel/framework/releases.atom',
            'adapter_type' => 'github',
            'last_fetched_at' => null,
            'etag' => null,
            'last_modified' => null,
        ]);

        (new FetchSourceJob($source))->handle(app(EntryIngestionService::class));

        $source->refresh();
        $this->assertSame('"v13-etag"', $source->etag);
        $this->assertSame('Mon, 16 Nov 2026 10:00:00 GMT', $source->last_modified);
        $this->assertDatabaseCount('entries', 2);

        $previousFetchTime = $source->last_fetched_at;
        $this->travel(5)->minutes();

        (new FetchSourceJob($source))->handle(app(EntryIngestionService::class));

        Http::assertSent(function (Request $request): bool {
            return ($request->header('If-None-Match')[0] ?? null) === '"v13-etag"'
                && ($request->header('If-Modified-Since')[0] ?? null) === 'Mon, 16 Nov 2026 10:00:00 GMT';
        });

        $source->refresh();
        $this->assertDatabaseCount('entries', 2);
        $this->assertSame('active', $source->status);
        $this->assertSame(0, $source->error_count);
        $this->assertTrue($source->last_fetched_at->greaterThan($previousFetchTime));
    }

    public function test_fetch_source_job_records_failure_on_http_error_and_rethrows(): void
    {
        Http::fake([
            'throttled.example.com/*' => Http::response('Too Many Requests', 429),
        ]);

        $source = Source::factory()->create([
            'url' => 'https://throttled.example.com/feed.xml',
            'adapter_type' => 'rss',
            'status' => 'active',
            'error_count' => 0,
            'last_error' => null,
        ]);

        $job = new FetchSourceJob($source);

        try {
            $job->handle(app(EntryIngestionService::class));
            $this->fail('Expected RequestException was not thrown.');
        } catch (RequestException) {
            // Expected exception for queue retry/backoff
        }

        $source->refresh();
        $this->assertSame('failing', $source->status);
        $this->assertSame(1, $source->error_count);
        $this->assertNotNull($source->last_error);
        $this->assertStringContainsString('429', $source->last_error);
    }

    public function test_fetch_source_job_releases_back_to_queue_when_rate_limit_exceeded(): void
    {
        Http::fake();

        $key = 'fetch-domain:rate-limited.example.com';
        RateLimiter::clear($key);

        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit($key, 60);
        }

        $source = Source::factory()->create([
            'url' => 'https://rate-limited.example.com/feed.xml',
            'adapter_type' => 'rss',
        ]);

        $mockQueueJob = Mockery::mock(QueueJobContract::class);
        $mockQueueJob->shouldReceive('release')->once()->with(15);

        $job = new FetchSourceJob($source);
        $job->setJob($mockQueueJob);

        $job->handle(app(EntryIngestionService::class));

        Http::assertNothingSent();
        RateLimiter::clear($key);
    }
}
