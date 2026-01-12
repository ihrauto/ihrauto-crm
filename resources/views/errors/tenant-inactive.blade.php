<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'IHRAUTO CRM') }} - Account Suspended</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased">
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 via-indigo-900 to-purple-900">
        <div class="max-w-md w-full mx-auto p-8">
            <div class="bg-white/10 backdrop-blur-xl rounded-2xl shadow-2xl p-8 text-center border border-white/20">
                <!-- Icon -->
                <div class="w-20 h-20 mx-auto mb-6 bg-red-500/20 rounded-full flex items-center justify-center">
                    <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>

                <!-- Title -->
                <h1 class="text-2xl font-bold text-white mb-3">Account Suspended</h1>

                <!-- Message -->
                <p class="text-gray-300 mb-6">
                    Your account has been temporarily suspended. This may be due to a billing issue or terms of service violation.
                </p>

                <!-- Contact Info -->
                <div class="bg-white/5 rounded-lg p-4 mb-6">
                    <p class="text-gray-400 text-sm">
                        Please contact support to resolve this issue:
                    </p>
                    <a href="mailto:support@ihrauto.com" class="text-indigo-400 hover:text-indigo-300 font-medium">
                        support@ihrauto.com
                    </a>
                </div>

                <!-- Actions -->
                <div class="flex flex-col gap-3">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors">
                            Sign Out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>