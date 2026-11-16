<?php

namespace App\Livewire;

use DateTimeZone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ProfileSettings extends Component
{
    public string $timezone = 'UTC';

    public string $digest_frequency = 'none';

    public string $digest_time = '08:30';

    public function mount(): void
    {
        $user = Auth::user();

        $this->timezone = $user->timezone ?: 'UTC';
        $this->digest_frequency = $user->digest_frequency ?: 'none';
        $this->digest_time = $user->digest_time ?: '08:30';
    }

    public function save(): void
    {
        $validated = $this->validate([
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'digest_frequency' => ['required', 'string', 'in:none,daily,weekly'],
            'digest_time' => ['required', 'date_format:H:i'],
        ]);

        Auth::user()->update($validated);

        session()->flash('status', 'Settings updated successfully.');
    }

    public function regenerateFeedToken(): void
    {
        $user = Auth::user();

        Cache::forget("private-rss:{$user->id}:all");
        foreach ($user->topics()->pluck('id') as $topicId) {
            Cache::forget("private-rss:{$user->id}:{$topicId}");
        }

        $user->update([
            'feed_token' => (string) Str::uuid(),
        ]);

        session()->flash('status', 'Private RSS token regenerated. Previous URLs are now invalid.');
    }

    public function render(): View
    {
        $user = Auth::user();

        return view('livewire.profile-settings', [
            'user' => $user,
            'topics' => $user->topics()->orderBy('name')->get(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }
}
