<?php

namespace App\Livewire;

use App\Http\Requests\SaveFilterRuleRequest;
use App\Models\FilterRule;
use App\Services\NoiseFilterService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class FilterRuleManager extends Component
{
    public ?int $editingId = null;

    public string $type = 'exclude';

    public string $match_field = 'both';

    public ?int $source_id = null;

    public ?int $topic_id = null;

    /**
     * @var array<int, string>
     */
    public array $keywords = [];

    public bool $is_active = true;

    public ?int $previewMatchCount = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['match_field', 'source_id', 'topic_id', 'keywords'], true)) {
            $this->previewMatchCount = null;
        }
    }

    public function save(): void
    {
        $this->normalizeInputs();

        $validated = $this->validate((new SaveFilterRuleRequest())->rules());

        if ($this->editingId) {
            $rule = FilterRule::query()
                ->where('user_id', Auth::id())
                ->findOrFail($this->editingId);

            $rule->update($validated);
        } else {
            Auth::user()->filterRules()->create($validated);
        }

        $this->resetForm();
        session()->flash('status', 'Filter rule saved successfully.');
    }

    public function edit(int $ruleId): void
    {
        $rule = FilterRule::query()
            ->where('user_id', Auth::id())
            ->findOrFail($ruleId);

        $this->editingId = $rule->id;
        $this->type = $rule->type;
        $this->match_field = $rule->match_field;
        $this->source_id = $rule->source_id;
        $this->topic_id = $rule->topic_id;
        $this->keywords = $rule->keywords ?? [];
        $this->is_active = $rule->is_active;
        $this->previewMatchCount = null;
    }

    public function toggleActive(int $ruleId): void
    {
        $rule = FilterRule::query()
            ->where('user_id', Auth::id())
            ->findOrFail($ruleId);

        $rule->update([
            'is_active' => !$rule->is_active,
        ]);
    }

    public function delete(int $ruleId): void
    {
        FilterRule::query()
            ->where('user_id', Auth::id())
            ->findOrFail($ruleId)
            ->delete();

        if ($this->editingId === $ruleId) {
            $this->resetForm();
        }

        session()->flash('status', 'Filter rule deleted.');
    }

    public function previewRule(NoiseFilterService $noiseFilter): void
    {
        $this->normalizeInputs();

        $this->validate([
            'match_field' => ['required', 'string', 'in:title,summary,both'],
            'keywords' => ['required', 'array', 'min:1'],
            'keywords.*' => ['required', 'string', 'max:80'],
        ]);

        $draftRule = new FilterRule([
            'match_field' => $this->match_field,
            'keywords' => $this->keywords,
        ]);

        $entries = Auth::user()
            ->entries()
            ->when($this->source_id, fn($q) => $q->where('source_id', $this->source_id))
            ->when($this->topic_id, fn($q) => $q->where('topic_id', $this->topic_id))
            ->get(['id', 'title', 'summary']);

        $this->previewMatchCount = $entries->filter(
            fn($entry): bool => $noiseFilter->matchesRule($draftRule, $entry->title, $entry->summary)
        )->count();
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'source_id', 'topic_id', 'keywords', 'previewMatchCount']);
        $this->type = 'exclude';
        $this->match_field = 'both';
        $this->is_active = true;
        $this->resetValidation();
    }

    protected function normalizeInputs(): void
    {
        if ($this->source_id === 0) {
            $this->source_id = null;
        }

        if ($this->topic_id === 0) {
            $this->topic_id = null;
        }

        $this->keywords = array_values(array_unique(array_filter(
            array_map(static fn($word): string => trim((string) $word), $this->keywords),
            static fn(string $word): bool => $word !== '',
        )));
    }

    public function render(): View
    {
        return view('livewire.filter-rule-manager', [
            'rules' => Auth::user()
                ->filterRules()
                ->with(['source', 'topic'])
                ->latest()
                ->get(),
            'sources' => Auth::user()
                ->sources()
                ->orderBy('name')
                ->get(),
            'topics' => Auth::user()
                ->topics()
                ->orderBy('name')
                ->get(),
        ]);
    }
}