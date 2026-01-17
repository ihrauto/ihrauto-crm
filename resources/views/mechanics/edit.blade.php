@extends('layouts.app')

@section('title', 'Edit Mechanic')

@section('content')
    <div class="max-w-2xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex items-center space-x-4">
            <a href="{{ route('mechanics.show', $mechanic) }}"
                class="text-indigo-500 hover:text-indigo-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18">
                    </path>
                </svg>
            </a>
            <h1 class="text-2xl font-bold text-indigo-950 tracking-tight">Edit Mechanic</h1>
        </div>

        <!-- Form Card -->
        <x-card class="border border-indigo-100 shadow-lg shadow-indigo-100/50">
            <form method="POST" action="{{ route('mechanics.update', $mechanic) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-semibold text-indigo-900 mb-2">Full Name *</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $mechanic->name) }}" required
                        class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900 placeholder-indigo-300"
                        placeholder="Enter mechanic's full name">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-semibold text-indigo-900 mb-2">Email Address *</label>
                    <input type="email" name="email" id="email" value="{{ old('email', $mechanic->email) }}" required
                        class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900 placeholder-indigo-300"
                        placeholder="mechanic@example.com">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Phone -->
                <div>
                    <label for="phone" class="block text-sm font-semibold text-indigo-900 mb-2">Phone Number</label>
                    <input type="text" name="phone" id="phone" value="{{ old('phone', $mechanic->phone) }}"
                        class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900 placeholder-indigo-300"
                        placeholder="+41 123 456 789">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Hourly Rate -->
                <div>
                    <label for="hourly_rate" class="block text-sm font-semibold text-indigo-900 mb-2">Hourly Rate
                        (€)</label>
                    <input type="number" name="hourly_rate" id="hourly_rate"
                        value="{{ old('hourly_rate', $mechanic->hourly_rate) }}" step="0.01" min="0"
                        class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900 placeholder-indigo-300"
                        placeholder="45.00">
                    @error('hourly_rate')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-indigo-100">
                    <a href="{{ route('mechanics.show', $mechanic) }}"
                        class="px-5 py-2.5 text-indigo-600 border border-indigo-200 rounded-lg font-semibold hover:bg-indigo-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                        class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-300 transition-colors shadow-md">
                        Save Changes
                    </button>
                </div>
            </form>
        </x-card>
    </div>
@endsection