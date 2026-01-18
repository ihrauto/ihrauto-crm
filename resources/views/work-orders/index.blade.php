@extends('layouts.app')

@section('title', 'Work Orders')

@section('content')
    <div class="space-y-6">
        <!-- Header removed as per request -->

        <!-- Header Action Buttons -->
        <div class="flex flex-col sm:flex-row justify-between gap-3">
            <!-- Left side buttons -->
            <div class="flex flex-col sm:flex-row gap-3">
                @can('access management')
                <a href="{{ route('work-orders.board') }}" class="px-4 py-3 sm:py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-indigo-700 transition-colors flex items-center justify-center gap-2 shadow-sm min-h-[44px]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    Technician Status
                </a>
                @endcan
                <a href="{{ route('work-orders.employee-stats') }}" class="px-4 py-3 sm:py-2 bg-white text-indigo-600 border border-indigo-200 rounded-lg text-sm font-bold hover:bg-indigo-50 transition-colors flex items-center justify-center gap-2 shadow-sm min-h-[44px]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    Employee Stats
                </a>
            </div>
            <!-- Right side buttons (management only) -->
            @can('access management')
            <div class="flex flex-col sm:flex-row gap-3">
                <a href="{{ route('mechanics.index') }}" class="px-4 py-3 sm:py-2 bg-white text-indigo-600 border border-indigo-200 rounded-lg text-sm font-bold hover:bg-indigo-50 transition-colors flex items-center justify-center gap-2 shadow-sm min-h-[44px]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Mechanics
                </a>
                <a href="{{ route('work-bays.index') }}" class="px-4 py-3 sm:py-2 bg-white text-indigo-600 border border-indigo-200 rounded-lg text-sm font-bold hover:bg-indigo-50 transition-colors flex items-center justify-center gap-2 shadow-sm min-h-[44px]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    Work Bay
                </a>
            </div>
            @endcan
        </div>

        <!-- Active Orders -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-indigo-100">
            <div class="p-6 border-b border-indigo-50 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-indigo-900">Active Jobs</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-indigo-50">
                    <thead class="bg-indigo-50/50">
                        <tr>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-indigo-500 uppercase tracking-wider">WO
                                #</th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-indigo-500 uppercase tracking-wider">
                                Source</th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-indigo-500 uppercase tracking-wider">
                                Customer</th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-indigo-500 uppercase tracking-wider">
                                Vehicle</th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-indigo-500 uppercase tracking-wider">
                                Status</th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-indigo-500 uppercase tracking-wider">
                                Technician</th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-medium text-indigo-500 uppercase tracking-wider">
                                Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-indigo-50">
                        @forelse($active_orders as $order)
                            <tr class="hover:bg-indigo-50/30 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-indigo-900">
                                    #{{ $order->id }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($order->checkin_id)
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Check-in
                                        </span>
                                    @else
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            Tire Hotel
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    {{ $order->customer->name ?? 'Unknown Customer' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    {{ $order->vehicle->make ?? 'Unknown' }} {{ $order->vehicle->model ?? 'Vehicle' }}
                                    <span class="block text-xs text-indigo-400">{{ $order->vehicle->license_plate ?? 'No Plate' }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $order->status_badge_color }}">
                                        {{ $order->status_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $order->technician ? $order->technician->name : 'Unassigned' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="{{ route('work-orders.show', $order) }}"
                                        class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700 transition-colors">Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-indigo-300">
                                    No active work orders found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Completed History (Brief) -->
        <div class="bg-white shadow-sm sm:rounded-lg border border-indigo-100 opacity-90">
            <div class="p-6 border-b border-indigo-50 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-indigo-900">Completed Jobs</h2>
                <div class="flex items-center gap-3" x-data="datePicker()">
                    <span class="text-sm font-medium text-gray-500">Date:</span>
                    
                    {{-- Day Picker --}}
                    <div class="relative">
                        <button @click="dayOpen = !dayOpen; monthOpen = false; yearOpen = false" 
                            class="date-picker-btn" type="button">
                            <span x-text="selectedDay.toString().padStart(2, '0')"></span>
                            <svg class="w-4 h-4 ml-2 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="dayOpen" @click.away="dayOpen = false" x-transition
                            class="date-picker-dropdown" style="width: 260px;">
                            {{-- Month Header --}}
                            <div class="text-center font-semibold text-gray-800 pb-3 mb-3 border-b border-gray-100">
                                <span x-text="months[selectedMonth - 1]"></span>
                            </div>
                            {{-- Weekday Headers --}}
                            <div class="day-grid" style="margin-bottom: 8px;">
                                <template x-for="day in ['S', 'M', 'T', 'W', 'T', 'F', 'S']" :key="day">
                                    <div class="weekday-header" x-text="day"></div>
                                </template>
                            </div>
                            {{-- Day Numbers --}}
                            <div class="day-grid">
                                <template x-for="d in 31" :key="d">
                                    <button type="button" @click="selectDay(d)"
                                        :class="{'selected-item rounded-full': selectedDay === d, 'hover:bg-indigo-50 rounded-full': selectedDay !== d}"
                                        class="date-picker-option">
                                        <span x-text="d"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Month Picker --}}
                    <div class="relative">
                        <button @click="monthOpen = !monthOpen; dayOpen = false; yearOpen = false" 
                            class="date-picker-btn" type="button">
                            <span x-text="months[selectedMonth - 1]"></span>
                            <svg class="w-4 h-4 ml-2 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="monthOpen" @click.away="monthOpen = false" x-transition
                            class="date-picker-dropdown" style="width: 160px;">
                            <div class="month-grid">
                                <template x-for="(mon, idx) in months" :key="idx">
                                    <button type="button" @click="selectMonth(idx + 1)"
                                        :class="{'selected-item': selectedMonth === idx + 1, 'hover:bg-indigo-50': selectedMonth !== idx + 1}"
                                        class="date-picker-option">
                                        <span x-text="mon"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Year Picker --}}
                    <div class="relative">
                        <button @click="yearOpen = !yearOpen; dayOpen = false; monthOpen = false" 
                            class="date-picker-btn" type="button">
                            <span x-text="selectedYear"></span>
                            <svg class="w-4 h-4 ml-2 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="yearOpen" @click.away="yearOpen = false" x-transition
                            class="date-picker-dropdown" style="width: 80px;">
                            <div class="year-grid">
                                <template x-for="y in years" :key="y">
                                    <button type="button" @click="selectYear(y)"
                                        :class="{'selected-item': selectedYear === y, 'hover:bg-indigo-50': selectedYear !== y}"
                                        class="date-picker-option">
                                        <span x-text="y"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <style>
                .date-picker-btn {
                    display: flex;
                    align-items: center;
                    background-color: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 8px 12px;
                    font-size: 14px;
                    font-weight: 500;
                    color: #334155;
                    cursor: pointer;
                    transition: all 0.15s ease;
                }
                .date-picker-btn:hover {
                    border-color: #a5b4fc;
                    background-color: #eef2ff;
                }
                .date-picker-dropdown {
                    position: absolute;
                    top: 100%;
                    left: 0;
                    margin-top: 4px;
                    background: white;
                    border: 1px solid #e2e8f0;
                    border-radius: 12px;
                    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
                    padding: 8px;
                    z-index: 50;
                }
                .date-picker-option {
                    padding: 4px;
                    font-size: 11px;
                    font-weight: 500;
                    text-align: center;
                    border-radius: 4px;
                    color: #374151;
                    cursor: pointer;
                    transition: all 0.1s ease;
                    min-width: 28px;
                }
                .date-picker-option.selected-item {
                    background-color: #4f46e5 !important;
                    color: #ffffff !important;
                }
                .day-grid {
                    display: grid;
                    grid-template-columns: repeat(7, 1fr);
                    gap: 2px;
                }
                .month-grid {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 4px;
                }
                .year-grid {
                    display: grid;
                    grid-template-columns: 1fr;
                    gap: 4px;
                }
                .weekday-header {
                    text-align: center;
                    font-size: 10px;
                    font-weight: 500;
                    color: #9ca3af;
                    padding: 2px;
                }
                .completed-jobs-scroll {
                    max-height: 360px;
                    overflow-y: auto;
                }
            </style>
            <div class="overflow-x-auto completed-jobs-scroll">
                <table class="min-w-full divide-y divide-indigo-50">
                    <tbody class="bg-white divide-y divide-indigo-50">
                            @forelse($completed_orders as $order)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">#{{ $order->id }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($order->checkin_id)
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Check-in
                                            </span>
                                        @else
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                Tire Hotel
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $order->customer->name ?? 'Unknown Customer' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $order->vehicle->make ?? 'Unknown Vehicle' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium">Completed
                                        {{ $order->completed_at->format('M d') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('work-orders.show', $order) }}"
                                            class="text-indigo-400 hover:text-indigo-600">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">
                                        No completed jobs for this date.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
    </div>

    <script>
        function datePicker() {
            const currentDate = '{{ $completedDate }}';
            const parts = currentDate.split('-');
            
            return {
                dayOpen: false,
                monthOpen: false,
                yearOpen: false,
                selectedDay: parseInt(parts[2]) || new Date().getDate(),
                selectedMonth: parseInt(parts[1]) || new Date().getMonth() + 1,
                selectedYear: parseInt(parts[0]) || new Date().getFullYear(),
                months: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                years: [{{ date('Y') - 1 }}, {{ date('Y') }}, {{ date('Y') + 1 }}],
                
                navigateToDate() {
                    const year = this.selectedYear;
                    const month = this.selectedMonth.toString().padStart(2, '0');
                    const day = this.selectedDay.toString().padStart(2, '0');
                    const dateStr = `${year}-${month}-${day}`;
                    
                    const url = new URL(window.location.href);
                    url.searchParams.set('completed_date', dateStr);
                    window.location.href = url.toString();
                },
                
                selectDay(d) {
                    this.selectedDay = d;
                    this.dayOpen = false;
                    this.navigateToDate();
                },
                
                selectMonth(m) {
                    this.selectedMonth = m;
                    this.monthOpen = false;
                    this.navigateToDate();
                },
                
                selectYear(y) {
                    this.selectedYear = y;
                    this.yearOpen = false;
                    this.navigateToDate();
                }
            }
        }
    </script>
@endsection