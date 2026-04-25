@php
    $growth = (float) ($stats['revenue_growth'] ?? 0);
    $growthClass = $growth >= 0 ? 'text-emerald-600' : 'text-red-600';
    $growthSymbol = $growth >= 0 ? '+' : '';
@endphp
<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-emerald-100 flex flex-col">
    <div class="p-4 sm:p-5 flex-1">
        <div class="flex items-center gap-3 mb-3">
            <div class="flex-shrink-0 p-2 sm:p-3 rounded-lg bg-emerald-50 border border-emerald-100">
                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.306a11.95 11.95 0 015.814-5.518l2.74-1.22m0 0l-5.94-2.281m5.94 2.28l-2.28 5.941" />
                </svg>
            </div>
            <span class="text-xs sm:text-sm font-medium text-emerald-700">Monthly Revenue</span>
        </div>
        <div class="flex items-baseline gap-2 flex-wrap">
            <span class="text-2xl sm:text-3xl font-bold text-emerald-900">{{ chf($stats['monthly_revenue'] ?? 0) }}</span>
            <span class="text-xs sm:text-sm font-medium {{ $growthClass }}">{{ $growthSymbol }}{{ $growth }}% vs. last</span>
        </div>
    </div>
    <div class="bg-emerald-50/50 px-4 sm:px-5 py-2 sm:py-3 border-t border-emerald-100">
        <a href="{{ route('finance.index') }}" class="text-xs sm:text-sm font-medium text-emerald-700 hover:text-emerald-900">View finance</a>
    </div>
</div>
