<?php

namespace App\Livewire;

use App\Http\Requests\SaveSourceRequest;
use App\Jobs\FetchSourceJob;
use App\Models\Source;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class SourceManager extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $url = '';

    public ?int $topic_id = null;

    public string $adapter_type = 'rss';

    public int $fetch_interval_minutes = 60;

    public function updatedUrl(string $value): void
    {
        if (!$this->editingId && $value !== '') {
            $this->adapter_type = $this->detectAdapterType($value);
        }
    }

    public function detectAdapterType(string $url): string
    {
        $lower = strtolower($url);

        if (str_contains($lower, 'github.com') && (str_contains($lower, 'releases') || str_contains($lower, '.atom'))) {
            return 'github';
        }

        if (str_ends_with($lower, '.json') || str_contains($lower, 'feed.json') || str_contains($lower, '/json')) {
            return 'json';
        }

        return 'rss';
    }

    public function save(): void
    {
        if ($this->topic_id === 0) {
            $this->topic_id = null;
        }

        $validated = $this->validate((new SaveSourceRequest())->rules());

        if ($this->editingId) {
            $source = Source::query()
                ->where('user_id', Auth::id())
                ->findOrFail($this->editingId);

            $source->update($validated);
        } else {
            Auth::user()->sources()->create([
                ...$validated,
                'status' => 'active',
                'error_count' => 0,
            ]);
        }

        $this->resetForm();
        session()->flash('status', 'Source saved successfully.');
    }

    public function edit(int $sourceId): void
    {
        $source = Source::query()
            ->where('user_id', Auth::id())
            ->findOrFail($sourceId);

        $this->editingId = $source->id;
        $this->name = $source->name;
        $this->url = $source->url;
        $this->topic_id = $source->topic_id;
        $this->adapter_type = $source->adapter_type;
        $this->fetch_interval_minutes = $source->fetch_interval_minutes;
    }

    public function delete(int $sourceId): void
    {
        Source::query()
            ->where('user_id', Auth::id())
            ->findOrFail($sourceId)
            ->delete();

        if ($this->editingId === $sourceId) {
            $this->resetForm();
        }

        session()->flash('status', 'Source deleted.');
    }

    public function fetchNow(int $sourceId): void
    {
        $source = Source::query()
            ->where('user_id', Auth::id())
            ->findOrFail($sourceId);

        FetchSourceJob::dispatch($source);

        session()->flash('status', "Fetch queued for {$source->name}.");
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'url', 'topic_id']);
        $this->adapter_type = 'rss';
        $this->fetch_interval_minutes = 60;
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.source-manager', [
            'sources' => Auth::user()
                ->sources()
                ->with('topic')
                ->withCount('entries')
                ->latest()
                ->get(),
            'topics' => Auth::user()
                ->topics()
                ->orderBy('name')
                ->get(),
        ]);
    }
}