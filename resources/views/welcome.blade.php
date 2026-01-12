<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome | {{ config('app.name', 'IHR AUTO CRM') }}</title>

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

        .bg-navy-custom {
            background-color: #1E1B4B;
        }

        .text-navy-custom {
            color: #1E1B4B;
        }

        .bg-purple-light-custom {
            background-color: #E3E1FC;
        }
    </style>
</head>

<body class="h-full antialiased bg-purple-light-custom selection:bg-indigo-500 selection:text-white">

    <!-- Navbar -->
    <nav class="fixed w-full z-50 top-0 start-0 border-b border-indigo-200/50 bg-white/80 backdrop-blur-md">
        <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between px-6 py-4">
            <a href="#" class="flex items-center space-x-3 rtl:space-x-reverse">
                <div
                    class="w-10 h-10 bg-indigo-900 rounded-lg flex items-center justify-center shadow-lg shadow-indigo-500/20">
                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                            d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <span class="self-center text-2xl font-bold whitespace-nowrap text-navy-custom tracking-tight">IHRAUTO
                    <span class="text-indigo-600">CRM</span></span>
            </a>
            <div class="flex md:order-2 space-x-3 md:space-x-0 rtl:space-x-reverse">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}"
                            class="text-white bg-indigo-600 hover:bg-indigo-700 focus:ring-4 focus:outline-none focus:ring-indigo-300 font-bold rounded-lg text-sm px-6 py-2.5 text-center shadow-lg shadow-indigo-500/30 transition-all transform hover:scale-105">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}"
                            class="text-white bg-indigo-900 hover:bg-indigo-800 focus:ring-4 focus:outline-none focus:ring-indigo-300 font-bold rounded-lg text-sm px-6 py-2.5 text-center shadow-md transition-all">Log
                            in</a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}"
                                class="ml-4 text-indigo-900 bg-indigo-50 hover:bg-indigo-100 focus:ring-4 focus:outline-none focus:ring-indigo-300 font-bold rounded-lg text-sm px-6 py-2.5 text-center border border-indigo-200 transition-all">Register</a>
                        @endif
                    @endauth
                @endif
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative min-h-screen flex items-center justify-center pt-20 overflow-hidden">
        <!-- Background Decorations -->
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden z-0">
            <div class="absolute -top-[30%] -left-[10%] w-[70%] h-[70%] rounded-full bg-indigo-300/20 blur-3xl filter">
            </div>
            <div class="absolute top-[20%] -right-[10%] w-[60%] h-[60%] rounded-full bg-purple-300/20 blur-3xl filter">
            </div>
            <div
                class="absolute -bottom-[20%] left-[20%] w-[50%] h-[50%] rounded-full bg-indigo-400/20 blur-3xl filter">
            </div>
        </div>

        <div class="py-8 px-4 mx-auto max-w-screen-xl text-center lg:py-16 z-10 relative">
            <div
                class="inline-flex items-center justify-center px-4 py-1.5 mb-7 text-sm font-semibold text-indigo-800 bg-indigo-100 rounded-full border border-indigo-200 shadow-sm">
                <span class="w-2 h-2 bg-indigo-600 rounded-full mr-2 animate-pulse"></span>
                v2.0 System Update Live
            </div>

            <h1
                class="mb-6 text-5xl font-extrabold tracking-tight leading-none text-navy-custom md:text-6xl lg:text-7xl">
                Advanced Workshop <br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-purple-600">Management
                    System</span>
            </h1>

            <p class="mb-10 text-lg font-normal text-indigo-900/70 lg:text-xl sm:px-16 lg:px-48 max-w-4xl mx-auto">
                Streamline your garage operations, manage tire hotels, track customers, and boost efficiency with the
                most powerful CRM designed for automotive professionals.
            </p>

            <div class="flex flex-col space-y-4 sm:flex-row sm:justify-center sm:space-y-0 sm:space-x-4">
                @auth
                    <a href="{{ url('/dashboard') }}"
                        class="inline-flex justify-center items-center py-4 px-8 text-base font-bold text-center text-white rounded-xl bg-indigo-600 hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-300 shadow-xl shadow-indigo-500/30 transition-all transform hover:-translate-y-1">
                        Access Dashboard
                        <svg class="w-3.5 h-3.5 ms-2 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 14 10">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M1 5h12m0 0L9 1m4 4L9 9" />
                        </svg>
                    </a>
                @else
                    <a href="{{ route('login') }}"
                        class="inline-flex justify-center items-center py-4 px-8 text-base font-bold text-center text-white rounded-xl bg-indigo-900 hover:bg-navy-custom focus:ring-4 focus:ring-indigo-300 shadow-xl shadow-indigo-900/20 transition-all transform hover:-translate-y-1">
                        Employee Login
                        <svg class="w-3.5 h-3.5 ms-2 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 14 10">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M1 5h12m0 0L9 1m4 4L9 9" />
                        </svg>
                    </a>
                @endauth
            </div>

            <!-- Feature Grip -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-20 text-left">
                <div
                    class="bg-white/60 backdrop-blur-sm p-8 rounded-2xl border border-indigo-50 shadow-lg hover:shadow-xl hover:bg-white transition-all duration-300">
                    <div
                        class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mb-4 text-indigo-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-navy-custom mb-2">Tire Hotel</h3>
                    <p class="text-indigo-900/60">Complete tire storage management with season tracking, location
                        mapping, and automated retrieval systems.</p>
                </div>
                <div
                    class="bg-white/60 backdrop-blur-sm p-8 rounded-2xl border border-indigo-50 shadow-lg hover:shadow-xl hover:bg-white transition-all duration-300">
                    <div
                        class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4 text-purple-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-navy-custom mb-2">CRM Tools</h3>
                    <p class="text-indigo-900/60">Advanced customer profiles, vehicle history tracking, and service
                        scheduling in one unified interface.</p>
                </div>
                <div
                    class="bg-white/60 backdrop-blur-sm p-8 rounded-2xl border border-indigo-50 shadow-lg hover:shadow-xl hover:bg-white transition-all duration-300">
                    <div
                        class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mb-4 text-indigo-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-navy-custom mb-2">Analytics</h3>
                    <p class="text-indigo-900/60">Real-time business insights, inventory turnover rates, and performance
                        metrics at your fingertips.</p>
                </div>
            </div>

        </div>
    </section>

    <footer class="bg-white border-t border-indigo-100 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
            <p class="text-indigo-900/50 text-sm">© {{ date('Y') }} IHRAUTO v{{ app_version() }}. All rights reserved.
            </p>
            <div class="flex space-x-6 text-indigo-900/50 text-sm font-medium">
                <a href="#" class="hover:text-indigo-600 transition-colors">Privacy</a>
                <a href="#" class="hover:text-indigo-600 transition-colors">Terms</a>
                <a href="#" class="hover:text-indigo-600 transition-colors">Support</a>
            </div>
        </div>
    </footer>

</body>

</html>