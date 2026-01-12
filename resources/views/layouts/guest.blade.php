<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'IHRAUTO CRM') }} - Login</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="h-full font-sans antialiased">
    <div class="min-h-screen flex">
        <!-- Left Side - Branding -->
        <div
            class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-indigo-950 via-indigo-900 to-indigo-800 relative overflow-hidden">
            <!-- Background Pattern -->
            <div class="absolute inset-0 opacity-10">
                <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                    <defs>
                        <pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse">
                            <path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" stroke-width="0.5" />
                        </pattern>
                    </defs>
                    <rect width="100" height="100" fill="url(#grid)" />
                </svg>
            </div>

            <!-- Content -->
            <div class="relative z-10 flex flex-col justify-between p-12 w-full">
                <!-- Logo -->
                <div class="flex items-center space-x-3">
                    <div
                        class="w-12 h-12 bg-white rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/30">
                        <svg class="w-7 h-7 text-indigo-900" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-2xl font-bold tracking-tight text-white">IHRAUTO</span>
                        <span class="text-sm text-indigo-300 font-medium ml-2">CRM</span>
                    </div>
                </div>

                <!-- Main Message -->
                <div class="max-w-md">
                    <h1 class="text-4xl font-bold text-white leading-tight mb-6">
                        Manage your workshop with confidence
                    </h1>
                    <p class="text-lg text-indigo-200 leading-relaxed">
                        Streamline check-ins, work orders, tire storage, invoicing, and customer management – all in one
                        powerful platform.
                    </p>

                    <!-- Features -->
                    <div class="mt-10 space-y-4">
                        <div class="flex items-center text-indigo-100">
                            <div class="w-8 h-8 rounded-lg bg-indigo-700/50 flex items-center justify-center mr-4">
                                <svg class="w-4 h-4 text-indigo-300" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium">Vehicle check-in & work orders</span>
                        </div>
                        <div class="flex items-center text-indigo-100">
                            <div class="w-8 h-8 rounded-lg bg-indigo-700/50 flex items-center justify-center mr-4">
                                <svg class="w-4 h-4 text-indigo-300" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium">Tire hotel & seasonal storage</span>
                        </div>
                        <div class="flex items-center text-indigo-100">
                            <div class="w-8 h-8 rounded-lg bg-indigo-700/50 flex items-center justify-center mr-4">
                                <svg class="w-4 h-4 text-indigo-300" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium">Invoicing & financial reports</span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-indigo-400 text-sm">
                    &copy; {{ date('Y') }} IHRAUTO. Professional workshop management.
                </div>
            </div>
        </div>

        <!-- Right Side - Form -->
        <div class="flex-1 flex flex-col justify-center items-center px-6 py-12 bg-gray-50 lg:px-8">
            <!-- Mobile Logo -->
            <div class="lg:hidden mb-10 flex items-center space-x-3">
                <div class="w-10 h-10 bg-indigo-900 rounded-xl flex items-center justify-center shadow-lg">
                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                            d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div>
                    <span class="text-xl font-bold tracking-tight text-indigo-950">IHRAUTO</span>
                    <span class="text-xs text-indigo-600 font-medium ml-1">CRM</span>
                </div>
            </div>

            <div class="w-full max-w-md">
                @isset($slot)
                    {{ $slot }}
                @else
                    @yield('content')
                @endisset
            </div>
        </div>
    </div>
</body>

</html>