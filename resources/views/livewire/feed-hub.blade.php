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

    $tabs = [
        'signal' => ['label' => 'Signal', 'count' => $counts['signal']],
        'unread' => ['label' => 'Unread', 'count' => $counts['unread']],
        'bookmarks' => ['label' => 'Bookmarks', 'count' => $counts['bookmarks']],
        'all' => ['label' => 'All Stream', 'count' => $counts['all']],
    ];
@endphp

<div
    x-data="{
        focusedIndex: -1,
        entryIds() {
            return Array.from(this.$el.querySelectorAll('[data-entry-id]')).map(el => Number(el.dataset.entryId));
        },
        activeEntryId() {
            const selected = @js($selectedEntryId);
            if (selected) return Number(selected);
            const ids = this.entryIds();
            return this.focusedIndex >= 0 && this.focusedIndex < ids.length ? ids[this.focusedIndex] : null;
        },
        scrollFocused() {
            const ids = this.entryIds();
            if (this.focusedIndex < 0 || this.focusedIndex >= ids.length) return;
            const target = this.$el.querySelector(`[data-entry-id='${ids[this.focusedIndex]}']`);
            if (target) target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        },
        handleKey(event) {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName) || event.target.isContentEditable) return;
            if (event.metaKey || event.ctrlKey || event.altKey) return;

            const ids = this.entryIds();

            if (event.key === 'j') {
                if (ids.length === 0) return;
                event.preventDefault();
                this.focusedIndex = Math.min(ids.length - 1, this.focusedIndex + 1);
                this.scrollFocused();
            } else if (event.key === 'k') {
                if (ids.length === 0) return;
                event.preventDefault();
                this.focusedIndex = this.focusedIndex <= 0 ? 0 : this.focusedIndex - 1;
                this.scrollFocused();
            } else if (event.key === 'Enter' && this.focusedIndex >= 0 && this.focusedIndex < ids.length) {
                event.preventDefault();
                $wire.selectEntry(ids[this.focusedIndex]);
            } else if (event.key === 'm') {
                const id = this.activeEntryId();
                if (id) {
                    event.preventDefault();
                    $wire.toggleRead(id);
                }
            } else if (event.key === 'b') {
                const id = this.activeEntryId();
                if (id) {
                    event.preventDefault();
                    $wire.toggleBookmark(id);
                }
            } else if (event.key === 'Escape' && $wire.selectedEntryId) {
                event.preventDefault();
                $wire.closeReader();
            }
        }
    }"
    @keydown.window="handleKey($event)"
    class="space-y-6"
