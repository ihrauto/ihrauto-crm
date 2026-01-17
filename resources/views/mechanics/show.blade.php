@extends('layouts.app')

@section('title', $mechanic->name)

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <!-- Success/Error Messages -->
        @if(session('success'))
            <div
                class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg flex items-center shadow-sm">
                <svg class="w-5 h-5 mr-2 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('mechanics.index') }}" class="text-indigo-500 hover:text-indigo-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-indigo-950 tracking-tight">Mechanic Details</h1>
            </div>
            <a href="{{ route('mechanics.edit', $mechanic) }}"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-colors shadow-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                    </path>
                </svg>
                Edit
            </a>
        </div>

        <!-- Profile Card -->
        <x-card class="border border-indigo-100 shadow-lg shadow-indigo-100/50">
            <div class="flex items-start space-x-6">
                <!-- Avatar -->
                <div
                    class="w-20 h-20 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-2xl flex-shrink-0">
                    {{ strtoupper(substr($mechanic->name, 0, 2)) }}
                </div>

                <!-- Info -->
                <div class="flex-1 space-y-4">
                    <div>
                        <h2 class="text-xl font-bold text-indigo-950">{{ $mechanic->name }}</h2>
                        <span
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $mechanic->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800' }} mt-1">
                            {{ $mechanic->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Email -->
                        <div>
                            <label
                                class="block text-xs font-semibold text-indigo-500 uppercase tracking-wide mb-1">Email</label>
                            <p class="text-indigo-900">{{ $mechanic->email }}</p>
                        </div>

                        <!-- Phone -->
                        <div>
                            <label
                                class="block text-xs font-semibold text-indigo-500 uppercase tracking-wide mb-1">Phone</label>
                            <p class="text-indigo-900">{{ $mechanic->phone ?? '-' }}</p>
                        </div>

                        <!-- Hourly Rate -->
                        <div>
                            <label class="block text-xs font-semibold text-indigo-500 uppercase tracking-wide mb-1">Hourly
                                Rate</label>
                            <p class="text-indigo-900">
                                {{ $mechanic->hourly_rate ? '€' . number_format($mechanic->hourly_rate, 2) : 'Not set' }}
                            </p>
                        </div>

                        <!-- Added Date -->
                        <div>
                            <label
                                class="block text-xs font-semibold text-indigo-500 uppercase tracking-wide mb-1">Added</label>
                            <p class="text-indigo-900">{{ $mechanic->created_at->format('M d, Y') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </x-card>

        <!-- App Access Card -->
        <x-card class="border border-indigo-100 shadow-lg shadow-indigo-100/50">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-indigo-950">App Access</h3>
                    <p class="text-sm text-indigo-500 mt-1">
                        @if($mechanic->email_verified_at)
                            This mechanic has access to the app
                        @else
                            This mechanic has not been invited to the app yet
                        @endif
                    </p>
                </div>

                @if(!$mechanic->email_verified_at)
                    <form method="POST" action="{{ route('mechanics.invite', $mechanic) }}">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-colors shadow-md border-0">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                </path>
                            </svg>
                            Invite to App
                        </button>
                    </form>
                @else
                    <span
                        class="inline-flex items-center px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-lg text-sm font-medium">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Has App Access
                    </span>
                @endif
            </div>
        </x-card>

        <!-- Danger Zone -->
        <x-card class="border border-red-100 bg-red-50/30">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-red-900">Danger Zone</h3>
                    <p class="text-sm text-red-600 mt-1">Once deleted, this mechanic cannot be recovered.</p>
                </div>
                <form method="POST" action="{{ route('mechanics.destroy', $mechanic) }}"
                    onsubmit="return confirm('Are you sure you want to remove this mechanic? This action cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                            </path>
                        </svg>
                        Remove Mechanic
                    </button>
                </form>
            </div>
        </x-card>
    </div>
@endsection