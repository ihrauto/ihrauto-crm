@extends('layouts.app')

@section('title', 'Work Orders')

@section('content')
    <div class="space-y-6">
        <!-- Header removed as per request -->

        <!-- Header Action Buttons -->
        <div class="flex flex-col sm:flex-row justify-end gap-3">
             <a href="{{ route('work-orders.board') }}" class="px-4 py-3 sm:py-2 bg-indigo-600 text-white rounded-lg text-sm font-bold hover:bg-indigo-700 transition-colors flex items-center justify-center gap-2 shadow-sm min-h-[44px]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                Technician Live Board
            </a>
            <a href="{{ route('work-orders.employee-stats') }}" class="px-4 py-3 sm:py-2 bg-white text-indigo-600 border border-indigo-200 rounded-lg text-sm font-bold hover:bg-indigo-50 transition-colors flex items-center justify-center gap-2 shadow-sm min-h-[44px]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                Employee Stats
            </a>
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
        @if($completed_orders->count() > 0)
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-indigo-100 opacity-75">
                <div class="p-6 border-b border-indigo-50">
                    <h2 class="text-lg font-semibold text-indigo-900">Recent Completed Jobs</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-indigo-50">
                        <tbody class="bg-white divide-y divide-indigo-50">
                            @foreach($completed_orders as $order)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">#{{ $order->id }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $order->customer->name ?? 'Unknown Customer' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $order->vehicle->make ?? 'Unknown Vehicle' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium">Completed
                                        {{ $order->completed_at->format('M d') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('work-orders.show', $order) }}"
                                            class="text-indigo-400 hover:text-indigo-600">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection