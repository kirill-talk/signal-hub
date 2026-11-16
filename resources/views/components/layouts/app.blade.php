@props(['title' => 'Signal-to-Noise Hub'])
<!DOCTYPE html>
<html lang="en" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — Signal-to-Noise Hub</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    @livewireStyles
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased font-sans flex flex-col selection:bg-indigo-500 selection:text-white">
    <header x-data="{ mobileOpen: false }" class="sticky top-0 z-30 border-b border-slate-800/80 bg-slate-950/90 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-8">
                <a href="{{ route('sources.index') }}" class="flex items-center gap-3 group">
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

                <nav class="hidden md:flex items-center gap-1.5">
                    <a
                        href="{{ route('sources.index') }}"
                        class="px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('sources.*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-900' }}"
                    >
                        Sources
                    </a>
                    <a
                        href="{{ route('topics.index') }}"
                        class="px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('topics.*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-900' }}"
                    >
                        Topics
                    </a>
                    <a
                        href="{{ route('feed.index') }}"
                        class="px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('feed.*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-900' }}"
                    >
                        Feed Hub
                    </a>
                    <a
                        href="{{ route('rules.index') }}"
                        class="px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('rules.*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-900' }}"
                    >
                        Filter Rules
                    </a>
                    <a
                        href="{{ route('settings.index') }}"
                        class="px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('settings.*') ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-900' }}"
                    >
                        Settings
                    </a>
                </nav>
            </div>

            <div class="flex items-center gap-3">
                @auth
                    <div class="hidden sm:flex flex-col items-end">
                        <span class="text-xs font-semibold text-slate-200 leading-tight">{{ auth()->user()->name }}</span>
                        <span class="text-[11px] text-slate-400 leading-tight">{{ auth()->user()->email }}</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="hidden md:block">
                        @csrf
                        <button
                            type="submit"
                            class="px-3 py-1.5 rounded-lg text-xs font-medium text-slate-300 border border-slate-800 bg-slate-900 hover:bg-slate-800 hover:text-white transition"
                        >
                            Sign out
                        </button>
                    </form>
                @endauth

                <button
                    type="button"
                    @click="mobileOpen = !mobileOpen"
                    class="md:hidden inline-flex items-center justify-center p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
                    aria-label="Toggle navigation menu"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path x-show="!mobileOpen" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        <path x-show="mobileOpen" x-cloak stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <div x-show="mobileOpen" x-cloak class="md:hidden border-t border-slate-800 bg-slate-950 px-4 py-3 space-y-1.5">
            <a
                href="{{ route('feed.index') }}"
                class="block px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('feed.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-900' }}"
            >
                Feed Hub
            </a>
            <a
                href="{{ route('sources.index') }}"
                class="block px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('sources.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-900' }}"
            >
                Sources
            </a>
            <a
                href="{{ route('topics.index') }}"
                class="block px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('topics.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-900' }}"
            >
                Topics
            </a>
            <a
                href="{{ route('rules.index') }}"
                class="block px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('rules.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-900' }}"
            >
                Filter Rules
            </a>
            <a
                href="{{ route('settings.index') }}"
                class="block px-3 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('settings.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-900' }}"
            >
                Settings
            </a>
            @auth
                <div class="pt-2 mt-2 border-t border-slate-800 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-slate-200">{{ auth()->user()->name }}</p>
                        <p class="text-[11px] text-slate-400">{{ auth()->user()->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-medium text-slate-300 border border-slate-800 bg-slate-900 hover:bg-slate-800">
                            Sign out
                        </button>
                    </form>
                </div>
            @endauth
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{ $slot }}
    </main>

    <footer class="border-t border-slate-900 py-5 text-center text-xs text-slate-500">
        Signal-to-Noise Hub &bull; Laravel 13 &amp; Livewire 4
    </footer>

    @livewireScripts
</body>
</html>
