<x-guest-layout>
    <div class="text-center mb-8">
        <h2 class="text-2xl font-bold text-indigo-950 tracking-tight">Welcome back</h2>
        <p class="mt-2 text-sm text-gray-500">Sign in to your workshop dashboard</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <!-- Google Sign In (Primary) -->
    <div class="mb-6">
        <a href="{{ route('auth.google') }}"
            class="w-full inline-flex items-center justify-center px-4 py-3 bg-white border-2 border-gray-200 rounded-xl font-semibold text-sm text-gray-700 hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all duration-200 shadow-sm">
            <svg class="w-5 h-5 mr-3" viewBox="0 0 24 24">
                <path fill="#4285F4"
                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                <path fill="#34A853"
                    d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                <path fill="#FBBC05"
                    d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                <path fill="#EA4335"
                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
            </svg>
            Continue with Google
        </a>
    </div>

    <!-- Divider -->
    <div class="relative my-6">
        <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-gray-200"></div>
        </div>
        <div class="relative flex justify-center text-xs uppercase">
            <span class="px-3 bg-gray-50 text-gray-400 font-medium">or sign in with email</span>
        </div>
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-semibold text-indigo-900 mb-2">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                autocomplete="username"
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-gray-900 placeholder-gray-400 bg-white shadow-sm transition-all @error('email') border-red-400 focus:ring-red-200 @enderror"
                placeholder="you@company.com">
            @error('email')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <label for="password" class="block text-sm font-semibold text-indigo-900">Password</label>
                @if (Route::has('password.request'))
                    <a class="text-sm font-medium text-indigo-600 hover:text-indigo-500 transition-colors"
                        href="{{ route('password.request') }}">
                        Forgot password?
                    </a>
                @endif
            </div>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-gray-900 placeholder-gray-400 bg-white shadow-sm transition-all @error('password') border-red-400 focus:ring-red-200 @enderror"
                placeholder="••••••••">
            @error('password')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Remember Me -->
        <div class="flex items-center">
            <input id="remember_me" type="checkbox" name="remember"
                class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 transition-colors">
            <label for="remember_me" class="ml-2 text-sm text-gray-600">Remember me for 30 days</label>
        </div>

        <!-- Submit Button -->
        <button type="submit"
            class="w-full bg-indigo-600 text-white py-3 px-4 rounded-xl font-bold text-sm uppercase tracking-wide hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-300 transition-all shadow-lg shadow-indigo-200 transform hover:-translate-y-0.5">
            Sign in
        </button>
    </form>

    <!-- Register Link -->
    <div class="mt-8 text-center">
        <span class="text-sm text-gray-500">Don't have an account?</span>
        <a href="{{ route('register') }}"
            class="text-sm font-bold text-indigo-600 hover:text-indigo-500 ml-1 transition-colors">
            Start your free trial
        </a>
    </div>
</x-guest-layout>