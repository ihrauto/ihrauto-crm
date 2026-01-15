<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IHRAUTO CRM - Workshop Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="h-full bg-gradient-to-br from-indigo-900 via-purple-900 to-indigo-800">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="py-6 px-8">
            <div class="max-w-7xl mx-auto flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-white">IHRAUTO <span class="text-indigo-300">CRM</span></span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="{{ route('login') }}" class="text-white hover:text-indigo-200 font-medium">Login</a>
                    <a href="{{ route('register') }}"
                        class="bg-white text-indigo-600 px-5 py-2 rounded-lg font-semibold hover:bg-indigo-50 transition">
                        Start Free Trial
                    </a>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <main class="flex-1 flex items-center justify-center px-8 py-12">
            <div class="max-w-4xl text-center">
                <h1 class="text-5xl md:text-6xl font-bold text-white mb-6 leading-tight">
                    The Complete Workshop<br>Management System
                </h1>
                <p class="text-xl text-indigo-200 mb-10 max-w-2xl mx-auto">
                    Manage customers, vehicles, work orders, tire storage, appointments and invoicing — all in one
                    place.
                </p>

                <div class="flex flex-col sm:flex-row gap-4 justify-center mb-16">
                    <a href="{{ route('register') }}"
                        class="bg-white text-indigo-600 px-8 py-4 rounded-xl font-bold text-lg hover:bg-indigo-50 transition shadow-xl">
                        Start 14-Day Free Trial
                    </a>
                    <a href="{{ route('login') }}"
                        class="bg-indigo-600/30 border border-indigo-400 text-white px-8 py-4 rounded-xl font-bold text-lg hover:bg-indigo-600/50 transition">
                        Sign In
                    </a>
                </div>

                <!-- Pricing Cards -->
                <div class="grid md:grid-cols-3 gap-6 mt-8">
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 border border-white/20">
                        <h3 class="text-xl font-bold text-white mb-2">Basic</h3>
                        <div class="text-4xl font-bold text-white mb-4">€49<span
                                class="text-lg text-indigo-300">/mo</span></div>
                        <p class="text-indigo-200 text-sm mb-6">For solo mechanics and small workshops</p>
                        <ul class="text-left text-indigo-100 space-y-2 text-sm">
                            <li>✓ 1 User</li>
                            <li>✓ 100 Customers</li>
                            <li>✓ Work Orders</li>
                            <li>✓ Appointments</li>
                        </ul>
                    </div>
                    <div
                        class="bg-white/20 backdrop-blur-sm rounded-2xl p-8 border-2 border-white/40 transform scale-105">
                        <div
                            class="bg-indigo-500 text-white text-xs font-bold px-3 py-1 rounded-full inline-block mb-4">
                            POPULAR</div>
                        <h3 class="text-xl font-bold text-white mb-2">Standard</h3>
                        <div class="text-4xl font-bold text-white mb-4">€149<span
                                class="text-lg text-indigo-300">/mo</span></div>
                        <p class="text-indigo-200 text-sm mb-6">For growing garages with multiple employees</p>
                        <ul class="text-left text-indigo-100 space-y-2 text-sm">
                            <li>✓ 5 Users</li>
                            <li>✓ 1000 Customers</li>
                            <li>✓ Tire Hotel</li>
                            <li>✓ Advanced Reports</li>
                        </ul>
                    </div>
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 border border-white/20">
                        <h3 class="text-xl font-bold text-white mb-2">Custom</h3>
                        <div class="text-4xl font-bold text-white mb-4">Contact</div>
                        <p class="text-indigo-200 text-sm mb-6">For large workshops and franchises</p>
                        <ul class="text-left text-indigo-100 space-y-2 text-sm">
                            <li>✓ Unlimited Users</li>
                            <li>✓ API Access</li>
                            <li>✓ Custom Branding</li>
                            <li>✓ Dedicated Support</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="py-6 px-8 text-center text-indigo-300 text-sm">
            © {{ date('Y') }} IHRAUTO CRM. All rights reserved.
        </footer>
    </div>
</body>

</html>