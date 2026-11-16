<x-layouts.guest title="Sign In">
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-white">Sign in to your account</h2>
        <p class="mt-1 text-sm text-slate-400">Manage your feeds, topics, and signal filters.</p>
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-slate-300">Email address</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="email"
                class="mt-1.5 block w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                placeholder="demo@signalhub.test"
            >
            @error('email')
                <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-300">Password</label>
            <input
                id="password"
                name="password"
                type="password"
                required
                autocomplete="current-password"
                class="mt-1.5 block w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                placeholder="••••••••"
            >
            @error('password')
                <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between">
            <label class="inline-flex items-center gap-2 text-sm text-slate-400 cursor-pointer">
                <input
                    type="checkbox"
                    name="remember"
                    class="rounded border-slate-700 bg-slate-950 text-indigo-500 focus:ring-indigo-500/30"
                >
                <span>Remember me</span>
            </label>
        </div>

        <button
            type="submit"
            class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 transition"
        >
            Sign in
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-400">
        Don't have an account yet?
        <a href="{{ route('register') }}" class="font-medium text-indigo-400 hover:text-indigo-300 transition">
            Create account
        </a>
    </p>
</x-layouts.guest>
