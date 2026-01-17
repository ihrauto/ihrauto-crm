@extends('layouts.app')

@section('title', 'Mechanics')

@section('content')
    <div class="space-y-6">
        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg flex items-center shadow-sm">
                <svg class="w-5 h-5 mr-2 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center shadow-sm">
                <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                {{ session('error') }}
            </div>
        @endif

        <!-- Header Actions -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center space-x-4">
                <a href="{{ route('work-orders.index') }}" class="text-indigo-500 hover:text-indigo-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h1 class="text-2xl font-bold text-indigo-950 tracking-tight">Mechanics</h1>
                <span class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-bold border border-indigo-200">
                    {{ $mechanics->total() }} Total
                </span>
            </div>

            <a href="{{ route('mechanics.create') }}"
                class="inline-flex items-center justify-center px-5 py-2.5 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-300 transition-all shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Add Mechanic
            </a>
        </div>

        <!-- Mechanics Table -->
        <x-card class="border border-indigo-100 shadow-lg shadow-indigo-100/50 overflow-hidden ring-1 ring-indigo-50/50 p-0">
            @if($mechanics->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-indigo-50/50 border-b border-indigo-100">
                            <tr>
                                <th class="text-left py-4 px-6 text-xs font-bold text-indigo-900 uppercase tracking-wider">Name</th>
                                <th class="text-left py-4 px-6 text-xs font-bold text-indigo-900 uppercase tracking-wider">Email</th>
                                <th class="text-left py-4 px-6 text-xs font-bold text-indigo-900 uppercase tracking-wider">Status</th>
                                <th class="text-left py-4 px-6 text-xs font-bold text-indigo-900 uppercase tracking-wider">Added</th>
                                <th class="text-right py-4 px-6 text-xs font-bold text-indigo-900 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-indigo-50">
                            @foreach($mechanics as $mechanic)
                                <tr class="hover:bg-indigo-50/30 transition-colors duration-150 cursor-pointer" onclick="window.location='{{ route('mechanics.show', $mechanic) }}'">
                                    <td class="py-4 px-6">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-sm mr-3">
                                                {{ strtoupper(substr($mechanic->name, 0, 2)) }}
                                            </div>
                                            <a href="{{ route('mechanics.show', $mechanic) }}" class="text-sm font-semibold text-indigo-950 hover:text-indigo-700">{{ $mechanic->name }}</a>
                                        </div>
                                    </td>

                                    <td class="py-4 px-6">
                                        <div class="text-sm text-indigo-700">{{ $mechanic->email }}</div>
                                    </td>

                                    <td class="py-4 px-6">
                                        @if($mechanic->is_active)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                                Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                Inactive
                                            </span>
                                        @endif
                                    </td>

                                    <td class="py-4 px-6">
                                        <div class="text-sm text-indigo-500">{{ $mechanic->created_at->format('M d, Y') }}</div>
                                    </td>

                                    <td class="py-4 px-6">
                                        <div class="flex items-center justify-end space-x-2">
                                            <form method="POST" action="{{ route('mechanics.destroy', $mechanic) }}" class="inline"
                                                onsubmit="return confirm('Are you sure you want to remove this mechanic? This action cannot be undone.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="p-2 text-indigo-300 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors group"
                                                    title="Remove Mechanic">
                                                    <svg class="w-5 h-5 transform group-hover:scale-110 transition-transform"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                        </path>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($mechanics->hasPages())
                    <div class="px-6 py-4 border-t border-indigo-100 bg-indigo-50/30">
                        {{ $mechanics->links() }}
                    </div>
                @endif
            @else
                <div class="text-center py-16">
                    <div class="w-16 h-16 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-indigo-900 mb-2">No mechanics added yet</h3>
                    <p class="text-indigo-500 mb-6">Add your first mechanic to get started.</p>
                    <a href="{{ route('mechanics.create') }}"
                        class="inline-flex items-center justify-center px-5 py-2.5 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-colors shadow-md">
                        Add First Mechanic
                    </a>
                </div>
            @endif
        </x-card>
    </div>
@endsection
