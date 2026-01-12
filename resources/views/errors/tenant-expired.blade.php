<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'IHRAUTO CRM') }} - Trial Expired</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="h-full font-sans antialiased">
    <div
        class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 via-indigo-900 to-purple-900">
        <div class="max-w-lg w-full mx-auto p-8">
            <div class="bg-white/10 backdrop-blur-xl rounded-2xl shadow-2xl p-8 text-center border border-white/20">
                <!-- Icon -->
                <div class="w-20 h-20 mx-auto mb-6 bg-amber-500/20 rounded-full flex items-center justify-center">
                    <svg class="w-10 h-10 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>

                <!-- Title -->
                <h1 class="text-2xl font-bold text-white mb-3">Trial Period Expired</h1>

                <!-- Message -->
                <p class="text-gray-300 mb-6">
                    Your free trial has ended. To continue using IHRAUTO CRM, please choose a subscription plan that
                    fits your needs.
                </p>

                <!-- Plan Options -->
                <div class="space-y-3 mb-6">
                    <a href="{{ route('management.pricing') }}"
                        class="block w-full px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors">
                        View Plans & Subscribe
                    </a>
                </div>

                <!-- Footer -->
                <div class="text-gray-400 text-sm">
                    <p>Need help? Contact us at</p>
                    <a href="mailto:support@ihrauto.com" class="text-indigo-400 hover:text-indigo-300">
                        support@ihrauto.com
                    </a>
                </div>

                <!-- Logout -->
                <div class="mt-6 pt-6 border-t border-white/10">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-gray-400 hover:text-white text-sm transition-colors">
                            Sign out and return later
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>

</html>