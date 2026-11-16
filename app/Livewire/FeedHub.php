<?php

namespace App\Livewire;

use App\Models\Entry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class FeedHub extends Component
{
    use WithPagination;

    #[Url(except: 'signal')]
    public string $tab = 'signal';

    #[Url(except: null)]
    public ?int $topicId = null;

    #[Url(except: null)]
    public ?int $sourceId = null;

    #[Url(except: '')]
    public string $search = '';

    public ?int $selectedEntryId = null;

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    public function updatedTopicId(): void
    {
        if ($this->topicId === 0) {
            $this->topicId = null;
        }

        $this->resetPage();
    }

    public function updatedSourceId(): void
    {
        if ($this->sourceId === 0) {
            $this->sourceId = null;
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function selectEntry(int $entryId): void
    {
        $entry = Entry::query()
            ->where('user_id', Auth::id())
            ->findOrFail($entryId);

        if (!$entry->is_read) {
            $entry->update(['is_read' => true]);
        }

        $this->selectedEntryId = $entry->id;
    }

    public function closeReader(): void
    {
        $this->selectedEntryId = null;
    }

    public function toggleRead(int $entryId): void
    {
        $entry = Entry::query()
            ->where('user_id', Auth::id())
            ->findOrFail($entryId);

        $entry->update(['is_read' => !$entry->is_read]);
    }

    public function toggleBookmark(int $entryId): void
    {
        $entry = Entry::query()
            ->where('user_id', Auth::id())
            ->findOrFail($entryId);

        $entry->update(['is_bookmarked' => !$entry->is_bookmarked]);
    }

    public function toggleNoise(int $entryId): void
    {
        $entry = Entry::query()
            ->where('user_id', Auth::id())
            ->findOrFail($entryId);

        $nextNoise = !$entry->is_noise;

        $entry->update([
            'is_noise' => $nextNoise,
            'is_highlighted' => $nextNoise ? false : $entry->is_highlighted,
        ]);
    }

    public function markAllAsRead(): void
    {
        $query = $this->applyScopeFilters(Entry::query());
        $query->where('is_read', false)->update(['is_read' => true]);

        session()->flash('status', 'All matching entries marked as read.');
    }

    protected function applyScopeFilters(Builder $query): Builder
    {
        $query->where('user_id', Auth::id());

        match ($this->tab) {
            'unread' => $query->where('is_noise', false)->where('is_read', false),
            'bookmarks' => $query->where('is_bookmarked', true),
            'all' => $query,
            default => $query->where('is_noise', false),
        };

        if ($this->topicId) {
            $query->where('topic_id', $this->topicId);
        }

        if ($this->sourceId) {
            $query->where('source_id', $this->sourceId);
        }

        return $query;
    }

    protected function applyFilters(Builder $query): Builder
    {
        return $this->applyScopeFilters($query)
            ->with(['source', 'topic'])
            ->orderByDesc('is_highlighted')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    public function render(): View
    {
        $user = Auth::user();
        $term = trim($this->search);

        $entries = $term !== ''
            ? Entry::search($term)
                ->query(fn(Builder $query): Builder => $this->applyFilters($query))
                ->paginate(20)
            : $this->applyFilters(Entry::query())->paginate(20);

        $selectedEntry = $this->selectedEntryId
            ? Entry::query()
                ->where('user_id', $user->id)
                ->with(['source', 'topic'])
                ->find($this->selectedEntryId)
            : null;

        return view('livewire.feed-hub', [
            'entries' => $entries,
            'selectedEntry' => $selectedEntry,
            'topics' => $user->topics()->orderBy('name')->get(),
            'sources' => $user->sources()->orderBy('name')->get(),
            'counts' => [
                'signal' => Entry::query()->where('user_id', $user->id)->where('is_noise', false)->count(),
                'unread' => Entry::query()->where('user_id', $user->id)->where('is_noise', false)->where('is_read', false)->count(),
                'bookmarks' => Entry::query()->where('user_id', $user->id)->where('is_bookmarked', true)->count(),
                'all' => Entry::query()->where('user_id', $user->id)->count(),
            ],
        ]);
    }
}