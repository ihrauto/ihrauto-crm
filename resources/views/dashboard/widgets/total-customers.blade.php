@php
    $growth = (float) ($stats['customer_growth'] ?? 0);
    $growthClass = $growth >= 0 ? 'text-emerald-600' : 'text-red-600';
    $growthSymbol = $growth >= 0 ? '+' : '';
@endphp
<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-inset ring-gray-200 flex flex-col">
    <div class="p-4 sm:p-5 flex-1">
        <div class="flex items-center gap-3 mb-3">
            <div class="flex-shrink-0 p-2 sm:p-3 rounded-lg bg-cyan-50 border border-cyan-100">
                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                </svg>
            </div>
            <span class="text-xs sm:text-sm font-medium text-gray-500">Total Customers</span>
        </div>
        <div class="flex items-baseline gap-2 flex-wrap">
            <span class="text-2xl sm:text-3xl font-bold text-gray-900">{{ number_format((int) ($stats['total_customers'] ?? 0)) }}</span>
            <span class="text-xs sm:text-sm font-medium {{ $growthClass }}">{{ $growthSymbol }}{{ $growth }}% MoM</span>
        </div>
    </div>
    <div class="bg-gray-50 px-4 sm:px-5 py-2 sm:py-3 border-t border-gray-100">
        <a href="{{ route('customers.index') }}" class="text-xs sm:text-sm font-medium text-cyan-700 hover:text-cyan-800">View all customers</a>
    </div>
</div>
