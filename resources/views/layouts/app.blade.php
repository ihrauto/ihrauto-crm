<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'IHR AUTO CRM') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-purple-light-custom { background-color: #E3E1FC; }
        .bg-navy-custom { background-color: #1E1B4B; }
    </style>
</head>
<body class="h-full font-sans antialiased text-slate-900 bg-purple-light-custom" x-data="{ sidebarOpen: false }">
    <div class="min-h-screen flex">
        
        <!-- Mobile Sidebar Backdrop -->
        <div x-show="sidebarOpen" 
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="sidebarOpen = false"
             class="fixed inset-0 bg-gray-900/80 z-40 lg:hidden"
             style="display: none;"></div>

        <!-- Sidebar -->
        <nav :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" 
             class="w-72 bg-navy-custom flex-shrink-0 flex flex-col fixed inset-y-0 z-50 shadow-xl transition-transform duration-300 ease-in-out lg:translate-x-0">
            
            <!-- Close button (mobile only) -->
            <button @click="sidebarOpen = false" class="absolute top-5 right-4 p-2 text-indigo-300 hover:text-white lg:hidden">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            
            <!-- Logo Section -->
            <div class="h-20 flex items-center px-6 border-b border-indigo-900/50">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 bg-white rounded-lg flex items-center justify-center shadow-lg shadow-indigo-500/20">
                        <svg class="w-5 h-5 text-indigo-900" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div>
                        <span class="text-lg font-bold tracking-tight text-white">IHRAUTO</span>
                        <span class="text-xs text-indigo-200 font-medium ml-1">CRM</span>
                    </div>
                </div>
            </div>

            <!-- Navigation Content -->
            <div class="flex-1 overflow-y-auto py-8 px-4 space-y-6">
                <!-- MAIN Section -->
                <div>
                    <h3 class="px-3 text-[10px] font-extrabold text-indigo-300/50 uppercase tracking-[0.2em] mb-4">Overview</h3>
                    <div class="space-y-1">
                        @if(!auth()->check() || auth()->user()->can('access dashboard'))
                        <a href="{{ route('dashboard') }}" id="nav-dashboard"
                           @click="sidebarOpen = false"
                           class="group flex items-center px-3 py-2.5 text-xs font-bold uppercase tracking-wider rounded-lg transition-all duration-200 border border-transparent {{ request()->routeIs('dashboard') ? 'bg-indigo-600 shadow-lg shadow-indigo-900/50 text-white border-indigo-500/30' : 'text-indigo-100/60 hover:bg-white/5 hover:text-white' }}">
                            <svg class="flex-shrink-0 w-5 h-5 mr-3 {{ request()->routeIs('dashboard') ? 'text-white' : 'text-indigo-400/50 group-hover:text-indigo-300' }} transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                            </svg>
                            Dashboard
                        </a>
                        @endif

                        @if(!auth()->check() || auth()->user()->can('access check-in'))
                        <a href="{{ route('checkin') }}" id="nav-checkin"
                           @click="sidebarOpen = false"
                           class="group flex items-center px-3 py-2.5 text-xs font-bold uppercase tracking-wider rounded-lg transition-all duration-200 border border-transparent {{ request()->routeIs('checkin*') ? 'bg-indigo-600 shadow-lg shadow-indigo-900/50 text-white border-indigo-500/30' : 'text-indigo-100/60 hover:bg-white/5 hover:text-white' }}">
                            <svg class="flex-shrink-0 w-5 h-5 mr-3 {{ request()->routeIs('checkin*') ? 'text-white' : 'text-indigo-400/50 group-hover:text-indigo-300' }} transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                            Check-In
                        </a>
                        @endif

                        @if(!auth()->check() || auth()->user()->can('access tire-hotel'))
                            @php
                                $hasTireHotel = tenant() && tenant()->hasTireHotel();
                            @endphp
                            @if($hasTireHotel)
                                <a href="{{ route('tires-hotel') }}" id="nav-tire-hotel"
                                   @click="sidebarOpen = false"
                                   class="group flex items-center px-3 py-2.5 text-xs font-bold uppercase tracking-wider rounded-lg transition-all duration-200 border border-transparent {{ request()->routeIs('tires-hotel*') ? 'bg-indigo-600 shadow-lg shadow-indigo-900/50 text-white border-indigo-500/30' : 'text-indigo-100/60 hover:bg-white/5 hover:text-white' }}">
                                    <svg class="flex-shrink-0 w-5 h-5 mr-3 {{ request()->routeIs('tires-hotel*') ? 'text-white' : 'text-indigo-400/50 group-hover:text-indigo-300' }} transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                    Tire Hotel
                                </a>
                            @else
                                {{-- Show locked/upgrade indicator for BASIC plan --}}
                                <div class="group flex items-center px-3 py-2.5 text-xs font-bold uppercase tracking-wider rounded-lg text-indigo-100/30 cursor-not-allowed" title="Upgrade to Standard to unlock Tire Hotel">
                                    <svg class="flex-shrink-0 w-5 h-5 mr-3 text-indigo-400/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                    </svg>
                                    Tire Hotel
                                    <span class="ml-auto px-1.5 py-0.5 text-[9px] bg-amber-500/20 text-amber-300 rounded font-semibold">PRO</span>
                                </div>
                            @endif
                        @endif

                        @if(!auth()->check() || auth()->user()->can('access work-orders'))
                        <a href="{{ route('work-orders.index') }}" 
                           @click="sidebarOpen = false"
                           class="group flex items-center px-3 py-2.5 text-xs font-bold uppercase tracking-wider rounded-lg transition-all duration-200 border border-transparent {{ request()->routeIs('work-orders*') ? 'bg-indigo-600 shadow-lg shadow-indigo-900/50 text-white border-indigo-500/30' : 'text-indigo-100/60 hover:bg-white/5 hover:text-white' }}">
                            <svg class="flex-shrink-0 w-5 h-5 mr-3 {{ request()->routeIs('work-orders*') ? 'text-white' : 'text-indigo-400/50 group-hover:text-indigo-300' }} transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Work Orders
                        </a>
                        @endif
                        
                        @if(!auth()->check() || auth()->user()->can('access appointments'))
                        <a href="{{ route('appointments.index') }}" 
                           @click="sidebarOpen = false"
                           class="group flex items-center px-3 py-2.5 text-xs font-bold uppercase tracking-wider rounded-lg transition-all duration-200 border border-transparent {{ request()->routeIs('appointments*') ? 'bg-indigo-600 shadow-lg shadow-indigo-900/50 text-white border-indigo-500/30' : 'text-indigo-100/60 hover:bg-white/5 hover:text-white' }}">
                            <svg class="flex-shrink-0 w-5 h-5 mr-3 {{ request()->routeIs('appointments*') ? 'text-white' : 'text-indigo-400/50 group-hover:text-indigo-300' }} transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            APPOINTMENTS
                        </a>
                        @endif

                        @if(!auth()->check() || auth()->user()->can('access finance'))
                        <a href="{{ route('finance.index') }}" 
                           @click="sidebarOpen = false"
                           class="group flex items-center px-3 py-2.5 text-xs font-bold uppercase tracking-wider rounded-lg transition-all duration-200 border border-transparent {{ request()->routeIs('finance*', 'payments*') ? 'bg-indigo-600 shadow-lg shadow-indigo-900/50 text-white border-indigo-500/30' : 'text-indigo-100/60 hover:bg-white/5 hover:text-white' }}">
                            <svg class="flex-shrink-0 w-5 h-5 mr-3 {{ request()->routeIs('finance*', 'payments*') ? 'text-white' : 'text-indigo-400/50 group-hover:text-indigo-300' }} transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            FINANCE
                        </a>
                        @endif
                    </div>
                </div>

                <!-- MANAGEMENT Section -->
                <div>
                   <h3 class="px-3 text-[10px] font-extrabold text-indigo-300/50 uppercase tracking-[0.2em] mb-4">Management</h3>
                    <div class="space-y-1">
                        @if(!auth()->check() || auth()->user()->can('access inventory'))
                        <a href="{{ route('products-services.index') }}" 
                           @click="sidebarOpen = false"
                           class="group flex items-center px-3 py-2.5 text-xs font-bold uppercase tracking-wider rounded-lg transition-all duration-200 border border-transparent {{ request()->routeIs('products-services*') ? 'bg-indigo-600 shadow-lg shadow-indigo-900/50 text-white border-indigo-500/30' : 'text-indigo-100/60 hover:bg-white/5 hover:text-white' }}">
                            <svg class="flex-shrink-0 w-5 h-5 mr-3 {{ request()->routeIs('products-services*') ? 'text-white' : 'text-indigo-400/50 group-hover:text-indigo-300' }} transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            Inventory & Services
                        </a>
                        @endif

                        @if(!auth()->check() || auth()->user()->can('access customers'))
                        <a href="{{ route('customers.index') }}" 
                           @click="sidebarOpen = false"
                           class="group flex items-center px-3 py-2.5 text-xs font-bold uppercase tracking-wider rounded-lg transition-all duration-200 border border-transparent {{ request()->routeIs('customers*') ? 'bg-indigo-600 shadow-lg shadow-indigo-900/50 text-white border-indigo-500/30' : 'text-indigo-100/60 hover:bg-white/5 hover:text-white' }}">
                            <svg class="flex-shrink-0 w-5 h-5 mr-3 {{ request()->routeIs('customers*') ? 'text-white' : 'text-indigo-400/50 group-hover:text-indigo-300' }} transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            Customers
                        </a>
                        @endif

                        @if(!auth()->check() || auth()->user()->can('access management'))
                        <a href="{{ route('management') }}" 
                           @click="sidebarOpen = false"
                           class="group flex items-center px-3 py-2.5 text-xs font-bold uppercase tracking-wider rounded-lg transition-all duration-200 border border-transparent {{ request()->routeIs('management*') ? 'bg-indigo-600 shadow-lg shadow-indigo-900/50 text-white border-indigo-500/30' : 'text-indigo-100/60 hover:bg-white/5 hover:text-white' }}">
                            <svg class="flex-shrink-0 w-5 h-5 mr-3 {{ request()->routeIs('management*') ? 'text-white' : 'text-indigo-400/50 group-hover:text-indigo-300' }} transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            Management
                        </a>
                        @endif
                    </div>
                </div>
            </div>


            <!-- User Profile / Footer -->
            <div class="border-t border-indigo-900/50 p-4 bg-black/20">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-indigo-500 flex items-center justify-center text-xs font-bold text-white border border-indigo-400">
                        IA
                    </div>
                    <div>
                        <p class="text-sm font-bold text-white">System Admin</p>
                        <p class="text-xs text-indigo-300">v{{ app_version() }}</p>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="lg:ml-72 flex-1 flex flex-col min-h-screen">
            <!-- Top Header -->
            <header class="bg-white/80 border-b border-indigo-100 h-16 lg:h-20 flex items-center justify-between px-4 lg:px-10 fixed top-0 right-0 left-0 lg:left-72 z-40 bg-opacity-95 backdrop-blur-sm">
                
                <!-- Mobile Menu Button + Title -->
                <div class="flex items-center">
                    <!-- Hamburger Menu (mobile/tablet only) -->
                    <button @click="sidebarOpen = true" class="p-2 mr-3 -ml-1 text-indigo-600 hover:text-indigo-900 hover:bg-indigo-50 rounded-lg lg:hidden">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    
                   <h2 class="text-lg lg:text-2xl font-bold text-indigo-950 tracking-tight truncate">@yield('title')</h2>
                </div>
                
                <div class="flex items-center space-x-3 lg:space-x-5">
                     @if(app()->environment('local'))
                         <div class="hidden sm:flex items-center px-3 py-1.5 bg-indigo-50 rounded-full border border-indigo-100">
                             <div class="w-2 h-2 rounded-full bg-indigo-400 mr-2"></div>
                             <span class="text-xs font-bold text-indigo-700 uppercase tracking-wide">
                                 @if(tenant()) {{ tenant()->name }} @else Local @endif
                             </span>
                         </div>
                     @endif
                     
                    <div class="hidden sm:block h-8 w-px bg-indigo-100 mx-2"></div>
                     
                    <!-- Notifications -->
                    <button class="relative p-2 text-indigo-300 hover:text-indigo-600 transition-colors rounded-full hover:bg-indigo-50">
                        <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border border-white"></span>
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                    </button>
                    
                    <!-- Profile Dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" @click.away="open = false" class="flex items-center space-x-2 text-sm font-semibold text-indigo-900 hover:text-indigo-700 transition-colors p-2 rounded-lg hover:bg-indigo-50">
                            <span class="hidden sm:inline">Profile</span>
                            <svg class="w-4 h-4 text-indigo-400 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="transform opacity-0 scale-95 translate-y-2"
                             x-transition:enter-end="transform opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="transform opacity-100 scale-100 translate-y-0"
                             x-transition:leave-end="transform opacity-0 scale-95 translate-y-2"
                             class="absolute right-0 mt-3 w-64 bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 py-2 z-50 origin-top-right divide-y divide-gray-50" style="display: none;">
                            
                            <div class="px-6 py-4">
                                <p class="text-[10px] uppercase tracking-wider font-bold text-gray-400 mb-1">Signed in as</p>
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-700 font-bold text-xs ring-4 ring-white">
                                        {{ substr(tenant() ? tenant()->name : 'Admin', 0, 1) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-bold text-gray-900 truncate">{{ tenant() ? tenant()->name : 'System Admin' }}</p>
                                        <p class="text-xs text-gray-400 truncate">{{ tenant() ? tenant()->email : 'admin@ihrauto.com' }}</p>
                                    </div>
                                </div>
                            </div>

                            @if(app()->environment('local'))
                                <div class="p-2">
                                    <form method="POST" action="{{ route('dev.tenant-clear') }}">
                                        @csrf
                                        <button type="submit" class="w-full group flex items-center px-4 py-2.5 text-sm font-medium text-gray-700 hover:text-indigo-600 hover:bg-indigo-50 rounded-xl transition-all duration-200">
                                            <div class="w-8 h-8 rounded-lg bg-gray-50 text-gray-500 group-hover:bg-indigo-100 group-hover:text-indigo-600 flex items-center justify-center mr-3 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                                            </div>
                                            Switch Plan
                                        </button>
                                    </form>
                                </div>
                            @endif

                            <div class="p-2">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full group flex items-center px-4 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50 rounded-xl transition-all duration-200">
                                        <div class="w-8 h-8 rounded-lg bg-red-50 text-red-500 group-hover:bg-red-100 flex items-center justify-center mr-3 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                        </div>
                                        Sign Out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="p-4 lg:p-10 mt-16 lg:mt-20">
                 @if(isset($breadcrumbs))
                    <nav class="flex mb-6 lg:mb-8 text-sm text-indigo-400" aria-label="Breadcrumb">
                        <ol class="flex items-center space-x-2 lg:space-x-3 overflow-x-auto">
                             @foreach($breadcrumbs as $breadcrumb)
                                @if($loop->last)
                                    <li><span class="text-indigo-900 font-bold border-b-2 border-indigo-200 pb-0.5 whitespace-nowrap">{{ $breadcrumb['name'] }}</span></li>
                                @else
                                    <li>
                                        <a href="{{ $breadcrumb['url'] }}" class="hover:text-indigo-700 transition-colors font-medium hover:underline decoration-indigo-200 decoration-1 whitespace-nowrap">{{ $breadcrumb['name'] }}</a>
                                    </li>
                                    <li>
                                        <svg class="w-4 h-4 text-indigo-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </li>
                                @endif
                            @endforeach
                        </ol>
                    </nav>
                @endif
                
                @yield('content')
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @if(session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: "{{ session('success') }}",
                    confirmButtonColor: '#4F46E5',
                    timer: 3000,
                    timerProgressBar: true
                });
            @endif

            @if(session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: "{{ session('error') }}",
                    confirmButtonColor: '#4F46E5'
                });
            @endif
        });
    </script>
</body>
</html>