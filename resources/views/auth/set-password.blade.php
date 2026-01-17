@extends('layouts.guest')

@section('title', 'Set Your Password')

@section('content')
    <div
        class="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-100 via-purple-50 to-indigo-100 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Logo/Brand -->
            <div class="text-center">
                <div class="w-16 h-16 bg-indigo-600 rounded-2xl flex items-center justify-center mx-auto shadow-lg">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z">
                        </path>
                    </svg>
                </div>
                <h2 class="mt-6 text-3xl font-extrabold text-indigo-900">Welcome, {{ $name }}!</h2>
                <p class="mt-2 text-sm text-indigo-600">Set up your password to activate your account</p>
            </div>

            <!-- Form Card -->
            <div class="bg-white rounded-2xl shadow-xl p-8 border border-indigo-100">
                @if ($errors->any())
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                        @foreach ($errors->all() as $error)
                            <p class="text-sm">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('invite.setup.store', ['token' => $token]) }}" class="space-y-6">
                    @csrf

                    <!-- Email (readonly) -->
                    <div>
                        <label for="email" class="block text-sm font-semibold text-indigo-900 mb-2">Email Address</label>
                        <input type="email" id="email" value="{{ $email }}" disabled
                            class="w-full p-3 border border-indigo-200 rounded-lg bg-indigo-50 text-indigo-700 cursor-not-allowed">
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-semibold text-indigo-900 mb-2">Create
                            Password</label>
                        <input type="password" name="password" id="password" required minlength="8"
                            class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900"
                            placeholder="Minimum 8 characters">
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="password_confirmation" class="block text-sm font-semibold text-indigo-900 mb-2">Confirm
                            Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" required
                            class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900"
                            placeholder="Repeat your password">
                    </div>

                    <!-- Submit -->
                    <button type="submit"
                        class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-lg text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-300 transition-all transform hover:-translate-y-0.5">
                        Activate My Account
                    </button>
                </form>
            </div>

            <!-- Footer -->
            <p class="text-center text-sm text-indigo-500">
                Already have an account? <a href="{{ route('login') }}"
                    class="font-semibold text-indigo-700 hover:text-indigo-900">Sign in</a>
            </p>
        </div>
    </div>
@endsection