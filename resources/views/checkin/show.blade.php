@extends('layouts.app')

@section('title', 'Check-in Details')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-indigo-900">Check-in #{{ $checkin->id }}</h1>
                <p class="text-sm text-indigo-500">Created on {{ $checkin->created_at->format('M d, Y H:i') }}</p>
            </div>
            <a href="{{ route('checkin') }}" 
               class="group inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-500 transition-colors">
                <div class="w-6 h-6 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mr-2 group-hover:bg-indigo-100 transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7 7-7"></path>
                    </svg>
                </div>
                Back to Check-ins
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Main Info -->
            <div class="md:col-span-2 space-y-6">
                <!-- Status Card -->
                <div class="bg-white rounded-xl border border-indigo-100 p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-indigo-950 mb-4 border-b border-indigo-50 pb-2">Status</h3>
                    <div class="flex items-center space-x-3">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                            @if($checkin->status === 'completed') bg-emerald-100 text-emerald-800
                            @elseif($checkin->status === 'in_progress') bg-indigo-100 text-indigo-800
                            @elseif($checkin->status === 'cancelled') bg-red-100 text-red-800
                            @else bg-amber-100 text-amber-800 @endif">
                            {{ ucfirst(str_replace('_', ' ', $checkin->status)) }}
                        </span>
                    </div>
                </div>

                <!-- Vehicle Details -->
                <div class="bg-white rounded-xl border border-indigo-100 p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-indigo-950 mb-4 border-b border-indigo-50 pb-2">Vehicle Information</h3>
                    @if($checkin->vehicle)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-bold text-indigo-400 uppercase">Make & Model</label>
                                <p class="text-indigo-900 font-medium">{{ $checkin->vehicle->make }} {{ $checkin->vehicle->model }}</p>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-indigo-400 uppercase">Year</label>
                                <p class="text-indigo-900 font-medium">{{ $checkin->vehicle->year }}</p>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-indigo-400 uppercase">License Plate</label>
                                <p class="text-indigo-900 font-medium font-mono bg-indigo-50 inline-block px-2 py-1 rounded">{{ $checkin->vehicle->license_plate }}</p>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-indigo-400 uppercase">VIN</label>
                                <p class="text-indigo-900 font-medium font-mono text-sm">{{ $checkin->vehicle->vin ?? 'N/A' }}</p>
                            </div>
                        </div>
                    @else
                        <p class="text-gray-500 italic">No vehicle attached to this check-in.</p>
                    @endif
                </div>

                <!-- Services / Notes (Placeholder if services relationship exists) -->
                @if(isset($checkin->services) || $checkin->notes)
                <div class="bg-white rounded-xl border border-indigo-100 p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-indigo-950 mb-4 border-b border-indigo-50 pb-2">Details</h3>
                   
                    @if($checkin->notes)
                        <div class="mb-4">
                            <label class="text-xs font-bold text-indigo-400 uppercase block mb-1">Notes</label>
                            <p class="text-gray-700 bg-gray-50 p-3 rounded-lg">{{ $checkin->notes }}</p>
                        </div>
                    @endif
                </div>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Customer Card -->
                <div class="bg-white rounded-xl border border-indigo-100 p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-indigo-950 mb-4 border-b border-indigo-50 pb-2">Customer</h3>
                    @if($checkin->customer)
                        <div class="space-y-3">
                            <div>
                                <label class="text-xs font-bold text-indigo-400 uppercase">Name</label>
                                <p class="text-indigo-900 font-medium text-lg">{{ $checkin->customer->name }}</p>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-indigo-400 uppercase">Phone</label>
                                <p class="text-indigo-900 font-medium">{{ $checkin->customer->phone ?? $checkin->customer->phone_number ?? '-' }}</p>
                            </div>
                            <div>
                                <label class="text-xs font-bold text-indigo-400 uppercase">Email</label>
                                <p class="text-indigo-900 font-medium">{{ $checkin->customer->email ?? '-' }}</p>
                            </div>
                            <div class="pt-4 mt-4 border-t border-indigo-50">
                                <a href="{{ route('customers.show', $checkin->customer->id) }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium hover:underline">
                                    View Customer Profile &rarr;
                                </a>
                            </div>
                        </div>
                    @else
                        <p class="text-gray-500 italic">No customer attached.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
