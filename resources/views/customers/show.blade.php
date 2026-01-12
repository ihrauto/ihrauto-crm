@extends('layouts.app')

@section('title', 'Customer Details')

@section('content')
<div class="space-y-8">
    <!-- Breadcrumbs -->
    <nav class="text-sm font-medium text-indigo-900/60">
        <a href="{{ route('customers.index') }}" class="hover:text-indigo-600 transition-colors">Customers</a>
        <span class="mx-2 text-indigo-300">/</span>
        <span class="text-indigo-600 font-bold">{{ $customer->name }}</span>
    </nav>

    <!-- Professional Header -->
    <div class="bg-white rounded-xl shadow-sm border border-indigo-100 p-8">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center space-x-6 w-full md:w-auto">
                <a href="{{ route('customers.index') }}" 
                   class="p-2 rounded-lg text-indigo-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                
                <div class="flex flex-col sm:flex-row items-center sm:space-x-6 text-center sm:text-left">
                    <div class="w-20 h-20 bg-gradient-to-br from-indigo-600 to-purple-600 text-white rounded-2xl flex items-center justify-center text-3xl font-bold shadow-lg mb-4 sm:mb-0">
                        {{ strtoupper(substr($customer->name, 0, 1)) }}
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-indigo-950 tracking-tight">{{ $customer->name }}</h1>
                        <p class="text-indigo-500 font-mono mt-1">Customer ID: #{{ sprintf('%04d', $customer->id) }}</p>
                        <div class="flex flex-wrap justify-center sm:justify-start items-center gap-3 mt-3">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 shadow-sm">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Active Customer
                            </span>
                            <span class="text-sm font-medium text-indigo-400">Member since {{ $customer->created_at->format('F Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center space-x-3 w-full md:w-auto justify-center md:justify-end">
                <a href="{{ route('customers.edit', $customer) }}" 
                   class="bg-indigo-600 text-white px-6 py-3 rounded-lg font-bold shadow-md hover:bg-indigo-700 hover:shadow-lg transition-all transform hover:-translate-y-0.5 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit Customer
                </a>
                
                <div class="relative">
                    <button type="button" 
                            onclick="document.getElementById('deleteModal').classList.remove('hidden')"
                            class="bg-white text-red-600 px-4 py-3 rounded-lg font-medium hover:bg-red-50 transition-colors border border-red-200 shadow-sm hover:border-red-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-indigo-100 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-indigo-400 uppercase tracking-wide">Vehicles</p>
                    <p class="text-3xl font-extrabold text-indigo-950 mt-2">{{ $customer->vehicles->count() }}</p>
                </div>
                <div class="w-12 h-12 bg-indigo-50 rounded-lg flex items-center justify-center border border-indigo-100">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 6h4l3 6v5h-2m-4 0H9m-4 0H3v-5l3-6h4z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-indigo-100 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-emerald-500 uppercase tracking-wide">Check-ins</p>
                    <p class="text-3xl font-extrabold text-indigo-950 mt-2">{{ $customer->checkins->count() }}</p>
                </div>
                <div class="w-12 h-12 bg-emerald-50 rounded-lg flex items-center justify-center border border-emerald-100">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-indigo-100 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-amber-500 uppercase tracking-wide">Stored Tires</p>
                    <p class="text-3xl font-extrabold text-indigo-950 mt-2">{{ $customer->tires->count() }}</p>
                </div>
                <div class="w-12 h-12 bg-amber-50 rounded-lg flex items-center justify-center border border-amber-100">
                    <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-indigo-100 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-purple-500 uppercase tracking-wide">Last Visit</p>
                    <p class="text-lg font-bold text-indigo-950 mt-2">{{ $customer->updated_at->diffForHumans() }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center border border-purple-100">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Customer Information -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Contact Information -->
            <div class="bg-white rounded-xl shadow-sm border border-indigo-100 overflow-hidden">
                <div class="px-8 py-6 border-b border-indigo-50 bg-indigo-50/10">
                    <h2 class="text-lg font-bold text-indigo-950">Contact Information</h2>
                </div>
                <div class="p-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="text-xs font-bold text-indigo-400 uppercase tracking-wide">Full Name</label>
                            <p class="text-lg font-semibold text-indigo-950 mt-1">{{ $customer->name }}</p>
                        </div>
                        
                        <div>
                            <label class="text-xs font-bold text-indigo-400 uppercase tracking-wide">Phone Number</label>
                            <p class="text-lg font-semibold text-indigo-950 mt-1">{{ $customer->phone }}</p>
                        </div>
                        
                        @if($customer->email)
                            <div>
                                <label class="text-xs font-bold text-indigo-400 uppercase tracking-wide">Email Address</label>
                                <p class="text-lg font-semibold text-indigo-950 mt-1">{{ $customer->email }}</p>
                            </div>
                        @endif
                        
                        @if($customer->date_of_birth)
                            <div>
                                <label class="text-xs font-bold text-indigo-400 uppercase tracking-wide">Date of Birth</label>
                                <p class="text-lg font-semibold text-indigo-950 mt-1">{{ \Carbon\Carbon::parse($customer->date_of_birth)->format('F d, Y') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Address Information -->
            @if($customer->address || $customer->city || $customer->postal_code || $customer->country)
                <div class="bg-white rounded-xl shadow-sm border border-indigo-100 overflow-hidden">
                    <div class="px-8 py-6 border-b border-indigo-50 bg-indigo-50/10">
                        <h2 class="text-lg font-bold text-indigo-950">Address Information</h2>
                    </div>
                    <div class="p-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            @if($customer->address)
                                <div class="md:col-span-2">
                                    <label class="text-xs font-bold text-indigo-400 uppercase tracking-wide">Street Address</label>
                                    <p class="text-lg font-semibold text-indigo-950 mt-1">{{ $customer->address }}</p>
                                </div>
                            @endif
                            
                            @if($customer->city)
                                <div>
                                    <label class="text-xs font-bold text-indigo-400 uppercase tracking-wide">City</label>
                                    <p class="text-lg font-semibold text-indigo-950 mt-1">{{ $customer->city }}</p>
                                </div>
                            @endif
                            
                            @if($customer->postal_code)
                                <div>
                                    <label class="text-xs font-bold text-indigo-400 uppercase tracking-wide">Postal Code</label>
                                    <p class="text-lg font-semibold text-indigo-950 mt-1">{{ $customer->postal_code }}</p>
                                </div>
                            @endif
                            
                            @if($customer->country)
                                <div>
                                    <label class="text-xs font-bold text-indigo-400 uppercase tracking-wide">Country</label>
                                    <p class="text-lg font-semibold text-indigo-950 mt-1">{{ $customer->country }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- Billing History -->
            <div class="bg-white rounded-xl shadow-sm border border-indigo-100 overflow-hidden">
                <div class="px-8 py-6 border-b border-indigo-50 bg-indigo-50/10 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-indigo-950">Billing History</h2>
                    <a href="{{ route('finance.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Go to Finance &rarr;</a>
                </div>
                <div class="p-8 space-y-8">
                    
                    <!-- Invoices -->
                    <div>
                        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-4">Invoices</h3>
                        @if($customer->invoices->count() > 0)
                            <div class="overflow-hidden rounded-lg border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Number</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($customer->invoices->sortByDesc('created_at')->take(5) as $invoice)
                                            <tr>
                                                <td class="px-4 py-2 text-sm font-medium text-indigo-600">{{ $invoice->invoice_number }}</td>
                                                <td class="px-4 py-2 text-sm text-gray-500">{{ $invoice->issue_date->format('M d, Y') }}</td>
                                                <td class="px-4 py-2 text-sm font-bold text-gray-900">CHF {{ number_format($invoice->total, 2) }}</td>
                                                <td class="px-4 py-2 text-sm">
                                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $invoice->status === 'paid' ? 'bg-green-50 text-green-700 ring-green-600/20' : ($invoice->status === 'overdue' ? 'bg-red-50 text-red-700 ring-red-600/20' : 'bg-yellow-50 text-yellow-800 ring-yellow-600/20') }}">
                                                        {{ ucfirst($invoice->status) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-gray-400 italic">No invoices found.</p>
                        @endif
                    </div>

                    <!-- Quotes -->
                    <div>
                        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-4">Recent Quotes</h3>
                        @if($customer->quotes->count() > 0)
                            <div class="overflow-hidden rounded-lg border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Number</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($customer->quotes->sortByDesc('created_at')->take(5) as $quote)
                                            <tr>
                                                <td class="px-4 py-2 text-sm font-medium text-indigo-600">{{ $quote->quote_number }}</td>
                                                <td class="px-4 py-2 text-sm text-gray-500">{{ $quote->issue_date->format('M d, Y') }}</td>
                                                <td class="px-4 py-2 text-sm font-bold text-gray-900">CHF {{ number_format($quote->total, 2) }}</td>
                                                <td class="px-4 py-2 text-sm uppercase text-gray-500">{{ $quote->status }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-sm text-gray-400 italic">No quotes found.</p>
                        @endif
                    </div>

                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="space-y-8">
            <!-- Vehicles -->
            <div class="bg-white rounded-xl shadow-sm border border-indigo-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-indigo-50 bg-indigo-50/10">
                    <h3 class="text-lg font-bold text-indigo-950">Registered Vehicles</h3>
                </div>
                <div class="p-6">
                    @if($customer->vehicles->count() > 0)
                        <div class="space-y-4">
                            @foreach($customer->vehicles as $vehicle)
                                <div class="flex items-center space-x-4 p-4 bg-indigo-50 rounded-lg border border-indigo-100">
                                    <div class="w-12 h-12 bg-white rounded-lg flex items-center justify-center shadow-sm">
                                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 6h4l3 6v5h-2m-4 0H9m-4 0H3v-5l3-6h4z"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="font-bold text-indigo-950">{{ $vehicle->make }} {{ $vehicle->model }}</h4>
                                        <p class="text-sm font-medium text-indigo-600">{{ $vehicle->year }} • {{ $vehicle->license_plate }}</p>
                                        @if($vehicle->color)
                                            <p class="text-xs text-indigo-400 mt-1 uppercase tracking-wide">{{ $vehicle->color }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <div class="w-12 h-12 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 6h4l3 6v5h-2m-4 0H9m-4 0H3v-5l3-6h4z"></path>
                                </svg>
                            </div>
                            <p class="text-indigo-400 font-medium">No vehicles registered</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-xl shadow-sm border border-indigo-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-indigo-50 bg-indigo-50/10">
                    <h3 class="text-lg font-bold text-indigo-950">Recent Activity</h3>
                </div>
                <div class="p-6">
                    @if($customer->checkins->count() > 0)
                        <div class="space-y-4">
                            @foreach($customer->checkins->take(5) as $checkin)
                                <div class="flex items-center space-x-4">
                                    <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="font-bold text-sm text-indigo-950">{{ ucfirst(str_replace('_', ' ', $checkin->service_type)) }}</h4>
                                        <p class="text-xs text-indigo-600">{{ $checkin->vehicle->make }} {{ $checkin->vehicle->model }}</p>
                                        <p class="text-[10px] text-indigo-400 uppercase tracking-wide mt-0.5">{{ $checkin->created_at->diffForHumans() }}</p>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border 
                                        @if($checkin->status == 'completed') bg-emerald-50 text-emerald-700 border-emerald-100 
                                        @elseif($checkin->status == 'in_progress') bg-amber-50 text-amber-700 border-amber-100 
                                        @else bg-gray-50 text-gray-700 border-gray-100 @endif">
                                        {{ ucfirst($checkin->status) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <div class="w-12 h-12 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <p class="text-indigo-400 font-medium">No recent activity</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-indigo-900/50 backdrop-blur-sm hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-2xl p-8 max-w-md w-full mx-4 transform transition-all scale-100">
        <div class="flex items-center justify-center w-16 h-16 mx-auto bg-red-50 rounded-full mb-6 ring-4 ring-red-50">
            <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
        </div>
        <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Delete Customer</h3>
        <p class="text-gray-500 text-center mb-8">Are you sure you want to delete <span class="font-bold text-gray-800">{{ $customer->name }}</span>? This action cannot be undone and will remove all associated data.</p>
        <div class="flex space-x-4">
            <button type="button" 
                    onclick="document.getElementById('deleteModal').classList.add('hidden')"
                    class="flex-1 px-4 py-3 border border-gray-200 rounded-xl text-gray-700 hover:bg-gray-50 font-bold transition-colors">
                Cancel
            </button>
            <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="flex-1">
                @csrf
                @method('DELETE')
                <button type="submit" 
                        class="w-full px-4 py-3 bg-red-600 text-white rounded-xl hover:bg-red-700 font-bold shadow-lg shadow-red-500/30 transition-all transform hover:-translate-y-0.5">
                    Delete Customer
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Close modal when clicking outside
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        this.classList.add('hidden');
    }
});
</script>
@endsection 