@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="space-y-6 lg:space-y-8">
        <!-- Welcome Section -->
        <div class="md:flex md:items-center md:justify-between">
            <div class="min-w-0 flex-1">
                <h2 class="text-2xl font-bold leading-7 text-indigo-950 sm:truncate sm:text-3xl sm:tracking-tight">Welcome
                    back, {{ auth()->user()->name ?? 'User' }}</h2>
                <p class="mt-1 text-sm text-indigo-700/70">Here's an overview of your operations today.</p>
            </div>
            <div class="mt-4 flex md:ml-4 md:mt-0">
                <span
                    class="inline-flex items-center rounded-lg bg-white px-3 py-2 text-sm font-semibold text-indigo-900 shadow-sm border border-indigo-200 hover:bg-indigo-50">
                    <svg class="-ml-0.5 mr-1.5 h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0h18" />
                    </svg>
                    {{ date('M j, Y') }}
                </span>
            </div>
        </div>



        <div class="mb-8">
            <!-- Key Stats (4x1 Grid) -->
            <div class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
                <!-- Active Jobs (In Progress) -->
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-indigo-100 flex flex-col">
                    <div class="p-4 sm:p-5 flex-1">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-shrink-0 p-2 sm:p-3 rounded-lg bg-green-50 border border-green-100">
                                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z" />
                                </svg>
                            </div>
                            <span class="text-xs sm:text-sm font-medium text-indigo-500">Active Jobs</span>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl sm:text-3xl font-bold text-indigo-950">{{ $stats['active_jobs'] }}</span>
                            <span class="text-xs sm:text-sm font-medium text-green-600">in progress</span>
                        </div>
                    </div>
                    <div class="bg-green-50/50 px-4 sm:px-5 py-2 sm:py-3 border-t border-green-100">
                        <a href="{{ route('work-orders.index') }}" class="text-xs sm:text-sm font-medium text-green-700 hover:text-green-800">View all jobs</a>
                    </div>
                </div>

                <!-- Pending Jobs -->
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-indigo-100 flex flex-col">
                    <div class="p-4 sm:p-5 flex-1">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-shrink-0 p-2 sm:p-3 rounded-lg bg-amber-50 border border-amber-100">
                                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <span class="text-xs sm:text-sm font-medium text-indigo-500">Pending</span>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl sm:text-3xl font-bold text-indigo-950">{{ $stats['pending_jobs'] }}</span>
                            <span class="text-xs sm:text-sm font-medium text-amber-600">waiting</span>
                        </div>
                    </div>
                    <div class="bg-amber-50/50 px-4 sm:px-5 py-2 sm:py-3 border-t border-amber-100">
                        <a href="{{ route('work-orders.index') }}" class="text-xs sm:text-sm font-medium text-amber-700 hover:text-amber-800">Start a job</a>
                    </div>
                </div>

                <!-- Completed Today -->
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-indigo-100 flex flex-col">
                    <div class="p-4 sm:p-5 flex-1">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-shrink-0 p-2 sm:p-3 rounded-lg bg-indigo-50 border border-indigo-100">
                                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <span class="text-xs sm:text-sm font-medium text-indigo-500">Completed</span>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl sm:text-3xl font-bold text-indigo-950">{{ $stats['completed_today'] }}</span>
                            <span class="text-xs sm:text-sm font-medium text-indigo-400">today</span>
                        </div>
                    </div>
                    <div class="bg-indigo-50/50 px-4 sm:px-5 py-2 sm:py-3 border-t border-indigo-100">
                        <a href="{{ route('finance.index') }}" class="text-xs sm:text-sm font-medium text-indigo-900 hover:text-indigo-700">View invoices</a>
                    </div>
                </div>

                <!-- All Work Orders -->
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-indigo-100 flex flex-col">
                    <div class="p-4 sm:p-5 flex-1">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-shrink-0 p-2 sm:p-3 rounded-lg bg-indigo-50 border border-indigo-100">
                                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                                </svg>
                            </div>
                            <span class="text-xs sm:text-sm font-medium text-indigo-500">All Jobs</span>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl sm:text-3xl font-bold text-indigo-950">{{ \App\Models\WorkOrder::count() }}</span>
                            <span class="text-xs sm:text-sm font-medium text-indigo-500">total</span>
                        </div>
                    </div>
                    <div class="bg-indigo-50/50 px-4 sm:px-5 py-2 sm:py-3 border-t border-indigo-100">
                        <a href="{{ route('work-orders.index') }}" class="text-xs sm:text-sm font-medium text-indigo-600 hover:text-indigo-700">View all</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Operational Pulse -->
        <div class="mb-8">
            <h3 class="text-xs sm:text-sm font-medium text-gray-500 mb-4 px-1">Operational Pulse</h3>
            <div class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
                
                <!-- Appointments Today -->
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-inset ring-gray-200 flex flex-col">
                    <div class="p-4 sm:p-5 flex-1">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-shrink-0 p-2 sm:p-3 rounded-lg bg-blue-50 border border-blue-100">
                                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0h18" />
                                </svg>
                            </div>
                            <span class="text-xs sm:text-sm font-medium text-gray-500">Appointments</span>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $stats['appointments_today'] }}</span>
                            <span class="text-xs sm:text-sm font-medium text-blue-600">today</span>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 sm:px-5 py-2 sm:py-3 border-t border-gray-100">
                        <a href="{{ route('appointments.index') }}" class="text-xs sm:text-sm font-medium text-blue-600 hover:text-blue-500">View all</a>
                    </div>
                </div>

                <!-- Low Stock Items -->
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-inset ring-gray-200 flex flex-col">
                    <div class="p-4 sm:p-5 flex-1">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-shrink-0 p-2 sm:p-3 rounded-lg bg-orange-50 border border-orange-100">
                                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-orange-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                </svg>
                            </div>
                            <span class="text-xs sm:text-sm font-medium text-gray-500">Low Stock</span>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $stats['low_stock_count'] }}</span>
                            <span class="text-xs sm:text-sm font-medium text-orange-600">alerts</span>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 sm:px-5 py-2 sm:py-3 border-t border-gray-100">
                        <a href="{{ route('products-services.index', ['tab' => 'parts']) }}" class="text-xs sm:text-sm font-medium text-orange-600 hover:text-orange-500">View inventory</a>
                    </div>
                </div>

                <!-- Free Bays -->
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-inset ring-gray-200 flex flex-col">
                    <div class="p-4 sm:p-5 flex-1">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-shrink-0 p-2 sm:p-3 rounded-lg bg-teal-50 border border-teal-100">
                                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 002.25-2.25V6a2.25 2.25 0 00-2.25-2.25H6A2.25 2.25 0 003.75 6v2.25A2.25 2.25 0 006 10.5z" />
                                </svg>
                            </div>
                            <span class="text-xs sm:text-sm font-medium text-gray-500">Free Bays</span>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $stats['free_bays'] }}</span>
                            <span class="text-xs sm:text-sm font-medium text-teal-600">of 6</span>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 sm:px-5 py-2 sm:py-3 border-t border-gray-100">
                        <a href="{{ route('work-orders.board') }}" class="text-xs sm:text-sm font-medium text-teal-600 hover:text-teal-500">View board</a>
                    </div>
                </div>

                <!-- Idle Technicians -->
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-inset ring-gray-200 flex flex-col">
                    <div class="p-4 sm:p-5 flex-1">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-shrink-0 p-2 sm:p-3 rounded-lg bg-pink-50 border border-pink-100">
                                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-pink-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                            </div>
                            <span class="text-xs sm:text-sm font-medium text-gray-500">Idle Staff</span>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $stats['idle_technicians'] }}</span>
                            <span class="text-xs sm:text-sm font-medium text-pink-600">ready</span>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 sm:px-5 py-2 sm:py-3 border-t border-gray-100">
                        <a href="{{ route('work-orders.employee-stats') }}" class="text-xs sm:text-sm font-medium text-pink-600 hover:text-pink-500">View stats</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary Operational Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">

            <!-- Left Column: Today's Schedule -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-indigo-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-indigo-100">
                        <h3 class="text-lg font-semibold text-indigo-950">Today's Schedule</h3>
                    </div>
                    <ul role="list" class="divide-y divide-gray-100">
                        @forelse($todays_schedule as $job)
                            <li class="flex items-center justify-between gap-x-6 py-5 px-6 hover:bg-gray-50">
                                <div class="min-w-0">
                                    <div class="flex items-start gap-x-3">
                                        <p class="text-sm font-semibold leading-6 text-gray-900">{{ $job['time'] }} -
                                            {{ $job['customer'] }}</p>
                                        <p
                                            class="rounded-md whitespace-nowrap mt-0.5 px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $job['status_color'] }}">
                                            {{ $job['status_label'] }}</p>
                                    </div>
                                    <div class="mt-1 flex items-center gap-x-2 text-xs leading-5 text-gray-500">
                                        <p class="truncate">{{ $job['vehicle'] }}</p>
                                        <svg viewBox="0 0 2 2" class="h-0.5 w-0.5 fill-current">
                                            <circle cx="1" cy="1" r="1" />
                                        </svg>
                                        <p class="whitespace-nowrap">Bay {{ $job['bay'] }}</p>
                                        <svg viewBox="0 0 2 2" class="h-0.5 w-0.5 fill-current">
                                            <circle cx="1" cy="1" r="1" />
                                        </svg>
                                        <p class="truncate">Tech: {{ $job['technician'] }}</p>
                                    </div>
                                </div>
                                <div class="flex flex-none items-center gap-x-4">
                                    <a href="{{ route('work-orders.show', $job['id']) }}"
                                        class="hidden rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:block">View
                                        job<span class="sr-only">, {{ $job['customer'] }}</span></a>
                                </div>
                            </li>
                        @empty
                            <li class="px-6 py-8 text-center text-sm text-gray-500">
                                No jobs scheduled for today.
                                <a href="{{ route('work-orders.create') }}"
                                    class="text-indigo-600 hover:text-indigo-500 font-medium">Schedule one now</a>
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <!-- Right Column: Quick Actions & Tech Status -->
            <div class="space-y-8">

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-sm border border-indigo-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-indigo-100">
                        <h3 class="text-lg font-semibold text-indigo-950">Quick Actions</h3>
                    </div>
                    <div class="p-6 grid grid-cols-1 gap-4">
                        <a href="{{ route('checkin') }}"
                            class="flex items-center p-3 rounded-lg border border-indigo-100 bg-indigo-50/50 hover:bg-indigo-50 transition group">
                            <div class="p-2 rounded-md bg-indigo-100 text-indigo-600 group-hover:bg-indigo-200">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-indigo-900">New Check-in</p>
                                <p class="text-xs text-indigo-500">Register vehicle arrival</p>
                            </div>
                        </a>

                        <a href="{{ route('work-orders.create') }}"
                            class="flex items-center p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition group">
                            <div class="p-2 rounded-md bg-gray-100 text-gray-600 group-hover:bg-gray-200">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-900">Schedule Job</p>
                                <p class="text-xs text-gray-500">Book future work</p>
                            </div>
                        </a>

                        @if($todays_schedule->where('status', 'scheduled')->count() > 0)
                            <a href="{{ route('work-orders.index', ['status' => 'scheduled']) }}"
                                class="flex items-center p-3 rounded-lg border border-green-200 bg-green-50/30 hover:bg-green-50 transition group">
                                <div class="p-2 rounded-md bg-green-100 text-green-600 group-hover:bg-green-200">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-green-900">Start Job</p>
                                    <p class="text-xs text-green-600">
                                        {{ $todays_schedule->where('status', 'scheduled')->count() }} pending today</p>
                                </div>
                            </a>
                        @endif

                        <a href="{{ route('finance.index') }}"
                            class="flex items-center p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition group">
                            <div class="p-2 rounded-md bg-gray-100 text-gray-600 group-hover:bg-gray-200">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-900">Issue Invoice</p>
                                <p class="text-xs text-gray-500">Create new invoice</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Technician Status Board -->
                <div class="bg-white rounded-xl shadow-sm border border-indigo-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-indigo-100">
                        <h3 class="text-lg font-semibold text-indigo-950">Technician Status</h3>
                    </div>
                    <ul role="list" class="divide-y divide-gray-100">
                        @foreach($technician_status as $tech)
                            <li class="flex flex-col gap-y-2 py-4 px-6 hover:bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-x-3">
                                        <div
                                            class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-xs">
                                            {{ substr($tech['name'], 0, 2) }}
                                        </div>
                                        <h3 class="text-sm font-semibold leading-6 text-gray-900">{{ $tech['name'] }}</h3>
                                    </div>
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $tech['status'] === 'busy' ? 'bg-red-50 text-red-700 ring-red-600/20' : 'bg-green-50 text-green-700 ring-green-600/20' }}">
                                        {{ ucfirst($tech['status']) }}
                                    </span>
                                </div>
                                @if($tech['status'] === 'busy' && $tech['current_job'])
                                    <div class="ml-11 text-xs text-gray-500">
                                        <p>Working on: <span
                                                class="font-medium text-gray-900">{{ $tech['current_job']['vehicle'] }}</span></p>
                                        <p class="mt-1">Bay {{ $tech['current_job']['bay'] }} • Started
                                            {{ $tech['current_job']['started_at'] }} ({{ $tech['current_job']['duration'] }})</p>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>


        <!-- Interactive Tour -->
        <x-dashboard-tour />
    </div>

