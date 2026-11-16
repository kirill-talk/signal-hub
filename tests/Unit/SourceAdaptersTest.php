<?php

namespace Tests\Unit;

use App\DataTransferObjects\EntryData;
use App\Models\Source;
use App\Services\Adapters\AdapterFactory;
use App\Services\Adapters\JsonFeedAdapter;
use App\Services\Adapters\RssAtomAdapter;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class SourceAdaptersTest extends TestCase
{
    public function test_adapter_factory_resolves_expected_adapters(): void
    {
        $factory = app(AdapterFactory::class);

        $this->assertInstanceOf(RssAtomAdapter::class, $factory->make('rss'));
        $this->assertInstanceOf(RssAtomAdapter::class, $factory->make('github'));
        $this->assertInstanceOf(JsonFeedAdapter::class, $factory->make('json'));

        $this->expectException(InvalidArgumentException::class);
        $factory->make('unknown');
    }

    public function test_rss_adapter_parses_standard_rss_namespaces_dates_and_sanitizes_html(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/standard_rss.xml'));
        $this->assertIsString($xml);

        Http::fake([
            'blog.example.com/*' => Http::response($xml, 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $source = new Source([
            'url' => 'https://blog.example.com/feed.xml',
            'adapter_type' => 'rss',
        ]);

        $entries = app(RssAtomAdapter::class)->fetch($source);

        $this->assertCount(2, $entries);

        /** @var EntryData $first */
        $first = $entries[0];
        $this->assertSame('Securing Laravel Applications in Production', $first->title);
        $this->assertSame('https://blog.example.com/posts/securing-laravel', $first->url);
        $this->assertSame('Taylor Otwell', $first->author);
        $this->assertSame('2026-11-16 08:30:00', $first->publishedAt?->utc()->format('Y-m-d H:i:s'));
        $this->assertNotNull($first->summary);
        $this->assertStringContainsString('Clean paragraph about <strong>security</strong>.', $first->summary);
        $this->assertStringNotContainsString('<script', $first->summary);
        $this->assertStringNotContainsString('javascript:', $first->summary);
        $this->assertStringNotContainsString('onclick', $first->summary);
        $this->assertStringNotContainsString('<img', $first->summary);

        /** @var EntryData $second */
        $second = $entries[1];
        $this->assertSame('Queue Workers & SQLite WAL Tuning', $second->title);
        $this->assertSame('https://blog.example.com/posts/sqlite-wal', $second->url);
        $this->assertSame('Nuno Maduro', $second->author);
        $this->assertSame('2026-11-15 14:15:00', $second->publishedAt?->utc()->format('Y-m-d H:i:s'));
    }

    public function test_rss_atom_adapter_parses_github_releases_atom_feed(): void
    {
        $atom = file_get_contents(base_path('tests/Fixtures/github_releases.atom'));
        $this->assertIsString($atom);

        Http::fake([
            'github.com/*' => Http::response($atom, 200, ['Content-Type' => 'application/atom+xml']),
        ]);

        $source = new Source([
            'url' => 'https://github.com/laravel/framework/releases.atom',
            'adapter_type' => 'github',
        ]);

        $entries = app(RssAtomAdapter::class)->fetch($source);

        $this->assertCount(2, $entries);
        $this->assertSame('v13.1.0', $entries[0]->title);
        $this->assertSame('https://github.com/laravel/framework/releases/tag/v13.1.0', $entries[0]->url);
        $this->assertSame('taylorotwell', $entries[0]->author);
        $this->assertSame('2026-11-16 09:45:00', $entries[0]->publishedAt?->utc()->format('Y-m-d H:i:s'));
        $this->assertNotNull($entries[0]->summary);
        $this->assertStringContainsString('Improved Queue RateLimiter stability', $entries[0]->summary);
        $this->assertStringNotContainsString('<script', $entries[0]->summary);
    }

    public function test_rss_atom_adapter_parses_reddit_rss_feed(): void
    {
        $atom = file_get_contents(base_path('tests/Fixtures/reddit.rss'));
        $this->assertIsString($atom);

        Http::fake([
            'www.reddit.com/*' => Http::response($atom, 200, ['Content-Type' => 'application/atom+xml']),
        ]);

        $source = new Source([
            'url' => 'https://www.reddit.com/r/laravel/.rss',
            'adapter_type' => 'rss',
        ]);

        $entries = app(RssAtomAdapter::class)->fetch($source);

        $this->assertCount(1, $entries);
        $this->assertSame('Livewire 4 Tips for High-Throughput Tables', $entries[0]->title);
        $this->assertSame('https://www.reddit.com/r/laravel/comments/123/test/', $entries[0]->url);
        $this->assertSame('/u/laravel_dev', $entries[0]->author);
        $this->assertNotNull($entries[0]->summary);
        $this->assertStringContainsString('<strong>Livewire</strong>', $entries[0]->summary);
        $this->assertStringNotContainsString('<iframe', $entries[0]->summary);
    }

    public function test_json_feed_adapter_parses_json_feed_and_sanitizes_html(): void
    {
        $json = file_get_contents(base_path('tests/Fixtures/feed.json'));
        $this->assertIsString($json);

        Http::fake([
            'jsonfeed.example.com/*' => Http::response($json, 200, ['Content-Type' => 'application/feed+json']),
        ]);

        $source = new Source([
            'url' => 'https://jsonfeed.example.com/feed.json',
            'adapter_type' => 'json',
        ]);

        $entries = app(JsonFeedAdapter::class)->fetch($source);

        $this->assertCount(2, $entries);
        $this->assertSame('Why JSON Feed Matters', $entries[0]->title);
        $this->assertSame('Brent Simmons', $entries[0]->author);
        $this->assertNotNull($entries[0]->summary);
        $this->assertStringContainsString('Clean <em>JSON</em> structure', $entries[0]->summary);
        $this->assertStringNotContainsString('<script', $entries[0]->summary);
        $this->assertSame('Manton Reece', $entries[1]->author);
    }
}
