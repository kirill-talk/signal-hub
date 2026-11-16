<?php

namespace Tests\Feature;

use App\Livewire\FeedHub;
use App\Models\Entry;
use App\Models\Source;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PhaseFiveFeedHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_and_authenticated_users_can_access_feed_hub(): void
    {
        $this->get(route('feed.index'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('feed.index'))->assertOk();
    }

    public function test_feed_hub_filters_by_tabs_topic_and_source(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $backend = Topic::factory()->create(['user_id' => $user->id, 'name' => 'Backend', 'slug' => 'backend']);
        $devops = Topic::factory()->create(['user_id' => $user->id, 'name' => 'DevOps', 'slug' => 'devops']);

        $sourceA = Source::factory()->create(['user_id' => $user->id, 'topic_id' => $backend->id, 'name' => 'Source A']);
        $sourceB = Source::factory()->create(['user_id' => $user->id, 'topic_id' => $devops->id, 'name' => 'Source B']);

        Entry::factory()->create([
            'user_id' => $user->id,
            'source_id' => $sourceA->id,
            'topic_id' => $backend->id,
            'title' => 'Signal Unread Entry',
            'is_read' => false,
            'is_bookmarked' => false,
            'is_noise' => false,
        ]);

        Entry::factory()->create([
            'user_id' => $user->id,
            'source_id' => $sourceB->id,
            'topic_id' => $devops->id,
            'title' => 'Read Bookmarked Entry',
            'is_read' => true,
            'is_bookmarked' => true,
            'is_noise' => false,
        ]);

        Entry::factory()->noise()->create([
            'user_id' => $user->id,
            'source_id' => $sourceA->id,
            'topic_id' => $backend->id,
            'title' => 'Noisy Meme Post',
        ]);

        Entry::factory()->create([
            'user_id' => $otherUser->id,
            'title' => 'Foreign User Secret Entry',
        ]);

        // Signal tab: excludes noise and foreign user
        Livewire::actingAs($user)
            ->test(FeedHub::class)
            ->assertSee('Signal Unread Entry')
            ->assertSee('Read Bookmarked Entry')
            ->assertDontSee('Noisy Meme Post')
            ->assertDontSee('Foreign User Secret Entry')
            // Unread tab
            ->set('tab', 'unread')
            ->assertSee('Signal Unread Entry')
            ->assertDontSee('Read Bookmarked Entry')
            // Bookmarks tab
            ->set('tab', 'bookmarks')
            ->assertSee('Read Bookmarked Entry')
            ->assertDontSee('Signal Unread Entry')
            // All Stream tab shows noise too
            ->set('tab', 'all')
            ->assertSee('Noisy Meme Post')
            // Topic filter
            ->set('tab', 'signal')
            ->set('topicId', $backend->id)
            ->assertSee('Signal Unread Entry')
            ->assertDontSee('Read Bookmarked Entry')
            // Source filter
            ->set('topicId', null)
            ->set('sourceId', $sourceB->id)
            ->assertSee('Read Bookmarked Entry')
            ->assertDontSee('Signal Unread Entry');
    }

    public function test_feed_hub_searches_with_laravel_scout(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $source = Source::factory()->create(['user_id' => $user->id]);

        Entry::factory()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'title' => 'SQLite WAL Concurrency Guide',
            'summary' => '<p>Configure busy_timeout carefully.</p>',
            'is_noise' => false,
        ]);

        Entry::factory()->noise()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'title' => 'SQLite Meme Thread',
            'summary' => '<p>Funny database meme</p>',
        ]);

        Entry::factory()->create([
            'user_id' => $otherUser->id,
            'title' => 'SQLite Foreign Entry',
        ]);

        // Search inside signal tab finds signal entry, excludes noise & foreign
        Livewire::actingAs($user)
            ->test(FeedHub::class)
            ->set('search', 'SQLite')
            ->assertSee('SQLite WAL Concurrency Guide')
            ->assertDontSee('SQLite Meme Thread')
            ->assertDontSee('SQLite Foreign Entry')
            // Switching to 'all' tab allows finding noisy entries via search too
            ->set('tab', 'all')
            ->assertSee('SQLite Meme Thread')
            ->assertDontSee('SQLite Foreign Entry');
    }

    public function test_feed_hub_reader_actions_and_mark_all_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $source = Source::factory()->create(['user_id' => $user->id]);

        $entry1 = Entry::factory()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'title' => 'First Article',
            'url' => 'https://example.com/first',
            'summary' => '<p>Full sanitized article body.</p>',
            'is_read' => false,
            'is_bookmarked' => false,
            'is_noise' => false,
        ]);

        $entry2 = Entry::factory()->create([
            'user_id' => $user->id,
            'source_id' => $source->id,
            'title' => 'Second Article',
            'is_read' => false,
            'is_noise' => false,
        ]);

        $foreignUnread = Entry::factory()->create([
            'user_id' => $otherUser->id,
            'is_read' => false,
        ]);

        Livewire::actingAs($user)
            ->test(FeedHub::class)
            ->call('selectEntry', $entry1->id)
            ->assertSet('selectedEntryId', $entry1->id)
            ->assertSee('Full sanitized article body.')
            ->assertSee('https://example.com/first')
            ->call('toggleBookmark', $entry1->id)
            ->call('toggleNoise', $entry1->id)
            ->call('closeReader')
            ->assertSet('selectedEntryId', null);

        $entry1->refresh();
        $this->assertTrue($entry1->is_read);
        $this->assertTrue($entry1->is_bookmarked);
        $this->assertTrue($entry1->is_noise);

        // Restore from noise and test markAllAsRead
        Livewire::actingAs($user)
            ->test(FeedHub::class)
            ->call('toggleNoise', $entry1->id)
            ->call('toggleRead', $entry1->id)
            ->call('markAllAsRead');

        $this->assertTrue($entry1->fresh()->is_read);
        $this->assertTrue($entry2->fresh()->is_read);
        $this->assertFalse($foreignUnread->fresh()->is_read);
    }
}
