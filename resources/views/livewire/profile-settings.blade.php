<div class="space-y-8 max-w-4xl">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-white">Settings</h1>
        <p class="mt-1 text-sm text-slate-400">
            Configure your email digest schedule, timezone, and private RSS feed endpoints.
        </p>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl">
        <h2 class="text-base font-semibold text-white">Email Digest Preferences</h2>
        <p class="mt-1 text-xs text-slate-400">
            Digests collect unread, non-noise entries and arrive according to your local timezone.
        </p>

        <form wire:submit="save" class="mt-5 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="settings-timezone" class="block text-sm font-medium text-slate-300">Timezone</label>
                    <select
                        id="settings-timezone"
                        wire:model="timezone"
                        class="mt-1.5 block w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3.5 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                    >
                        @foreach ($timezones as $tz)
                            <option value="{{ $tz }}">{{ $tz }}</option>
                        @endforeach
                    </select>
                    @error('timezone')
                        <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="settings-frequency" class="block text-sm font-medium text-slate-300">Digest Frequency</label>
                    <select
                        id="settings-frequency"
                        wire:model="digest_frequency"
                        class="mt-1.5 block w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3.5 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                    >
                        <option value="none">Disabled</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                    </select>
                    @error('digest_frequency')
                        <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="settings-time" class="block text-sm font-medium text-slate-300">Delivery Time (24h)</label>
                    <input
                        id="settings-time"
                        type="time"
                        wire:model="digest_time"
                        class="mt-1.5 block w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3.5 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                    >
                    @error('digest_time')
                        <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <span class="text-xs text-slate-500">
                    Last digest sent: {{ $user->last_digest_sent_at ? $user->last_digest_sent_at->diffForHumans() : 'Never' }}
                </span>
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-500 disabled:opacity-50 transition"
                >
                    <span wire:loading.remove wire:target="save">Save Preferences</span>
                    <span wire:loading wire:target="save">Saving...</span>
                </button>
            </div>
        </form>
    </div>

    <div
        x-data="{ confirmingRegenerate: false, copiedUrl: null }"
        class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-5"
    >
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-white">Private RSS Feeds</h2>
                <p class="mt-1 text-xs text-slate-400">
                    Subscribe in any external RSS reader. Only entries marked as Signal (non-noise) are included.
                </p>
            </div>

            <button
                type="button"
                @click="confirmingRegenerate = true"
                class="self-start rounded-xl border border-rose-500/30 bg-rose-500/10 px-3.5 py-2 text-xs font-semibold text-rose-300 hover:bg-rose-500/20 transition"
            >
                Regenerate Secret Token
            </button>
        </div>

        @php
            $allFeedUrl = route('feeds.private', ['token' => $user->feed_token]);
        @endphp

        <div class="space-y-3">
            <div class="rounded-xl border border-slate-800 bg-slate-950/80 p-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-xs font-semibold text-indigo-300 block">All Signal Entries</span>
                    <code class="text-xs text-slate-300 break-all">{{ $allFeedUrl }}</code>
                </div>
                <button
                    type="button"
                    @click="navigator.clipboard.writeText(@js($allFeedUrl)); copiedUrl = 'all'; setTimeout(() => copiedUrl = null, 2000)"
                    class="shrink-0 rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition"
                >
                    <span x-show="copiedUrl !== 'all'">Copy URL</span>
                    <span x-show="copiedUrl === 'all'" x-cloak class="text-emerald-300">Copied!</span>
                </button>
            </div>

            @foreach ($topics as $topic)
                @php
                    $topicFeedUrl = route('feeds.private.topic', ['token' => $user->feed_token, 'topicSlug' => $topic->slug]);
                @endphp
                <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="min-w-0">
                        <span class="text-xs font-semibold text-slate-200 block">{{ $topic->name }}</span>
                        <code class="text-xs text-slate-400 break-all">{{ $topicFeedUrl }}</code>
                    </div>
                    <button
                        type="button"
                        @click="navigator.clipboard.writeText(@js($topicFeedUrl)); copiedUrl = '{{ $topic->id }}'; setTimeout(() => copiedUrl = null, 2000)"
                        class="shrink-0 rounded-lg border border-slate-700 bg-slate-900 px-3 py-1.5 text-xs font-medium text-slate-300 hover:bg-slate-800 hover:text-white transition"
                    >
                        <span x-show="copiedUrl !== '{{ $topic->id }}'">Copy URL</span>
                        <span x-show="copiedUrl === '{{ $topic->id }}'" x-cloak class="text-emerald-300">Copied!</span>
                    </button>
                </div>
            @endforeach
        </div>

        <div
            x-show="confirmingRegenerate"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
        >
            <div
                @click.outside="confirmingRegenerate = false"
                class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-2xl"
            >
                <h3 class="text-lg font-semibold text-white">Regenerate feed token?</h3>
                <p class="mt-2 text-sm text-slate-400">
                    Your existing private RSS links will immediately stop working and you will need to update your RSS reader with the new URL.
                </p>
                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        @click="confirmingRegenerate = false"
                        class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-medium text-slate-300 hover:bg-slate-700 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="$wire.regenerateFeedToken(); confirmingRegenerate = false"
                        class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500 transition"
                    >
                        Regenerate Token
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
