@extends('layouts.app')

@section('title', 'Work Bays Management')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6" x-data="serviceBays()">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <nav class="flex text-sm font-medium text-indigo-500 mb-2">
                    <a href="{{ route('work-orders.index') }}" class="hover:text-indigo-700">Work Orders</a>
                    <span class="mx-2">/</span>
                    <span class="text-indigo-900">Work Bays</span>
                </nav>
            </div>
            <a href="{{ route('work-orders.index') }}"
                class="inline-flex items-center px-4 py-2 border border-indigo-200 text-indigo-600 rounded-lg text-sm font-medium hover:bg-indigo-50 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18">
                    </path>
                </svg>
                Back to Work Orders
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-50 text-green-700 p-4 rounded-lg border border-green-200">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 text-red-700 p-4 rounded-lg border border-red-200">
                {{ session('error') }}
            </div>
        @endif

        <!-- Bays List -->
        <div class="bg-white rounded-xl shadow-sm border border-indigo-100 overflow-hidden">

            <div class="p-4 space-y-3">
                @foreach($bays as $bay)
                    <div
                        class="flex items-center gap-3 p-2 bg-indigo-50/30 rounded-lg border border-indigo-100 hover:bg-indigo-50/50 transition-colors group">
                        <!-- Bay Name Input -->
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-indigo-400 mb-1">Bay name *</label>
                            <form action="{{ route('work-bays.update', $bay) }}" method="POST" class="flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name" value="{{ $bay->name }}"
                                    class="flex-1 px-3 py-2 rounded-lg border border-indigo-200 text-indigo-900 font-medium text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white"
                                    required>
                                <button type="submit"
                                    class="opacity-0 group-hover:opacity-100 px-2 py-1 text-indigo-600 hover:text-indigo-800 text-xs font-medium transition-opacity">
                                    Save
                                </button>
                            </form>
                        </div>

                        <!-- Delete Button -->
                        <form action="{{ route('work-bays.destroy', $bay) }}" method="POST"
                            onsubmit="return confirm('Are you sure you want to delete this bay?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="w-8 h-8 flex items-center justify-center rounded-full border border-gray-300 text-gray-400 hover:border-red-400 hover:text-red-500 hover:bg-red-50 transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                </svg>
                            </button>
                        </form>
                    </div>
                @endforeach

                @if($bays->isEmpty())
                    <div class="text-center py-12 text-indigo-300">
                        <svg class="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                            </path>
                        </svg>
                        <p>No service bays configured yet.</p>
                    </div>
                @endif
            </div>

            <!-- Footer Actions -->
            <div class="px-6 py-4 border-t border-indigo-50 bg-indigo-50/30 flex items-center justify-between">
                <p class="text-xs text-indigo-400">* Required</p>

                <!-- Add Bay Form -->
                <form action="{{ route('work-bays.store') }}" method="POST" class="flex items-center gap-3">
                    @csrf
                    <input type="text" name="name" placeholder="New bay name..." required
                        class="px-4 py-2 rounded-lg border border-indigo-200 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-48">
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 text-sm font-bold text-orange-600 hover:text-orange-700 transition-colors uppercase tracking-wide">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add Bay
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function serviceBays() {
            return {
                // Future: Add drag-and-drop reordering
            }
        }
    </script>
@endsection