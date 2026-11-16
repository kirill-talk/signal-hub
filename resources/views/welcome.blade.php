<!DOCTYPE html>
<html lang="en" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Signal-to-Noise Hub — Smart Feed Aggregator & Noise Filter</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased font-sans flex flex-col selection:bg-indigo-500 selection:text-white">
    <!-- Navbar -->
    <header class="sticky top-0 z-40 border-b border-slate-800/80 bg-slate-950/80 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-3 group">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-500 via-violet-500 to-sky-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/20 group-hover:scale-105 transition-transform">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                </div>
                <div>
                    <span class="text-sm font-bold tracking-tight text-white block leading-tight">Signal-to-Noise Hub</span>
                    <span class="text-[11px] text-slate-400 block leading-tight">Curated Feed Engine</span>
                </div>
            </a>

            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('feed.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold shadow-lg shadow-indigo-500/20 transition">
                        Open Feed Hub
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="px-3.5 py-2 rounded-lg text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-900 transition">
                        Sign In
                    </a>
                    <a href="{{ route('register') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold shadow-lg shadow-indigo-500/20 transition">
                        Get Started
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <main class="flex-1">
        <!-- Hero Section -->
        <section class="relative overflow-hidden pt-16 pb-20 sm:pt-24 sm:pb-28">
            <div class="absolute inset-0 -z-10 pointer-events-none">
                <div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[720px] h-[360px] bg-gradient-to-tr from-indigo-500/20 via-violet-500/15 to-sky-500/10 blur-3xl rounded-full"></div>
            </div>

            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-xs font-semibold mb-6">
                    <span class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></span>
                    Self-Hosted Feed Aggregator & Filter
                </div>

                <h1 class="text-4xl sm:text-6xl font-extrabold text-white tracking-tight leading-tight sm:leading-none">
                    Turn the firehose into <span class="bg-gradient-to-r from-indigo-400 via-sky-300 to-indigo-200 bg-clip-text text-transparent">pure signal</span>.
                </h1>

                <p class="mt-6 text-lg sm:text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed">
                    Aggregate RSS, Atom, and JSON feeds across multiple topics. Eliminate noise with rule-based regex and keyword filters, highlight critical updates, and consume clean feeds via web reader, private RSS, or daily email digest.
                </p>

                <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                    @auth
                        <a href="{{ route('feed.index') }}" class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold shadow-lg shadow-indigo-500/25 transition">
                            Enter Feed Hub &rarr;
                        </a>
                        <a href="{{ route('sources.index') }}" class="px-6 py-3 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-200 font-medium transition">
                            Manage Sources
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold shadow-lg shadow-indigo-500/25 transition">
                            Get Started Free &rarr;
                        </a>
                        <a href="{{ route('login') }}" class="px-6 py-3 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-200 font-medium transition">
                            Sign In to Account
                        </a>
                    @endauth
                </div>

                <!-- Demo badge / credentials hint -->
                <div class="mt-6 inline-flex items-center gap-2 text-xs text-slate-500 bg-slate-900/60 border border-slate-800/80 rounded-lg px-3 py-1.5">
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Demo account: <strong class="text-slate-300">demo@signalhub.test</strong> / <strong class="text-slate-300">password</strong></span>
                </div>
            </div>
        </section>

        <!-- Feature Grid -->
        <section class="py-16 border-t border-slate-900 bg-slate-950/40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-2xl mx-auto mb-14">
                    <h2 class="text-xs uppercase tracking-wider font-semibold text-indigo-400">Core Capabilities</h2>
                    <p class="mt-2 text-3xl font-bold text-white tracking-tight">Engineered to eliminate information overload</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Feature 1 -->
                    <div class="p-6 rounded-2xl bg-slate-900/60 border border-slate-800/80 hover:border-indigo-500/40 transition">
                        <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 5c7.18 0 13 5.82 13 13M6 11a7 7 0 017 7m-6 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
                        </div>
                        <h3 class="text-base font-semibold text-white">Universal Ingestion</h3>
                        <p class="mt-2 text-sm text-slate-400 leading-relaxed">
                            Connect standard RSS, Atom (GitHub Releases, Reddit, Blogs), and JSON Feeds. Automatic HTTP caching with ETag and 304 Not Modified support.
                        </p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="p-6 rounded-2xl bg-slate-900/60 border border-slate-800/80 hover:border-violet-500/40 transition">
                        <div class="w-12 h-12 rounded-xl bg-violet-500/10 border border-violet-500/20 text-violet-400 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        </div>
                        <h3 class="text-base font-semibold text-white">Signal-to-Noise Engine</h3>
                        <p class="mt-2 text-sm text-slate-400 leading-relaxed">
                            Granular 3-stage rule pipeline: <strong>Include</strong> (whitelist criteria), <strong>Exclude</strong> (suppress noise keywords/regex), and <strong>Highlight</strong> for priority badges.
                        </p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="p-6 rounded-2xl bg-slate-900/60 border border-slate-800/80 hover:border-sky-500/40 transition">
                        <div class="w-12 h-12 rounded-xl bg-sky-500/10 border border-sky-500/20 text-sky-400 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <h3 class="text-base font-semibold text-white">Distraction-Free Reader</h3>
                        <p class="mt-2 text-sm text-slate-400 leading-relaxed">
                            Organize feeds by topic tabs with Laravel Scout search. Fast slide-over article drawer with DOMPurify sanitization and Vim-style keyboard shortcuts (<code class="text-xs text-indigo-300">j/k/Enter/m/b</code>).
                        </p>
                    </div>

                    <!-- Feature 4 -->
                    <div class="p-6 rounded-2xl bg-slate-900/60 border border-slate-800/80 hover:border-emerald-500/40 transition">
                        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </div>
                        <h3 class="text-base font-semibold text-white">Private RSS & Email Digest</h3>
                        <p class="mt-2 text-sm text-slate-400 leading-relaxed">
                            Read your filtered output anywhere. Export tokenized private RSS feeds into Reeder or NetNewsWire, or receive scheduled daily HTML summaries in your local timezone.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Architecture & Tech Stack -->
        <section class="py-16 border-t border-slate-900">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-gradient-to-br from-slate-900/90 to-slate-950/90 border border-slate-800 rounded-2xl p-8 sm:p-10">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-indigo-400">Lightweight Architecture</span>
                            <h3 class="text-2xl font-bold text-white mt-1">Self-Contained & Production-Ready</h3>
                            <p class="text-sm text-slate-400 mt-2 max-w-xl">
                                Built on Laravel 13, Livewire 4, Alpine.js, and SQLite with WAL mode. Zero mandatory external infrastructure: database, queues, full-text search, and rate limiting run from a single compact deploy.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs font-mono">
                            <span class="px-3 py-1.5 rounded-lg bg-slate-800/80 border border-slate-700/60 text-slate-300">PHP 8.3+</span>
                            <span class="px-3 py-1.5 rounded-lg bg-slate-800/80 border border-slate-700/60 text-slate-300">Laravel 13</span>
                            <span class="px-3 py-1.5 rounded-lg bg-slate-800/80 border border-slate-700/60 text-slate-300">Livewire 4</span>
                            <span class="px-3 py-1.5 rounded-lg bg-slate-800/80 border border-slate-700/60 text-slate-300">Alpine.js</span>
                            <span class="px-3 py-1.5 rounded-lg bg-slate-800/80 border border-slate-700/60 text-slate-300">Tailwind CSS</span>
                            <span class="px-3 py-1.5 rounded-lg bg-slate-800/80 border border-slate-700/60 text-slate-300">SQLite WAL</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-900 bg-slate-950 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
            <p>&copy; {{ date('Y') }} Signal-to-Noise Hub. Open-source personal feed engine.</p>
            <div class="flex items-center gap-4">
                <a href="https://github.com/kirill-talk/signal-hub" target="_blank" rel="noopener noreferrer" class="hover:text-slate-300 transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/></svg>
                    GitHub Repository
                </a>
            </div>
        </div>
    </footer>
</body>
</html>
