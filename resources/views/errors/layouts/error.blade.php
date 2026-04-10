{{-- Shared layout for custom error pages. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Error' }} · {{ config('app.name', 'IHRAUTO CRM') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="h-full font-sans antialiased">
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 via-indigo-900 to-purple-900 px-4">
        <main role="main" class="max-w-md w-full mx-auto">
            <div class="bg-white/10 backdrop-blur-xl rounded-2xl shadow-2xl p-8 text-center border border-white/20">
                {{-- Error code badge --}}
                <div class="inline-flex items-center justify-center mx-auto mb-6 {{ $iconBgClass ?? 'bg-indigo-500/20' }} rounded-full w-20 h-20">
                    {{ $icon }}
                </div>

                <p class="text-xs font-semibold uppercase tracking-[0.2em] {{ $codeColorClass ?? 'text-indigo-300' }} mb-2">
                    Error {{ $code }}
                </p>

                <h1 class="text-2xl sm:text-3xl font-bold text-white mb-3">{{ $title }}</h1>

                <p class="text-gray-300 mb-6 leading-relaxed">
                    {{ $message }}
                </p>

                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    @auth
                        <a href="{{ route('dashboard') }}"
                           class="inline-flex items-center justify-center px-5 py-2.5 bg-white text-indigo-900 rounded-lg font-semibold hover:bg-indigo-50 transition-colors">
                            Back to Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                           class="inline-flex items-center justify-center px-5 py-2.5 bg-white text-indigo-900 rounded-lg font-semibold hover:bg-indigo-50 transition-colors">
                            Sign In
                        </a>
                    @endauth

                    <a href="mailto:{{ config('crm.support_email', 'info@ihrauto.ch') }}"
                       class="inline-flex items-center justify-center px-5 py-2.5 bg-transparent text-white border border-white/30 rounded-lg font-semibold hover:bg-white/10 transition-colors">
                        Contact Support
                    </a>
                </div>
            </div>

            <p class="text-center text-xs text-gray-500 mt-6">
                &copy; {{ date('Y') }} {{ config('app.name', 'IHRAUTO CRM') }} · v{{ app_version() }}
            </p>
        </main>
    </div>
</body>
</html>
