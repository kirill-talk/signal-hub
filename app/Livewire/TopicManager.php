<?php

namespace App\Livewire;

use App\Http\Requests\StoreTopicRequest;
use App\Models\Topic;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class TopicManager extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $color = 'indigo';

    public function save(): void
    {
        $validated = $this->validate((new StoreTopicRequest())->rules());

        $baseSlug = Str::slug($validated['name']) ?: 'topic';
        $slug = $baseSlug;
        $counter = 1;

        while (
            Topic::query()
                ->where('user_id', Auth::id())
                ->where('slug', $slug)
                ->when($this->editingId, fn($q) => $q->where('id', '!=', $this->editingId))
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter++;
        }

        if ($this->editingId) {
            $topic = Topic::query()
                ->where('user_id', Auth::id())
                ->findOrFail($this->editingId);

            $topic->update([
                'name' => $validated['name'],
                'slug' => $slug,
                'color' => $validated['color'],
            ]);
        } else {
            Auth::user()->topics()->create([
                'name' => $validated['name'],
                'slug' => $slug,
                'color' => $validated['color'],
            ]);
        }

        $this->resetForm();
        session()->flash('status', 'Topic saved successfully.');
    }

    public function edit(int $topicId): void
    {
        $topic = Topic::query()
            ->where('user_id', Auth::id())
            ->findOrFail($topicId);

        $this->editingId = $topic->id;
        $this->name = $topic->name;
        $this->color = $topic->color;
    }

    public function delete(int $topicId): void
    {
        Topic::query()
            ->where('user_id', Auth::id())
            ->findOrFail($topicId)
            ->delete();

        if ($this->editingId === $topicId) {
            $this->resetForm();
        }

        session()->flash('status', 'Topic deleted.');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name']);
        $this->color = 'indigo';
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.topic-manager', [
            'topics' => Auth::user()
                ->topics()
                ->withCount(['sources', 'entries'])
                ->orderBy('name')
                ->get(),
        ]);
    }
}