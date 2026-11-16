@php
    $colorStyles = [
        'indigo' => ['pill' => 'bg-indigo-500/15 text-indigo-300 border-indigo-500/30', 'dot' => 'bg-indigo-400', 'ring' => 'peer-checked:ring-indigo-500'],
        'emerald' => ['pill' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30', 'dot' => 'bg-emerald-400', 'ring' => 'peer-checked:ring-emerald-500'],
        'amber' => ['pill' => 'bg-amber-500/15 text-amber-300 border-amber-500/30', 'dot' => 'bg-amber-400', 'ring' => 'peer-checked:ring-amber-500'],
        'rose' => ['pill' => 'bg-rose-500/15 text-rose-300 border-rose-500/30', 'dot' => 'bg-rose-400', 'ring' => 'peer-checked:ring-rose-500'],
        'sky' => ['pill' => 'bg-sky-500/15 text-sky-300 border-sky-500/30', 'dot' => 'bg-sky-400', 'ring' => 'peer-checked:ring-sky-500'],
        'violet' => ['pill' => 'bg-violet-500/15 text-violet-300 border-violet-500/30', 'dot' => 'bg-violet-400', 'ring' => 'peer-checked:ring-violet-500'],
        'slate' => ['pill' => 'bg-slate-500/15 text-slate-300 border-slate-500/30', 'dot' => 'bg-slate-400', 'ring' => 'peer-checked:ring-slate-500'],
    ];
@endphp

<div
    x-data="{ confirmingDeleteId: null, confirmingDeleteName: '' }"
    @keydown.escape.window="confirmingDeleteId = null"
    class="space-y-8"
>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Topics</h1>
            <p class="mt-1 text-sm text-slate-400">
                Organize your feeds into focused buckets for filtering, RSS exports, and email digests.
            </p>
        </div>
        <a
            href="{{ route('sources.index') }}"
            class="inline-flex items-center gap-2 self-start rounded-xl border border-slate-800 bg-slate-900 px-3.5 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition"
        >
            Manage Sources
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
            </svg>
        </a>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300 flex items-center justify-between">
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        <div class="lg:col-span-4 bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <h2 class="text-base font-semibold text-white">
                {{ $editingId ? 'Edit Topic' : 'New Topic' }}
            </h2>
            <p class="mt-1 text-xs text-slate-400">
                {{ $editingId ? 'Update the topic name or badge color.' : 'Create a category like Backend, DevOps, or Security.' }}
            </p>

            <form wire:submit="save" class="mt-5 space-y-5">
                <div>
                    <label for="topic-name" class="block text-sm font-medium text-slate-300">Name</label>
                    <input
                        id="topic-name"
                        type="text"
                        wire:model="name"
                        placeholder="e.g. Backend Engineering"
                        class="mt-1.5 block w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                    >
                    @error('name')
                        <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <span class="block text-sm font-medium text-slate-300 mb-2">Badge Color</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($colorStyles as $key => $style)
                            <label class="cursor-pointer">
                                <input
                                    type="radio"
                                    wire:model="color"
                                    value="{{ $key }}"
                                    class="sr-only peer"
                                >
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border transition {{ $style['pill'] }} peer-checked:ring-2 {{ $style['ring'] }} peer-checked:ring-offset-2 peer-checked:ring-offset-slate-900">
                                    <span class="w-2 h-2 rounded-full {{ $style['dot'] }}"></span>
                                    {{ ucfirst($key) }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('color')
                        <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="flex-1 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-500 disabled:opacity-50 transition"
                    >
                        <span wire:loading.remove wire:target="save">
                            {{ $editingId ? 'Save Changes' : 'Create Topic' }}
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
                <h2 class="text-base font-semibold text-white">Your Topics</h2>
                <span class="text-xs font-medium text-slate-400">{{ $topics->count() }} total</span>
            </div>

            @if ($topics->isEmpty())
                <div class="px-6 py-12 text-center">
                    <p class="text-sm font-medium text-slate-300">No topics created yet</p>
                    <p class="mt-1 text-xs text-slate-500">Add your first topic on the left to group incoming sources.</p>
                </div>
            @else
                <div class="divide-y divide-slate-800/80">
                    @foreach ($topics as $topic)
                        @php
                            $style = $colorStyles[$topic->color] ?? $colorStyles['indigo'];
                        @endphp
                        <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 hover:bg-slate-900 transition">
                            <div class="min-w-0 space-y-1">
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border {{ $style['pill'] }}">
                                        <span class="w-2 h-2 rounded-full {{ $style['dot'] }}"></span>
                                        {{ $topic->name }}
                                    </span>
                                    <code class="text-xs text-slate-500 bg-slate-950 px-2 py-0.5 rounded border border-slate-800">
                                        /{{ $topic->slug }}
                                    </code>
                                </div>
                                <div class="flex items-center gap-4 text-xs text-slate-400">
                                    <span>{{ $topic->sources_count }} {{ \Illuminate\Support\Str::plural('source', $topic->sources_count) }}</span>
                                    <span>&bull;</span>
                                    <span>{{ $topic->entries_count }} {{ \Illuminate\Support\Str::plural('entry', $topic->entries_count) }}</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <button
                                    type="button"
                                    wire:click="edit({{ $topic->id }})"
                                    class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-1.5 text-xs font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    @click="confirmingDeleteId = {{ $topic->id }}; confirmingDeleteName = @js($topic->name)"
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
            <h3 class="text-lg font-semibold text-white">Delete topic?</h3>
            <p class="mt-2 text-sm text-slate-400">
                Are you sure you want to delete <span class="font-semibold text-white" x-text="confirmingDeleteName"></span>?
                Sources and entries assigned to this topic will be preserved without a topic.
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
                    Delete Topic
                </button>
            </div>
        </div>
    </div>
</div>
