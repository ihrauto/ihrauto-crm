@extends('layouts.app')

@section('title', 'Employee Directory')

@section('content')
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <nav class="flex text-sm font-medium text-indigo-500 mb-2">
                    <a href="{{ route('dashboard') }}" class="hover:text-indigo-700">Dashboard</a>
                    <span class="mx-2">/</span>
                    <a href="{{ route('work-orders.index') }}" class="hover:text-indigo-700">Work Orders</a>
                    <span class="mx-2">/</span>
                    <span class="text-indigo-900">Employees</span>
                </nav>
            </div>
            <div>
                <a href="{{ route('work-orders.index') }}"
                    class="inline-flex items-center px-4 py-2 border border-indigo-200 text-indigo-600 rounded-lg text-sm font-medium hover:bg-indigo-50 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Work Orders
                </a>
            </div>
        </div>

        <!-- Employee Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach($users as $user)
                <a href="{{ route('work-orders.employee-details', $user) }}" class="group flex items-center p-3 bg-white border border-indigo-50 rounded-lg hover:border-indigo-300 hover:shadow-md transition-all cursor-pointer">
                    <!-- Compact Avatar -->
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-sm group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                        {{ substr($user->name, 0, 1) }}
                    </div>
                    
                    <!-- Info -->
                    <div class="ml-3 flex-grow min-w-0">
                        <h3 class="text-sm font-bold text-indigo-900 truncate group-hover:text-indigo-700">{{ $user->name }}</h3>
                        <p class="text-xs text-gray-500 truncate uppercase tracking-wider">{{ ucfirst($user->role) }}</p>
                    </div>

                    <!-- Arrow Icon (Subtle) -->
                    <div class="ml-2 text-gray-300 group-hover:text-indigo-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
@endsection