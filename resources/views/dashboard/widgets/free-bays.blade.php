<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-inset ring-gray-200 flex flex-col">
    <div class="p-4 sm:p-5 flex-1">
        <div class="flex items-center gap-3 mb-3">
            <div class="flex-shrink-0 p-2 sm:p-3 rounded-lg bg-teal-50 border border-teal-100">
                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-teal-600" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M13.5 16.875h3.375m0 0h3.375m-3.375 0V13.5m0 3.375v3.375M6 10.5h2.25a2.25 2.25 0 002.25-2.25V6a2.25 2.25 0 00-2.25-2.25H6A2.25 2.25 0 003.75 6v2.25A2.25 2.25 0 006 10.5z" />
                </svg>
            </div>
            <span class="text-xs sm:text-sm font-medium text-gray-500">Free Bays</span>
        </div>
        <div class="flex items-baseline gap-2">
            <span class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $stats['free_bays'] ?? 0 }}</span>
            <span class="text-xs sm:text-sm font-medium text-teal-600">of 6</span>
        </div>
    </div>
    <div class="bg-gray-50 px-4 sm:px-5 py-2 sm:py-3 border-t border-gray-100">
        <a href="{{ route('work-orders.board') }}"
            class="text-xs sm:text-sm font-medium text-teal-600 hover:text-teal-500">View board</a>
    </div>
</div>
