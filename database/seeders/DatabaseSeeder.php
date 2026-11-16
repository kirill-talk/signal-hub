<?php

namespace Database\Seeders;

use App\Models\Entry;
use App\Models\FilterRule;
use App\Models\Source;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Alex Mercer',
            'email' => 'demo@signalhub.test',
            'password' => Hash::make('password'),
            'timezone' => 'UTC',
            'digest_frequency' => 'daily',
            'digest_time' => '08:30',
        ]);

        $backend = Topic::factory()->create([
            'user_id' => $user->id,
            'name' => 'Backend',
            'slug' => 'backend',
            'color' => 'indigo',
        ]);

        $frontend = Topic::factory()->create([
            'user_id' => $user->id,
            'name' => 'Frontend',
            'slug' => 'frontend',
            'color' => 'sky',
        ]);

        $devops = Topic::factory()->create([
            'user_id' => $user->id,
            'name' => 'DevOps',
            'slug' => 'devops',
            'color' => 'emerald',
        ]);

        $security = Topic::factory()->create([
            'user_id' => $user->id,
            'name' => 'Security',
            'slug' => 'security',
            'color' => 'rose',
        ]);

        $laravelReleases = Source::factory()->create([
            'user_id' => $user->id,
            'topic_id' => $backend->id,
            'name' => 'Laravel Framework Releases',
            'url' => 'https://github.com/laravel/framework/releases.atom',
            'adapter_type' => 'github',
            'fetch_interval_minutes' => 60,
        ]);

        $laravelNews = Source::factory()->create([
            'user_id' => $user->id,
            'topic_id' => $backend->id,
            'name' => 'Laravel News',
            'url' => 'https://feed.laravel-news.com',
            'adapter_type' => 'rss',
            'fetch_interval_minutes' => 60,
        ]);

        $redditLaravel = Source::factory()->create([
            'user_id' => $user->id,
            'topic_id' => $frontend->id,
            'name' => 'r/laravel',
            'url' => 'https://www.reddit.com/r/laravel/.rss',
            'adapter_type' => 'rss',
            'fetch_interval_minutes' => 30,
        ]);

        $jsonFeedBlog = Source::factory()->create([
            'user_id' => $user->id,
            'topic_id' => $devops->id,
            'name' => 'JSON Feed Blog',
            'url' => 'https://www.jsonfeed.org/feed.json',
            'adapter_type' => 'json',
            'fetch_interval_minutes' => 180,
        ]);

        Entry::factory()->count(3)->highlighted()->create([
            'user_id' => $user->id,
            'source_id' => $laravelReleases->id,
            'topic_id' => $backend->id,
        ]);

        Entry::factory()->count(4)->create([
            'user_id' => $user->id,
            'source_id' => $laravelNews->id,
            'topic_id' => $backend->id,
        ]);

        Entry::factory()->count(3)->noise()->create([
            'user_id' => $user->id,
            'source_id' => $redditLaravel->id,
            'topic_id' => $frontend->id,
        ]);

        Entry::factory()->count(2)->create([
            'user_id' => $user->id,
            'source_id' => $jsonFeedBlog->id,
            'topic_id' => $devops->id,
        ]);

        FilterRule::factory()->create([
            'user_id' => $user->id,
            'source_id' => $laravelReleases->id,
            'topic_id' => $backend->id,
            'type' => 'include',
            'match_field' => 'both',
            'keywords' => ['security', 'release', 'Laravel 13'],
        ]);

        FilterRule::factory()->create([
            'user_id' => $user->id,
            'source_id' => null,
            'topic_id' => null,
            'type' => 'exclude',
            'match_field' => 'both',
            'keywords' => ['hiring', 'weekly thread', 'meme'],
        ]);

        FilterRule::factory()->create([
            'user_id' => $user->id,
            'source_id' => null,
            'topic_id' => $security->id,
            'type' => 'highlight',
            'match_field' => 'title',
            'keywords' => ['CVE', 'vulnerability', 'patch'],
        ]);
    }
}
