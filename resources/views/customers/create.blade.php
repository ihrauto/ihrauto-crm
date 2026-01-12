@extends('layouts.app')

@section('title', 'Add New Customer')

@section('content')
    <div class="space-y-6">
        <!-- Breadcrumbs -->
        <nav class="text-sm font-medium text-indigo-900/60">
            <a href="{{ route('customers.index') }}" class="hover:text-indigo-600 transition-colors">Customers</a>
            <span class="mx-2 text-indigo-300">/</span>
            <span class="text-indigo-600">Add New Customer</span>
        </nav>

        <!-- Header -->
        <div class="flex items-center space-x-4">
            <a href="{{ route('customers.index') }}"
                class="p-2 rounded-lg text-indigo-600 hover:bg-indigo-50 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="text-2xl font-bold text-indigo-950 tracking-tight">Add New Customer</h1>
        </div>

        <!-- Form -->
        <x-card class="border border-indigo-100 shadow-lg ring-1 ring-indigo-50/50">
            <form method="POST" action="{{ route('customers.store') }}" class="space-y-8">
                @csrf

                <!-- Basic Information -->
                <div>
                    <h3 class="text-lg font-bold text-indigo-900 mb-6 border-b border-indigo-50 pb-2">Basic Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="text-sm font-semibold text-indigo-900 mb-1.5 block">Customer Name <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="name" value="{{ old('name') }}" required
                                class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900 placeholder-indigo-300 bg-white shadow-sm @error('name') border-red-300 focus:ring-red-200 focus:border-red-400 @enderror">
                            @error('name')
                                <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-indigo-900 mb-1.5 block">Phone Number <span
                                    class="text-red-500">*</span></label>
                            <input type="tel" name="phone" value="{{ old('phone') }}" required
                                class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900 placeholder-indigo-300 bg-white shadow-sm @error('phone') border-red-300 focus:ring-red-200 focus:border-red-400 @enderror">
                            @error('phone')
                                <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-indigo-900 mb-1.5 block">Email Address</label>
                            <input type="email" name="email" value="{{ old('email') }}"
                                class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900 placeholder-indigo-300 bg-white shadow-sm @error('email') border-red-300 focus:ring-red-200 focus:border-red-400 @enderror">
                            @error('email')
                                <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-indigo-900 mb-1.5 block">Date of Birth</label>
                            <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}"
                                class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900 bg-white shadow-sm @error('date_of_birth') border-red-300 focus:ring-red-200 focus:border-red-400 @enderror">
                            @error('date_of_birth')
                                <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Address Information -->
                <div>
                    <h3 class="text-lg font-bold text-indigo-900 mb-6 border-b border-indigo-50 pb-2">Address Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="text-sm font-semibold text-indigo-900 mb-1.5 block">Street Address</label>
                            <input type="text" name="address" value="{{ old('address') }}"
                                class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900 placeholder-indigo-300 bg-white shadow-sm @error('address') border-red-300 focus:ring-red-200 focus:border-red-400 @enderror">
                            @error('address')
                                <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-indigo-900 mb-1.5 block">City</label>
                            <input type="text" name="city" value="{{ old('city') }}"
                                class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900 placeholder-indigo-300 bg-white shadow-sm @error('city') border-red-300 focus:ring-red-200 focus:border-red-400 @enderror">
                            @error('city')
                                <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-indigo-900 mb-1.5 block">Postal Code</label>
                            <input type="text" name="postal_code" value="{{ old('postal_code') }}"
                                class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900 placeholder-indigo-300 bg-white shadow-sm @error('postal_code') border-red-300 focus:ring-red-200 focus:border-red-400 @enderror">
                            @error('postal_code')
                                <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-indigo-900 mb-1.5 block">Country</label>
                            <input type="text" name="country" value="{{ old('country') }}"
                                class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900 placeholder-indigo-300 bg-white shadow-sm @error('country') border-red-300 focus:ring-red-200 focus:border-red-400 @enderror">
                            @error('country')
                                <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Additional Information -->
                <div>
                    <h3 class="text-lg font-bold text-indigo-900 mb-6 border-b border-indigo-50 pb-2">Additional Information
                    </h3>
                    <div>
                        <label class="text-sm font-semibold text-indigo-900 mb-1.5 block">Notes</label>
                        <textarea name="notes" rows="4"
                            class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900 placeholder-indigo-300 bg-white shadow-sm @error('notes') border-red-300 focus:ring-red-200 focus:border-red-400 @enderror"
                            placeholder="Any additional notes about this customer...">{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="text-red-500 text-xs mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end space-x-3 pt-6 border-t border-indigo-50">
                    <a href="{{ route('customers.index') }}"
                        class="px-5 py-2.5 border border-indigo-200 rounded-lg text-indigo-700 hover:bg-indigo-50 font-semibold transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                        class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg uppercase font-bold text-sm tracking-wide hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-300 transition-all shadow-md transform hover:-translate-y-0.5">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Create Customer
                    </button>
                </div>
            </form>
        </x-card>
    </div>
@endsection