@if(tenant() && empty(tenant()->settings['has_seen_tour']))
    <div x-data="dashboardTour()" x-init="initTour()" class="fixed inset-0 z-[100] pointer-events-none"
        style="display: none;" x-show="step > 0">

        <!-- Backdrop (Dark Overlay) -->
        <div class="absolute inset-0 bg-black/50 transition-opacity duration-500 pointer-events-auto"
            x-transition:enter="opacity-0" x-transition:enter-end="opacity-100" x-show="step > 0"></div>

        <!-- Spotlight Element (Hole punch effect via high z-index stacking context or just absolute positioning of tooltip) -->
        <!-- We'll keep it simple: Just tooltips near target elements -->

        <!-- Tooltip Container -->
        <div class="absolute transition-all duration-500 ease-in-out pointer-events-auto" :style="tooltipStyle">

            <div class="bg-white rounded-2xl shadow-2xl p-6 w-80 relative">
                <!-- Arrow -->
                <div class="absolute w-4 h-4 bg-white transform rotate-45 -top-2 left-1/2 -translate-x-1/2"
                    x-show="position === 'bottom'"></div>
                <div class="absolute w-4 h-4 bg-white transform rotate-45a -bottom-2 left-1/2 -translate-x-1/2"
                    x-show="position === 'top'"></div>
                <div class="absolute w-4 h-4 bg-white transform rotate-45 -left-2 top-1/2 -translate-y-1/2"
                    x-show="position === 'right'"></div>
                <div class="absolute w-4 h-4 bg-white transform rotate-45 -right-2 top-1/2 -translate-y-1/2"
                    x-show="position === 'left'"></div>

                <!-- Content -->
                <div class="text-center">
                    <div
                        class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                        <span x-text="steps[step-1].icon"></span>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2" x-text="steps[step-1].title"></h3>
                    <p class="text-sm text-gray-500 mb-6" x-text="steps[step-1].text"></p>

                    <div class="flex justify-between items-center">
                        <button @click="skipTour" class="text-xs font-bold text-gray-400 hover:text-gray-600">Skip</button>

                        <button @click="nextStep"
                            class="bg-indigo-600 text-white px-5 py-2 rounded-lg text-sm font-bold hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-200">
                            <span x-text="step === steps.length ? 'Get Started' : 'Next'"></span>
                        </button>
                    </div>

                    <!-- Dots -->
                    <div class="flex justify-center space-x-1 mt-4">
                        <template x-for="i in steps.length">
                            <div class="w-1.5 h-1.5 rounded-full transition-colors"
                                :class="i === step ? 'bg-indigo-600' : 'bg-gray-200'"></div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function dashboardTour() {
            return {
                step: 0,
                position: 'bottom',
                tooltipStyle: 'top: 50%; left: 50%; transform: translate(-50%, -50%);',
                steps: [
                    {
                        title: "Welcome to IHRAUTO CRM",
                        text: "Your professional workshop management system is ready. Let's take a quick tour.",
                        icon: "👋",
                        target: null
                    },
                    {
                        title: "Dashboard Overview",
                        text: "Track your customers, revenue, and daily operations at a glance.",
                        icon: "📊",
                        target: "#nav-dashboard",
                        position: 'right'
                    },
                    {
                        title: "Vehicle Check-In",
                        text: "Register vehicles and manage active repairs in your workshop.",
                        icon: "🔧",
                        target: "#nav-checkin",
                        position: 'right'
                    },
                    {
                        title: "Tire Hotel",
                        text: "Manage seasonal tire storage, locations, and swap appointments here.",
                        icon: "🛞",
                        target: "#nav-tire-hotel",
                        position: 'right'
                    },
                    {
                        title: "Work Orders",
                        text: "Create detailed work orders with parts, labor, and service tracking.",
                        icon: "📋",
                        target: "a[href*='work-orders']",
                        position: 'right'
                    },
                    {
                        title: "Appointments",
                        text: "Schedule and manage customer appointments efficiently.",
                        icon: "📅",
                        target: "a[href*='appointments']",
                        position: 'right'
                    },
                    {
                        title: "Finance & Billing",
                        text: "Track payments, invoices, and financial overview of your business.",
                        icon: "💶",
                        target: "a[href*='finance']",
                        position: 'right'
                    },
                    {
                        title: "You're Ready!",
                        text: "Explore the system and enjoy your new CRM!",
                        icon: "🚀",
                        target: null
                    }
                ],

                initTour() {
                    // Delay start slightly
                    setTimeout(() => {
                        this.step = 1;
                        this.updatePosition();
                    }, 1000);
                },

                nextStep() {
                    if (this.step < this.steps.length) {
                        this.step++;
                        this.updatePosition();
                    } else {
                        this.finishTour();
                    }
                },

                skipTour() {
                    this.finishTour();
                },

                async finishTour() {
                    this.step = 0;
                    // Save state to backend so it doesn't show again
                    await fetch('{{ route("subscription.tour-complete") }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                    });
                },

                updatePosition() {
                    const currentStep = this.steps[this.step - 1];

                    if (!currentStep.target) {
                        // Center screen
                        this.tooltipStyle = 'top: 50%; left: 50%; transform: translate(-50%, -50%);';
                        this.position = 'bottom'; // Default arrow
                        return;
                    }

                    const el = document.querySelector(currentStep.target);
                    if (el) {
                        const rect = el.getBoundingClientRect();
                        const padding = 20;

                        // Simple positioning logic
                        // In a real app, use Popper.js or Floating UI
                        if (currentStep.position === 'right') {
                            this.tooltipStyle = `top: ${rect.top + (rect.height / 2) - 150}px; left: ${rect.right + padding}px;`;
                            this.position = 'right'; // Arrow points right (towards element on left)
                        } else if (currentStep.position === 'left') {
                            this.tooltipStyle = `top: ${rect.top + (rect.height / 2) - 150}px; left: ${rect.left - 320 - padding}px;`;
                            this.position = 'left'; // Arrow points left (towards element on right)
                        } else {
                            // Default bottom
                            this.tooltipStyle = `top: ${rect.bottom + padding}px; left: ${rect.left + (rect.width / 2) - 160}px;`;
                            this.position = 'top'; // Arrow points top
                        }

                        // Highlight effect on element
                        el.style.zIndex = "101";
                        el.style.position = "relative";
                        el.style.boxShadow = "0 0 0 4px rgba(255, 255, 255, 0.5), 0 0 0 8px rgba(99, 102, 241, 0.5)";

                        // Cleanup previous highlights
                        document.querySelectorAll('*').forEach(e => {
                            if (e !== el) {
                                e.style.zIndex = "";
                                e.style.position = "";
                                e.style.boxShadow = "";
                            }
                        });

                    } else {
                        // Fallback if element not found
                        this.tooltipStyle = 'top: 50%; left: 50%; transform: translate(-50%, -50%);';
                    }
                }
            }
        }
    </script>
@endif

@endsection
