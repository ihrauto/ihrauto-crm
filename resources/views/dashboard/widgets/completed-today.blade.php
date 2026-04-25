<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-indigo-100 flex flex-col">
    <div class="p-4 sm:p-5 flex-1">
        <div class="flex items-center gap-3 mb-3">
            <div class="flex-shrink-0 p-2 sm:p-3 rounded-lg bg-indigo-50 border border-indigo-100">
                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-indigo-600" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <span class="text-xs sm:text-sm font-medium text-indigo-500">Completed</span>
        </div>
        <div class="flex items-baseline gap-2">
            <span class="text-2xl sm:text-3xl font-bold text-indigo-950">{{ $stats['completed_today'] ?? 0 }}</span>
            <span class="text-xs sm:text-sm font-medium text-indigo-400">today</span>
        </div>
    </div>
    <div class="bg-indigo-50/50 px-4 sm:px-5 py-2 sm:py-3 border-t border-indigo-100">
        <a href="{{ route('finance.index') }}"
            class="text-xs sm:text-sm font-medium text-indigo-900 hover:text-indigo-700">View invoices</a>
    </div>
</div>
