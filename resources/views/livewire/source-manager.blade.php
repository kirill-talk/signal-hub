@php
    $colorStyles = [
        'indigo' => 'bg-indigo-500/15 text-indigo-300 border-indigo-500/30',
        'emerald' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
        'amber' => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
        'rose' => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
        'sky' => 'bg-sky-500/15 text-sky-300 border-sky-500/30',
        'violet' => 'bg-violet-500/15 text-violet-300 border-violet-500/30',
        'slate' => 'bg-slate-500/15 text-slate-300 border-slate-500/30',
    ];

    $adapterLabels = [
        'rss' => ['label' => 'RSS / Atom', 'class' => 'bg-sky-500/10 text-sky-300 border-sky-500/20'],
        'github' => ['label' => 'GitHub', 'class' => 'bg-violet-500/10 text-violet-300 border-violet-500/20'],
        'json' => ['label' => 'JSON Feed', 'class' => 'bg-amber-500/10 text-amber-300 border-amber-500/20'],
    ];
@endphp

<div
    x-data="{ confirmingDeleteId: null, confirmingDeleteName: '' }"
    @keydown.escape.window="confirmingDeleteId = null"
    class="space-y-8"
>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Sources</h1>
            <p class="mt-1 text-sm text-slate-400">
                Connect RSS/Atom blogs, GitHub releases, Reddit feeds, and JSON feeds.
            </p>
        </div>
        <a
            href="{{ route('topics.index') }}"
            class="inline-flex items-center gap-2 self-start rounded-xl border border-slate-800 bg-slate-900 px-3.5 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition"
        >
            Manage Topics
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
            </svg>
        </a>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        <div class="lg:col-span-4 bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <h2 class="text-base font-semibold text-white">
                {{ $editingId ? 'Edit Source' : 'Add Source' }}
            </h2>
            <p class="mt-1 text-xs text-slate-400">
                Paste a feed URL and the adapter type will be detected automatically.
            </p>

            <form wire:submit="save" class="mt-5 space-y-4">
                <div>
                    <label for="source-name" class="block text-sm font-medium text-slate-300">Source Name</label>
                    <input
                        id="source-name"
                        type="text"
                        wire:model="name"
                        placeholder="e.g. Laravel Releases"
                        class="mt-1.5 block w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                    >
                    @error('name')
                        <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="source-url" class="block text-sm font-medium text-slate-300">Feed URL</label>
                    <input
                        id="source-url"
                        type="url"
                        wire:model.live.debounce.300ms="url"
                        placeholder="https://github.com/laravel/framework/releases.atom"
                        class="mt-1.5 block w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                    >
                    @error('url')
                        <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="source-topic" class="block text-sm font-medium text-slate-300">Topic</label>
                    <select
                        id="source-topic"
                        wire:model="topic_id"
                        class="mt-1.5 block w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3.5 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                    >
                        <option value="">No topic</option>
                        @foreach ($topics as $topic)
                            <option value="{{ $topic->id }}">{{ $topic->name }}</option>
                        @endforeach
                    </select>
                    @error('topic_id')
                        <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="source-adapter" class="block text-sm font-medium text-slate-300">Adapter</label>
                        <select
                            id="source-adapter"
                            wire:model="adapter_type"
                            class="mt-1.5 block w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                        >
                            <option value="rss">RSS / Atom</option>
                            <option value="github">GitHub</option>
                            <option value="json">JSON Feed</option>
                        </select>
                        @error('adapter_type')
                            <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="source-interval" class="block text-sm font-medium text-slate-300">Interval</label>
                        <select
                            id="source-interval"
                            wire:model="fetch_interval_minutes"
                            class="mt-1.5 block w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                        >
                            <option value="15">15 min</option>
                            <option value="30">30 min</option>
                            <option value="60">1 hour</option>
                            <option value="180">3 hours</option>
                            <option value="360">6 hours</option>
                            <option value="720">12 hours</option>
                            <option value="1440">24 hours</option>
                        </select>
                        @error('fetch_interval_minutes')
                            <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="flex-1 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-500 disabled:opacity-50 transition"
                    >
                        <span wire:loading.remove wire:target="save">
                            {{ $editingId ? 'Save Changes' : 'Add Source' }}
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

        <div class="lg:col-span-8 bg-slate-900/80 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                <h2 class="text-base font-semibold text-white">Configured Sources</h2>
                <span class="text-xs font-medium text-slate-400">{{ $sources->count() }} total</span>
            </div>

            @if ($sources->isEmpty())
                <div class="px-6 py-12 text-center">
                    <p class="text-sm font-medium text-slate-300">No sources connected yet</p>
                    <p class="mt-1 text-xs text-slate-500">Add your first RSS, Atom, GitHub, or JSON feed on the left.</p>
                </div>
            @else
                <div class="divide-y divide-slate-800/80">
                    @foreach ($sources as $source)
                        @php
                            $adapter = $adapterLabels[$source->adapter_type] ?? $adapterLabels['rss'];
                            $topicColor = $source->topic ? ($colorStyles[$source->topic->color] ?? $colorStyles['indigo']) : null;
                        @endphp
                        <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 hover:bg-slate-900 transition">
                            <div class="min-w-0 space-y-1.5">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-semibold text-white">{{ $source->name }}</span>

                                    @if ($source->status === 'failing')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-rose-500/15 text-rose-300 border border-rose-500/30">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                            Failing ({{ $source->error_count }})
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-500/10 text-emerald-300 border border-emerald-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                            Active
                                        </span>
                                    @endif

                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium border {{ $adapter['class'] }}">
                                        {{ $adapter['label'] }}
                                    </span>

                                    @if ($source->topic)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium border {{ $topicColor }}">
                                            {{ $source->topic->name }}
                                        </span>
                                    @endif
                                </div>

                                <p class="text-xs text-slate-400 truncate max-w-xl" title="{{ $source->url }}">
                                    {{ $source->url }}
                                </p>

                                <div class="flex items-center gap-3 flex-wrap text-[11px] text-slate-500">
                                    <span>{{ $source->entries_count ?? 0 }} {{ \Illuminate\Support\Str::plural('entry', $source->entries_count ?? 0) }}</span>
                                    <span>&bull;</span>
                                    <span>Every {{ $source->fetch_interval_minutes }} min</span>
                                    <span>&bull;</span>
                                    <span>
                                        Last fetched:
                                        {{ $source->last_fetched_at ? $source->last_fetched_at->diffForHumans() : 'Never' }}
                                    </span>
                                    @if ($source->etag || $source->last_modified)
                                        <span>&bull;</span>
                                        <span class="text-slate-400">ETag cached</span>
                                    @endif
                                </div>

                                @if ($source->status === 'failing' && $source->last_error)
                                    <p class="text-[11px] text-rose-400 bg-rose-500/10 border border-rose-500/20 rounded-lg px-2.5 py-1">
                                        Last error: {{ \Illuminate\Support\Str::limit($source->last_error, 120) }}
                                    </p>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <button
                                    type="button"
                                    wire:click="fetchNow({{ $source->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="fetchNow({{ $source->id }})"
                                    class="rounded-lg border border-indigo-500/30 bg-indigo-500/10 px-3 py-1.5 text-xs font-medium text-indigo-300 hover:bg-indigo-500/20 disabled:opacity-50 transition"
                                >
                                    <span wire:loading.remove wire:target="fetchNow({{ $source->id }})">Fetch Now</span>
                                    <span wire:loading wire:target="fetchNow({{ $source->id }})">Queuing...</span>
                                </button>
                                <button
                                    type="button"
                                    wire:click="edit({{ $source->id }})"
                                    class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-1.5 text-xs font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    @click="confirmingDeleteId = {{ $source->id }}; confirmingDeleteName = @js($source->name)"
                                    class="rounded-lg border border-rose-500/30 bg-rose-500/10 px-3 py-1.5 text-xs font-medium text-rose-300 hover:bg-rose-500/20 transition"
                                >
                                    Delete
                                </button>
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
            <h3 class="text-lg font-semibold text-white">Delete source?</h3>
            <p class="mt-2 text-sm text-slate-400">
                Are you sure you want to delete <span class="font-semibold text-white" x-text="confirmingDeleteName"></span>?
                All fetched entries from this source will also be removed.
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
                    Delete Source
                </button>
            </div>
        </div>
    </div>
</div>
