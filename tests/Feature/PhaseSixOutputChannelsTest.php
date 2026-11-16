<?php

namespace Tests\Feature;

use App\Jobs\SendDigestMailJob;
use App\Livewire\ProfileSettings;
use App\Mail\DailyDigestMail;
use App\Models\Entry;
use App\Models\Source;
use App\Models\Topic;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class PhaseSixOutputChannelsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_private_rss_feed_returns_valid_xml_excluding_noise_and_filtering_by_topic(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $backend = Topic::factory()->create([
            'user_id' => $user->id,
            'name' => 'Backend',
            'slug' => 'backend',
        ]);

        $devops = Topic::factory()->create([
            'user_id' => $user->id,
            'name' => 'DevOps',
            'slug' => 'devops',
        ]);

        $source = Source::factory()->create(['user_id' => $user->id, 'topic_id' => $backend->id]);

        Entry::factory()->highlighted()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'topic_id' => $backend->id,
            'title' => 'Laravel 13 Security Patch',
            'summary' => '<p>Critical update notes.</p>',
        ]);

        Entry::factory()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'topic_id' => $devops->id,
            'title' => 'Kubernetes 1.32 Release',
        ]);

        Entry::factory()->noise()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'topic_id' => $backend->id,
            'title' => 'Noisy Meme Entry',
        ]);

        Entry::factory()->create([
            'user_id' => $otherUser->id,
            'title' => 'Other User Entry',
        ]);

        $response = $this->get(route('feeds.private', ['token' => $user->feed_token]));

        $response->assertOk();
        $this->assertStringContainsString('application/rss+xml', (string) $response->headers->get('Content-Type'));
        $response->assertSee('[Priority] Laravel 13 Security Patch');
        $response->assertSee('Kubernetes 1.32 Release');
        $response->assertDontSee('Noisy Meme Entry');
        $response->assertDontSee('Other User Entry');

        $xml = simplexml_load_string((string) $response->getContent());
        $this->assertNotFalse($xml);
        $this->assertCount(2, $xml->channel->item);

        // Topic-filtered RSS feed
        $topicResponse = $this->get(route('feeds.private.topic', [
            'token' => $user->feed_token,
            'topicSlug' => 'backend',
        ]));

        $topicResponse->assertOk();
        $topicResponse->assertSee('Laravel 13 Security Patch');
        $topicResponse->assertDontSee('Kubernetes 1.32 Release');

        // Invalid token returns 404
        $this->get(route('feeds.private', ['token' => 'non-existent-token']))->assertNotFound();
    }

    public function test_scheduled_digests_command_respects_user_timezone_and_prevents_duplicates(): void
    {
        Queue::fake();

        // 2026-11-16 06:35:00 UTC -> 08:35 in Europe/Kyiv (UTC+2), 01:35 in America/New_York (UTC-5)
        Carbon::setTestNow(Carbon::create(2026, 11, 16, 6, 35, 0, 'UTC'));

        $kyivUser = User::factory()->create([
            'timezone' => 'Europe/Kyiv',
            'digest_frequency' => 'daily',
            'digest_time' => '08:30',
            'last_digest_sent_at' => null,
        ]);

        $nyUser = User::factory()->create([
            'timezone' => 'America/New_York',
            'digest_frequency' => 'daily',
            'digest_time' => '08:30',
            'last_digest_sent_at' => null,
        ]);

        $alreadySentUser = User::factory()->create([
            'timezone' => 'Europe/Kyiv',
            'digest_frequency' => 'daily',
            'digest_time' => '08:30',
            'last_digest_sent_at' => Carbon::create(2026, 11, 16, 6, 31, 0, 'UTC'),
        ]);

        $disabledUser = User::factory()->create([
            'timezone' => 'Europe/Kyiv',
            'digest_frequency' => 'none',
            'digest_time' => '08:30',
        ]);

        $this->artisan('digests:send')
            ->expectsOutputToContain('Dispatched 1 digest jobs.')
            ->assertSuccessful();

        Queue::assertPushed(SendDigestMailJob::class, 1);
        Queue::assertPushed(SendDigestMailJob::class, fn (SendDigestMailJob $job): bool => $job->user->id === $kyivUser->id);
        Queue::assertNotPushed(SendDigestMailJob::class, fn (SendDigestMailJob $job): bool => $job->user->id === $nyUser->id);
        Queue::assertNotPushed(SendDigestMailJob::class, fn (SendDigestMailJob $job): bool => $job->user->id === $alreadySentUser->id);
        Queue::assertNotPushed(SendDigestMailJob::class, fn (SendDigestMailJob $job): bool => $job->user->id === $disabledUser->id);
    }

    public function test_send_digest_mail_job_groups_unread_signal_entries_and_skips_empty(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'digest@example.com',
            'digest_frequency' => 'daily',
            'last_digest_sent_at' => null,
        ]);

        $backend = Topic::factory()->create(['user_id' => $user->id, 'name' => 'Backend', 'slug' => 'backend']);
        $source = Source::factory()->create(['user_id' => $user->id, 'topic_id' => $backend->id]);

        Entry::factory()->highlighted()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'topic_id' => $backend->id,
            'title' => 'Important Digest Entry',
            'is_read' => false,
            'is_noise' => false,
        ]);

        Entry::factory()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'topic_id' => $backend->id,
            'title' => 'Already Read Entry',
            'is_read' => true,
            'is_noise' => false,
        ]);

        Entry::factory()->noise()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'topic_id' => $backend->id,
            'title' => 'Noisy Unread Entry',
            'is_read' => false,
        ]);

        (new SendDigestMailJob($user))->handle();

        Mail::assertSent(DailyDigestMail::class, function (DailyDigestMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && $mail->totalCount === 1
                && $mail->groupedEntries->has('Backend');
        });

        $rendered = (new DailyDigestMail(
            $user,
            collect(['Backend' => Entry::where('title', 'Important Digest Entry')->get()]),
            1
        ))->render();

        $this->assertStringContainsString('Important Digest Entry', $rendered);
        $this->assertNotNull($user->fresh()->last_digest_sent_at);

        // User with zero unread signal entries gets no email
        $emptyUser = User::factory()->create(['digest_frequency' => 'daily']);
        (new SendDigestMailJob($emptyUser))->handle();

        Mail::assertSent(DailyDigestMail::class, 1);
    }

    public function test_profile_settings_updates_preferences_and_regenerates_feed_token(): void
    {
        $user = User::factory()->create([
            'timezone' => 'UTC',
            'digest_frequency' => 'none',
            'digest_time' => '08:30',
        ]);

        $originalToken = $user->feed_token;

        Livewire::actingAs($user)
            ->test(ProfileSettings::class)
            ->set('timezone', 'Europe/Kyiv')
            ->set('digest_frequency', 'weekly')
            ->set('digest_time', '09:15')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Europe/Kyiv', $user->timezone);
        $this->assertSame('weekly', $user->digest_frequency);
        $this->assertSame('09:15', $user->digest_time);

        Livewire::actingAs($user)
            ->test(ProfileSettings::class)
            ->call('regenerateFeedToken');

        $user->refresh();
        $this->assertNotSame($originalToken, $user->feed_token);

        $this->get(route('feeds.private', ['token' => $originalToken]))->assertNotFound();
        $this->get(route('feeds.private', ['token' => $user->feed_token]))->assertOk();
    }
}
