@php
    $typeStyles = [
        'include' => ['label' => 'Include (Whitelist)', 'badge' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30', 'dot' => 'bg-emerald-400'],
        'exclude' => ['label' => 'Exclude (Noise)', 'badge' => 'bg-rose-500/15 text-rose-300 border-rose-500/30', 'dot' => 'bg-rose-400'],
        'highlight' => ['label' => 'Highlight (Priority)', 'badge' => 'bg-amber-500/15 text-amber-300 border-amber-500/30', 'dot' => 'bg-amber-400'],
    ];

    $fieldLabels = [
        'title' => 'Title only',
        'summary' => 'Summary only',
        'both' => 'Title & Summary',
    ];
@endphp

<div
    x-data="{ confirmingDeleteId: null }"
    @keydown.escape.window="confirmingDeleteId = null"
    class="space-y-8"
>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Filter Rules</h1>
            <p class="mt-1 text-sm text-slate-400">
                Define keyword rules to separate signal from noise automatically during feed ingestion.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('sources.index') }}"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-900 px-3.5 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition"
            >
                Sources
            </a>
            <a
                href="{{ route('topics.index') }}"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-900 px-3.5 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition"
            >
                Topics
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        <div class="lg:col-span-5 bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <h2 class="text-base font-semibold text-white">
                {{ $editingId ? 'Edit Filter Rule' : 'Create Filter Rule' }}
            </h2>
            <p class="mt-1 text-xs text-slate-400">
                Priority: Include rules run first, then Exclude rules, then Highlight rules.
            </p>

            <div
                x-data="{
                    keywords: $wire.entangle('keywords'),
                    draft: '',
                    addTag() {
                        const parts = this.draft.split(',').map(s => s.trim()).filter(Boolean);
                        if (!Array.isArray(this.keywords)) {
                            this.keywords = [];
                        }
                        for (const part of parts) {
                            if (!this.keywords.some(k => k.toLowerCase() === part.toLowerCase())) {
                                this.keywords.push(part);
                            }
                        }
                        this.draft = '';
                    },
                    removeTag(index) {
                        this.keywords.splice(index, 1);
                    },
                    handleKeydown(event) {
                        if (event.key === 'Enter' || event.key === ',') {
                            event.preventDefault();
                            this.addTag();
                        } else if (event.key === 'Backspace' && this.draft === '' && this.keywords.length > 0) {
                            this.keywords.pop();
                        }
                    }
                }"
            >
                <form @submit.prevent="addTag(); $wire.save()" class="mt-5 space-y-4">
                    <div>
                        <span class="block text-sm font-medium text-slate-300 mb-2">Rule Type</span>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach ($typeStyles as $typeKey => $meta)
                                <label class="cursor-pointer">
                                    <input
                                        type="radio"
                                        wire:model="type"
                                        value="{{ $typeKey }}"
                                        class="sr-only peer"
                                    >
                                    <span class="flex items-center justify-center gap-1.5 px-2.5 py-2 rounded-xl text-xs font-semibold border border-slate-700 bg-slate-950/80 text-slate-400 peer-checked:border-indigo-500 peer-checked:bg-indigo-500/15 peer-checked:text-white transition text-center">
                                        <span class="w-2 h-2 rounded-full {{ $meta['dot'] }}"></span>
                                        {{ ucfirst($typeKey) }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('type')
                            <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="rule-field" class="block text-sm font-medium text-slate-300">Match Field</label>
                            <select
                                id="rule-field"
                                wire:model="match_field"
                                class="mt-1.5 block w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3.5 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                            >
                                <option value="both">Title &amp; Summary</option>
                                <option value="title">Title only</option>
                                <option value="summary">Summary only</option>
                            </select>
                            @error('match_field')
                                <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="rule-topic" class="block text-sm font-medium text-slate-300">Topic Scope</label>
                            <select
                                id="rule-topic"
                                wire:model="topic_id"
                                class="mt-1.5 block w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3.5 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                            >
                                <option value="">All Topics (Global)</option>
                                @foreach ($topics as $topic)
                                    <option value="{{ $topic->id }}">{{ $topic->name }}</option>
                                @endforeach
                            </select>
                            @error('topic_id')
                                <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="rule-source" class="block text-sm font-medium text-slate-300">Source Scope</label>
                        <select
                            id="rule-source"
                            wire:model="source_id"
                            class="mt-1.5 block w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3.5 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                        >
                            <option value="">All Sources (Global)</option>
                            @foreach ($sources as $source)
                                <option value="{{ $source->id }}">{{ $source->name }}</option>
                            @endforeach
                        </select>
                        @error('source_id')
                            <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <label for="keyword-input" class="block text-sm font-medium text-slate-300">Keywords</label>
                            <span class="text-[11px] text-slate-500">Press Enter or comma to add</span>
                        </div>

                        <div class="mt-1.5 rounded-xl border border-slate-700 bg-slate-950/80 p-2.5 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/30">
                            <div class="flex flex-wrap gap-1.5 mb-2" x-show="Array.isArray(keywords) && keywords.length > 0">
                                <template x-for="(tag, index) in keywords" :key="index">
                                    <span class="inline-flex items-center gap-1 rounded-lg border border-indigo-500/30 bg-indigo-500/15 px-2.5 py-1 text-xs font-medium text-indigo-200">
                                        <span x-text="tag"></span>
                                        <button
                                            type="button"
                                            @click="removeTag(index)"
                                            class="text-indigo-300 hover:text-white focus:outline-none"
                                            aria-label="Remove keyword"
                                        >
                                            &times;
                                        </button>
                                    </span>
                                </template>
                            </div>

                            <div class="flex items-center gap-2">
                                <input
                                    id="keyword-input"
                                    type="text"
                                    x-model="draft"
                                    @keydown="handleKeydown($event)"
                                    @blur="addTag()"
                                    placeholder="e.g. hiring, weekly thread, CVE"
                                    class="flex-1 bg-transparent text-sm text-white placeholder-slate-500 focus:outline-none"
                                >
                                <button
                                    type="button"
                                    @click="addTag()"
                                    class="rounded-lg border border-slate-700 bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-300 hover:bg-slate-700 hover:text-white transition"
                                >
                                    Add
                                </button>
                            </div>
                        </div>

                        @error('keywords')
                            <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                        @error('keywords.*')
                            <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <label class="inline-flex items-center gap-2.5 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model="is_active"
                                class="rounded border-slate-700 bg-slate-950 text-indigo-500 focus:ring-indigo-500/30"
                            >
                            <span class="text-sm text-slate-300">Rule is active</span>
                        </label>

                        <button
                            type="button"
                            @click="addTag(); $wire.previewRule()"
                            wire:loading.attr="disabled"
                            wire:target="previewRule"
                            class="rounded-lg border border-sky-500/30 bg-sky-500/10 px-3 py-1.5 text-xs font-semibold text-sky-300 hover:bg-sky-500/20 disabled:opacity-50 transition"
                        >
                            <span wire:loading.remove wire:target="previewRule">Test Rule on Existing Entries</span>
                            <span wire:loading wire:target="previewRule">Testing...</span>
                        </button>
                    </div>

                    @if ($previewMatchCount !== null)
                        <div class="rounded-xl border border-sky-500/30 bg-sky-500/10 px-3.5 py-2.5 text-xs text-sky-200">
                            Preview result: <strong class="text-white">{{ $previewMatchCount }}</strong> existing {{ \Illuminate\Support\Str::plural('entry', $previewMatchCount) }} match this rule.
                        </div>
                    @endif

                    <div class="flex items-center gap-3 pt-2">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            class="flex-1 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-500 disabled:opacity-50 transition"
                        >
                            <span wire:loading.remove wire:target="save">
                                {{ $editingId ? 'Save Changes' : 'Save Filter Rule' }}
                            </span>
                            <span wire:loading wire:target="save">Saving...</span>
                        </button>

                        @if ($editingId)
                            <button
                                type="button"
                                wire:click="resetForm"
                                class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm font-medium text-slate-300 hover:bg-slate-700 hover:text-white transition"
                            >
                                Cancel
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="lg:col-span-7 bg-slate-900/80 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <h2 class="text-base font-semibold text-white">Active &amp; Paused Rules</h2>
                <span class="text-xs font-medium text-slate-400">{{ $rules->count() }} total</span>
            </div>

            @if ($rules->isEmpty())
                <div class="px-6 py-12 text-center">
                    <p class="text-sm font-medium text-slate-300">No filter rules defined yet</p>
                    <p class="mt-1 text-xs text-slate-500">Create your first Include, Exclude, or Highlight rule on the left.</p>
                </div>
            @else
                <div class="divide-y divide-slate-800/80">
                    @foreach ($rules as $rule)
                        @php
                            $typeMeta = $typeStyles[$rule->type] ?? $typeStyles['exclude'];
                        @endphp
                        <div class="px-6 py-4 flex flex-col gap-3 hover:bg-slate-900 transition {{ $rule->is_active ? '' : 'opacity-60' }}">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $typeMeta['badge'] }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $typeMeta['dot'] }}"></span>
                                        {{ $typeMeta['label'] }}
                                    </span>

                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium border border-slate-700 bg-slate-950 text-slate-300">
                                        {{ $fieldLabels[$rule->match_field] ?? $rule->match_field }}
                                    </span>

                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium border border-slate-800 bg-slate-950 text-slate-400">
                                        {{ $rule->source ? 'Source: '.$rule->source->name : 'All Sources' }}
                                    </span>

                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium border border-slate-800 bg-slate-950 text-slate-400">
                                        {{ $rule->topic ? 'Topic: '.$rule->topic->name : 'All Topics' }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <button
                                        type="button"
                                        wire:click="toggleActive({{ $rule->id }})"
                                        class="rounded-lg border px-2.5 py-1 text-xs font-medium transition {{ $rule->is_active ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300 hover:bg-emerald-500/20' : 'border-slate-700 bg-slate-950 text-slate-400 hover:text-white' }}"
                                    >
                                        {{ $rule->is_active ? 'Active' : 'Paused' }}
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="edit({{ $rule->id }})"
                                        class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-1 text-xs font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        @click="confirmingDeleteId = {{ $rule->id }}"
                                        class="rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-1 text-xs font-medium text-rose-300 hover:bg-rose-500/20 transition"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($rule->keywords ?? [] as $keyword)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-mono bg-slate-950 border border-slate-800 text-slate-200">
                                        {{ $keyword }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div
        x-show="confirmingDeleteId !== null"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
    >
        <div
            @click.outside="confirmingDeleteId = null"
            class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-2xl"
        >
            <h3 class="text-lg font-semibold text-white">Delete filter rule?</h3>
            <p class="mt-2 text-sm text-slate-400">
                Existing entries will keep their current status, and future ingested entries will no longer be evaluated against this rule.
            </p>
            <div class="mt-6 flex items-center justify-end gap-3">
                <button
                    type="button"
                    @click="confirmingDeleteId = null"
                    class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-700 transition"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    @click="$wire.delete(confirmingDeleteId); confirmingDeleteId = null"
                    class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500 transition"
                >
                    Delete Rule
                </button>
            </div>
        </div>
    </div>
</div>
