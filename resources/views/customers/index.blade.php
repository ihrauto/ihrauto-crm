@extends('layouts.app')

@section('title', 'Customers')

@section('content')
    <div class="space-y-6">
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
                <h1 class="text-2xl font-bold text-indigo-950 tracking-tight">Customer Management</h1>
                <span
                    class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-xs font-bold border border-indigo-200">
                    {{ $customers->total() }} Total
                </span>
            </div>

            <a href="{{ route('customers.create') }}"
                class="inline-flex items-center justify-center px-5 py-2 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-300 transition-all shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6">
                    </path>
                </svg>
                Add New Customer
            </a>
        </div>

        <!-- Search and Filters -->
        <x-card class="border border-indigo-100 shadow-sm ring-1 ring-indigo-50/50">
            <form method="GET" action="{{ route('customers.index') }}"
                class="flex flex-col sm:flex-row items-end space-y-4 sm:space-y-0 sm:space-x-4">
                <div class="flex-1 w-full">
                    <label class="text-xs font-semibold text-indigo-900 uppercase tracking-wide mb-1.5 block">Search
                        Customers</label>
                    <div class="relative">
                        <input type="text" name="search" value="{{ request('search') }}"
                            placeholder="Search by name, phone, or email..."
                            class="w-full p-2.5 pr-10 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900 placeholder-indigo-300 bg-white">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="flex items-center space-x-2 w-full sm:w-auto">
                    <button type="submit"
                        class="w-full sm:w-auto bg-indigo-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-300 transition-colors shadow-sm">
                        Search
                    </button>

                    @if(request('search'))
                        <a href="{{ route('customers.index') }}"
                            class="w-full sm:w-auto text-center bg-white text-indigo-600 border border-indigo-200 px-6 py-2.5 rounded-lg font-semibold hover:bg-indigo-50 focus:outline-none focus:ring-4 focus:ring-indigo-100 transition-colors">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </x-card>

        <!-- Customers Table -->
        <x-card
            class="border border-indigo-100 shadow-lg shadow-indigo-100/50 overflow-hidden ring-1 ring-indigo-50/50 p-0">
            @if($customers->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-indigo-50/50 border-b border-indigo-100">
                            <tr>
                                <th class="text-left py-4 px-6 text-xs font-bold text-indigo-900 uppercase tracking-wider">
                                    Customer</th>
                                <th class="text-left py-4 px-6 text-xs font-bold text-indigo-900 uppercase tracking-wider">
                                    Contact</th>
                                <th class="text-left py-4 px-6 text-xs font-bold text-indigo-900 uppercase tracking-wider">
                                    Location</th>
                                <th class="text-left py-4 px-6 text-xs font-bold text-indigo-900 uppercase tracking-wider">ID
                                </th>
                                <th class="text-left py-4 px-6 text-xs font-bold text-indigo-900 uppercase tracking-wider">Last
                                    Activity</th>
                                <th class="text-right py-4 px-6 text-xs font-bold text-indigo-900 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-indigo-50">
                            @foreach($customers as $customer)
                                <tr class="hover:bg-indigo-50/30 transition-colors duration-150">
                                    <td class="py-4 px-6">
                                        <div class="flex items-center">
                                            <div
                                                class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-xs mr-3">
                                                {{ substr($customer->name, 0, 2) }}
                                            </div>
                                            <div class="text-sm font-semibold text-indigo-950">
                                                {{ explode(' - ', $customer->name)[0] }}
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-4 px-6">
                                        <div class="text-sm text-indigo-700 font-medium">{{ $customer->phone }}</div>
                                    </td>

                                    <td class="py-4 px-6">
                                        <div class="text-sm text-indigo-600">
                                            @if($customer->city)
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700">
                                                    {{ $customer->city }}
                                                    @if($customer->country), {{ $customer->country }}@endif
                                                </span>
                                            @else
                                                <span class="text-indigo-300">-</span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="py-4 px-6">
                                        <div class="text-sm text-indigo-400 font-mono">#{{ $customer->id }}</div>
                                    </td>

                                    <td class="py-4 px-6">
                                        <div class="text-sm text-indigo-500">
                                            {{ $customer->updated_at->diffForHumans() }}
                                        </div>
                                    </td>

                                    <td class="py-4 px-6">
                                        <div class="flex items-center justify-end space-x-2">
                                            <a href="{{ route('customers.show', $customer) }}"
                                                class="p-2 text-indigo-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors group"
                                                title="View Details">
                                                <svg class="w-5 h-5 transform group-hover:scale-110 transition-transform"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                    </path>
                                                </svg>
                                            </a>

                                            <a href="{{ route('customers.edit', $customer) }}"
                                                class="p-2 text-indigo-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors group"
                                                title="Edit Customer">
                                                <svg class="w-5 h-5 transform group-hover:scale-110 transition-transform"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                                    </path>
                                                </svg>
                                            </a>

                                            <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="inline"
                                                onsubmit="return confirm('Are you sure you want to delete this customer? This action cannot be undone.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="p-2 text-indigo-300 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors group"
                                                    title="Delete Customer">
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
                @if($customers->hasPages())
                    <div class="px-6 py-4 border-t border-indigo-100 bg-indigo-50/30">
                        {{ $customers->links() }}
                    </div>
                @endif
            @else
                <div class="text-center py-16">
                    <div class="w-16 h-16 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-indigo-900 mb-2">No customers found</h3>
                    <p class="text-indigo-500 mb-6">
                        @if(request('search'))
                            We couldn't find any customers matching "{{ request('search') }}".
                        @else
                            Get started by adding your first customer to the system.
                        @endif
                    </p>
                    @if(request('search'))
                        <a href="{{ route('customers.index') }}"
                            class="text-indigo-600 font-semibold hover:text-indigo-800 underline decoration-2 underline-offset-4 decoration-indigo-200 hover:decoration-indigo-500 transition-all">
                            Clear search filters
                        </a>
                    @else
                        <a href="{{ route('customers.create') }}"
                            class="inline-flex items-center justify-center px-5 py-2.5 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-colors shadow-md">
                            Add First Customer
                        </a>
                    @endif
                </div>
            @endif
        </x-card>
    </div>
@endsection