@extends('layouts.app')

@section('content')
    <div class="min-h-screen p-6">
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <h1 class="text-3xl font-bold text-indigo-900 tracking-tight">Tire Details</h1>
                <a href="{{ route('tires-hotel') }}"
                    class="inline-flex items-center px-4 py-2 border border-indigo-200 text-indigo-600 rounded-lg text-sm font-medium hover:bg-indigo-50 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Tire Hotel
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Customer & Vehicle Info -->
                <div class="bg-white rounded-2xl shadow-sm border border-indigo-100 p-6">
                    <h2 class="text-lg font-semibold text-indigo-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Owner Information
                    </h2>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide">Customer</label>
                            <div class="text-gray-900 font-medium text-lg">{{ $tire->vehicle->customer->name ?? 'Unknown' }}
                            </div>
                            @if($tire->vehicle->customer->phone ?? $tire->vehicle->customer->phone_number ?? false)
                                <div class="text-indigo-600">
                                    {{ $tire->vehicle->customer->phone ?? $tire->vehicle->customer->phone_number }}
                                </div>
                            @endif
                        </div>

                        <div class="pt-4 border-t border-gray-100">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide">Vehicle</label>
                            <div class="text-gray-900 font-bold">{{ $tire->vehicle->make }} {{ $tire->vehicle->model }}
                            </div>
                            <div
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 mt-1">
                                {{ $tire->vehicle->license_plate }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Storage Info -->
                <div class="bg-white rounded-2xl shadow-sm border border-indigo-100 p-6 flex flex-col justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-indigo-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                                </path>
                            </svg>
                            Storage Location
                        </h2>

                        <div class="text-center py-8">
                            <div class="text-6xl font-black text-indigo-600 font-mono tracking-tighter">
                                {{ $tire->storage_location }}
                            </div>
                            <div class="text-sm text-gray-400 mt-2">Section - Row - Slot</div>
                        </div>
                    </div>
                    <div class="bg-indigo-50 rounded-xl p-4">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-indigo-900 font-medium">Stored On:</span>
                            <span class="text-indigo-600 font-bold">{{ $tire->created_at->format('d M Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tire Info -->
            <div class="bg-white rounded-2xl shadow-sm border border-indigo-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-50 bg-gray-50/50 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-indigo-900">Tire Specifications</h3>
                    <span
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $tire->season === 'Winter' ? 'bg-blue-100 text-blue-800' : ($tire->season === 'Summer' ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-800') }}">
                        {{ $tire->season }} Tires
                    </span>
                </div>
                <div class="p-6 grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-xs text-gray-400 uppercase">Brand</label>
                        <div class="font-bold text-gray-900 text-lg">{{ $tire->brand }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 uppercase">Size</label>
                        <div class="font-bold text-gray-900 text-lg">{{ $tire->size }}</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 uppercase">Tread Depth</label>
                        <div class="font-bold text-gray-900 text-lg">{{ $tire->tread_depth }} mm</div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 uppercase">Rims</label>
                        <div class="font-bold text-gray-900 text-lg">{{ $tire->has_rims ? 'Yes' : 'No' }}</div>
                    </div>
                    @if($tire->dot)
                        <div class="col-span-2">
                            <label class="block text-xs text-gray-400 uppercase">DOT Code</label>
                            <div class="font-mono text-gray-700">{{ $tire->dot }}</div>
                        </div>
                    @endif
                    @if($tire->note)
                        <div class="col-span-full bg-yellow-50 p-4 rounded-lg border border-yellow-100">
                            <label class="block text-xs text-yellow-600 uppercase font-bold mb-1">Notes</label>
                            <div class="text-yellow-800 text-sm italic">"{{ $tire->note }}"</div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                <form method="POST" action="/tires-hotel/{{ $tire->id }}/generate-work-order">
                    @csrf
                    <button type="submit"
                        class="px-4 py-2 bg-white text-indigo-600 font-semibold rounded-lg border border-indigo-200 hover:bg-indigo-50 shadow-sm transition-all focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Create Work Order
                    </button>
                </form>

                <form method="POST" action="/tires-hotel/{{ $tire->id }}" onsubmit="return confirm('Are you sure?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="px-4 py-2 bg-red-50 text-red-600 font-semibold rounded-lg border border-red-100 hover:bg-red-100 shadow-sm transition-all focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        Delete
                    </button>
                </form>


            </div>
        </div>
    </div>
@endsection