>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-white">Feed Hub</h1>
            <p class="mt-1 text-sm text-slate-400">
                High-signal reading stream across all connected feeds.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="hidden lg:flex items-center gap-2 text-[11px] text-slate-500">
                <span class="px-1.5 py-0.5 rounded bg-slate-900 border border-slate-800 font-mono text-slate-300">j/k</span>
                <span>Navigate</span>
                <span class="px-1.5 py-0.5 rounded bg-slate-900 border border-slate-800 font-mono text-slate-300">Enter</span>
                <span>Open</span>
                <span class="px-1.5 py-0.5 rounded bg-slate-900 border border-slate-800 font-mono text-slate-300">m</span>
                <span>Read</span>
                <span class="px-1.5 py-0.5 rounded bg-slate-900 border border-slate-800 font-mono text-slate-300">b</span>
                <span>Bookmark</span>
            </div>

            <button
                type="button"
                wire:click="markAllAsRead"
                wire:loading.attr="disabled"
                class="rounded-xl border border-slate-700 bg-slate-900 px-3.5 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-800 hover:text-white disabled:opacity-50 transition"
            >
                Mark All Read
            </button>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 space-y-4 shadow-xl">
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-800 pb-3">
            @foreach ($tabs as $tabKey => $tabMeta)
                <button
                    type="button"
                    wire:click="$set('tab', '{{ $tabKey }}')"
                    @click="focusedIndex = -1"
                    class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-semibold transition {{ $tab === $tabKey ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/70' }}"
                >
                    <span>{{ $tabMeta['label'] }}</span>
                    <span class="px-1.5 py-0.5 rounded-md text-[10px] {{ $tab === $tabKey ? 'bg-indigo-500/40 text-white' : 'bg-slate-950 text-slate-400' }}">
                        {{ $tabMeta['count'] }}
                    </span>
                </button>
            @endforeach
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-6 relative">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search titles, summaries, or authors..."
                    class="w-full rounded-xl border border-slate-700 bg-slate-950/80 pl-9 pr-3.5 py-2 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                >
                <svg class="w-4 h-4 text-slate-500 absolute left-3 top-2.5 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                </svg>
            </div>

            <div class="md:col-span-3">
                <select
                    wire:model.live="topicId"
                    aria-label="Filter by topic"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                >
                    <option value="">All Topics</option>
                    @foreach ($topics as $topic)
                        <option value="{{ $topic->id }}">{{ $topic->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-3">
                <select
                    wire:model.live="sourceId"
                    aria-label="Filter by source"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                >
                    <option value="">All Sources</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->id }}">{{ $source->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
        @if ($entries->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="text-base font-medium text-slate-300">No entries match your filters</p>
                <p class="mt-1 text-xs text-slate-500">
                    Try switching tabs, clearing the search query, or fetching new items from your sources.
                </p>
            </div>
        @else
            <div class="divide-y divide-slate-800/80">
                @foreach ($entries as $index => $entry)
                    @php
                        $topicColor = $entry->topic ? ($colorStyles[$entry->topic->color] ?? $colorStyles['indigo']) : null;
                    @endphp
                    <div
                        data-entry-id="{{ $entry->id }}"
                        :class="focusedIndex === {{ $index }} ? 'ring-1 ring-inset ring-indigo-500/60 bg-slate-900' : ''"
                        class="px-5 py-4 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 hover:bg-slate-900/90 transition {{ $entry->is_read ? 'opacity-70' : '' }}"
                    >
                        <div class="min-w-0 flex-1 space-y-1.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                @if (! $entry->is_read)
                                    <span class="w-2 h-2 rounded-full bg-indigo-400 shrink-0" title="Unread"></span>
                                @endif

                                @if ($entry->is_highlighted)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider bg-amber-500/15 text-amber-300 border border-amber-500/30">
                                        Highlight
                                    </span>
                                @endif

                                @if ($entry->is_noise)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider bg-rose-500/15 text-rose-300 border border-rose-500/30">
                                        Noise
                                    </span>
                                @endif

                                @if ($entry->topic)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium border {{ $topicColor }}">
                                        {{ $entry->topic->name }}
                                    </span>
                                @endif

                                <span class="text-xs font-medium text-slate-400">
                                    {{ $entry->source?->name ?? 'Unknown Source' }}
                                </span>

                                <span class="text-xs text-slate-600">&bull;</span>

                                <span class="text-xs text-slate-500">
                                    {{ $entry->published_at ? $entry->published_at->diffForHumans() : $entry->created_at->diffForHumans() }}
                                </span>
                            </div>

                            <button
                                type="button"
                                wire:click="selectEntry({{ $entry->id }})"
                                @click="focusedIndex = {{ $index }}"
                                class="text-left block w-full group"
                            >
                                <h2 class="text-base font-semibold text-white group-hover:text-indigo-300 transition {{ $entry->is_read ? 'font-medium text-slate-300' : '' }}">
                                    {{ $entry->title }}
                                </h2>
                            </button>

                            @if ($entry->summary)
                                <p class="text-xs text-slate-400 line-clamp-2">
                                    {{ \Illuminate\Support\Str::limit(html_entity_decode(strip_tags($entry->summary), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 180) }}
                                </p>
                            @endif
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0">
                            <button
                                type="button"
                                wire:click="toggleBookmark({{ $entry->id }})"
                                class="rounded-lg border px-2.5 py-1.5 text-xs font-medium transition {{ $entry->is_bookmarked ? 'border-amber-500/40 bg-amber-500/15 text-amber-300' : 'border-slate-800 bg-slate-950 text-slate-400 hover:text-white hover:border-slate-700' }}"
                                title="Toggle bookmark"
                            >
                                {{ $entry->is_bookmarked ? 'Bookmarked' : 'Bookmark' }}
                            </button>

                            <button
                                type="button"
                                wire:click="toggleRead({{ $entry->id }})"
                                class="rounded-lg border border-slate-800 bg-slate-950 px-2.5 py-1.5 text-xs font-medium text-slate-400 hover:text-white hover:border-slate-700 transition"
                            >
                                {{ $entry->is_read ? 'Mark Unread' : 'Mark Read' }}
                            </button>

                            <button
                                type="button"
                                wire:click="toggleNoise({{ $entry->id }})"
                                class="rounded-lg border px-2.5 py-1.5 text-xs font-medium transition {{ $entry->is_noise ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300 hover:bg-emerald-500/20' : 'border-slate-800 bg-slate-950 text-slate-400 hover:text-rose-300 hover:border-rose-500/30' }}"
                            >
                                {{ $entry->is_noise ? 'Restore' : 'Noise' }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($entries->hasPages())
                <div class="px-6 py-4 border-t border-slate-800">
                    {{ $entries->links() }}
                </div>
            @endif
        @endif
    </div>

    @if ($selectedEntry)
        <div
            class="fixed inset-0 z-50 flex justify-end bg-slate-950/80 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
        >
            <div wire:click="closeReader" class="flex-1"></div>

            <div class="w-full max-w-2xl bg-slate-900 border-l border-slate-800 h-full flex flex-col shadow-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-2 min-w-0">
                        @if ($selectedEntry->topic)
                            @php
                                $drawerTopicColor = $colorStyles[$selectedEntry->topic->color] ?? $colorStyles['indigo'];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $drawerTopicColor }}">
                                {{ $selectedEntry->topic->name }}
                            </span>
                        @endif
                        <span class="text-xs text-slate-400 truncate">
                            {{ $selectedEntry->source?->name ?? 'Source' }}
                        </span>
                    </div>

                    <button
                        type="button"
                        wire:click="closeReader"
                        class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs font-medium text-slate-300 hover:text-white transition"
                    >
                        Close (Esc)
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-6 space-y-6">
                    <div class="space-y-3">
                        <h2 class="text-xl font-bold text-white leading-snug">
                            {{ $selectedEntry->title }}
                        </h2>
                        <div class="flex flex-wrap items-center gap-3 text-xs text-slate-400">
                            @if ($selectedEntry->author)
                                <span>By <strong class="text-slate-200">{{ $selectedEntry->author }}</strong></span>
                                <span>&bull;</span>
                            @endif
                            <span>
                                {{ $selectedEntry->published_at ? $selectedEntry->published_at->format('M j, Y H:i') : $selectedEntry->created_at->format('M j, Y H:i') }}
                            </span>
                        </div>
                    </div>

                    <div class="text-sm text-slate-200 leading-relaxed space-y-4 [&_a]:text-indigo-400 [&_a]:underline [&_pre]:bg-slate-950 [&_pre]:p-3 [&_pre]:rounded-xl [&_pre]:overflow-x-auto [&_code]:bg-slate-950 [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_blockquote]:border-l-2 [&_blockquote]:border-slate-700 [&_blockquote]:pl-4 [&_blockquote]:text-slate-400">
                        @if ($selectedEntry->summary)
                            {!! $selectedEntry->summary !!}
                        @else
                            <p class="text-slate-400 italic">No preview summary provided by this source.</p>
                        @endif
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-slate-800 bg-slate-950/60 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="toggleBookmark({{ $selectedEntry->id }})"
                            class="rounded-xl border px-3.5 py-2 text-xs font-semibold transition {{ $selectedEntry->is_bookmarked ? 'border-amber-500/40 bg-amber-500/15 text-amber-300' : 'border-slate-700 bg-slate-800 text-slate-300 hover:text-white' }}"
                        >
                            {{ $selectedEntry->is_bookmarked ? 'Bookmarked' : 'Bookmark' }}
                        </button>
                        <button
                            type="button"
                            wire:click="toggleNoise({{ $selectedEntry->id }})"
                            class="rounded-xl border border-slate-700 bg-slate-800 px-3.5 py-2 text-xs font-semibold text-slate-300 hover:text-white transition"
                        >
                            {{ $selectedEntry->is_noise ? 'Restore from Noise' : 'Mark as Noise' }}
                        </button>
                    </div>

                    @if ($selectedEntry->url)
                        <a
                            href="{{ $selectedEntry->url }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-500 transition"
                        >
                            Open Original
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 3h7v7m0-7L10 14" />
                            </svg>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
