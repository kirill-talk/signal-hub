<?php

namespace Tests\Feature;

use App\Livewire\SourceManager;
use App\Livewire\TopicManager;
use App\Models\Source;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PhaseOneCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receives_feed_token(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertRedirect(route('sources.index'));

        $user = User::where('email', 'jane@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNotEmpty($user->feed_token);
        $this->assertAuthenticatedAs($user);
    }

    public function test_guests_cannot_access_topics_or_sources(): void
    {
        $this->get(route('topics.index'))->assertRedirect(route('login'));
        $this->get(route('sources.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_create_edit_and_delete_topics_with_unique_slugs(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TopicManager::class)
            ->set('name', 'Backend')
            ->set('color', 'indigo')
            ->call('save')
            ->assertHasNoErrors();

        $topic = Topic::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('backend', $topic->slug);

        // Creating another topic with the same name produces a unique slug
        Livewire::actingAs($user)
            ->test(TopicManager::class)
            ->set('name', 'Backend')
            ->set('color', 'emerald')
            ->call('save')
            ->assertHasNoErrors();

        $secondTopic = Topic::where('user_id', $user->id)->where('id', '!=', $topic->id)->firstOrFail();
        $this->assertSame('backend-1', $secondTopic->slug);

        // Editing the first topic
        Livewire::actingAs($user)
            ->test(TopicManager::class)
            ->call('edit', $topic->id)
            ->set('name', 'Backend Core')
            ->set('color', 'sky')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('topics', [
            'id' => $topic->id,
            'name' => 'Backend Core',
            'slug' => 'backend-core',
            'color' => 'sky',
        ]);

        // Deleting the second topic
        Livewire::actingAs($user)
            ->test(TopicManager::class)
            ->call('delete', $secondTopic->id);

        $this->assertDatabaseMissing('topics', ['id' => $secondTopic->id]);
    }

    public function test_source_manager_auto_detects_adapter_and_performs_crud(): void
    {
        $user = User::factory()->create();
        $topic = Topic::factory()->create([
            'user_id' => $user->id,
            'name' => 'PHP',
            'slug' => 'php',
        ]);

        Livewire::actingAs($user)
            ->test(SourceManager::class)
            ->set('name', 'Laravel Releases')
            ->set('url', 'https://github.com/laravel/framework/releases.atom')
            ->assertSet('adapter_type', 'github')
            ->set('topic_id', $topic->id)
            ->set('fetch_interval_minutes', 30)
            ->call('save')
            ->assertHasNoErrors();

        $source = Source::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('github', $source->adapter_type);
        $this->assertSame(30, $source->fetch_interval_minutes);
        $this->assertSame($topic->id, $source->topic_id);

        // JSON feed detection
        Livewire::actingAs($user)
            ->test(SourceManager::class)
            ->set('url', 'https://example.com/feed.json')
            ->assertSet('adapter_type', 'json')
            ->set('url', 'https://blog.laravel.com/feed')
            ->assertSet('adapter_type', 'rss');

        // Edit and delete source
        Livewire::actingAs($user)
            ->test(SourceManager::class)
            ->call('edit', $source->id)
            ->set('name', 'Laravel Framework')
            ->set('fetch_interval_minutes', 15)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sources', [
            'id' => $source->id,
            'name' => 'Laravel Framework',
            'fetch_interval_minutes' => 15,
        ]);

        Livewire::actingAs($user)
            ->test(SourceManager::class)
            ->call('delete', $source->id);

        $this->assertDatabaseMissing('sources', ['id' => $source->id]);
    }

    public function test_user_cannot_attach_another_users_topic_to_source(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignTopic = Topic::factory()->create(['user_id' => $otherUser->id]);

        Livewire::actingAs($user)
            ->test(SourceManager::class)
            ->set('name', 'Malicious Source')
            ->set('url', 'https://example.com/feed.xml')
            ->set('topic_id', $foreignTopic->id)
            ->call('save')
            ->assertHasErrors(['topic_id']);

        $this->assertDatabaseCount('sources', 0);
    }
}
