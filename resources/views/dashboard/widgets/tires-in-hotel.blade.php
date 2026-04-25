<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-inset ring-gray-200 flex flex-col">
    <div class="p-4 sm:p-5 flex-1">
        <div class="flex items-center gap-3 mb-3">
            <div class="flex-shrink-0 p-2 sm:p-3 rounded-lg bg-purple-50 border border-purple-100">
                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                </svg>
            </div>
            <span class="text-xs sm:text-sm font-medium text-gray-500">Tires Stored</span>
        </div>
        <div class="flex items-baseline gap-2">
            <span class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $stats['tires_in_hotel'] ?? 0 }}</span>
            <span class="text-xs sm:text-sm font-medium text-purple-600">in hotel</span>
        </div>
    </div>
    <div class="bg-gray-50 px-4 sm:px-5 py-2 sm:py-3 border-t border-gray-100">
        <a href="{{ route('tires-hotel') }}" class="text-xs sm:text-sm font-medium text-purple-700 hover:text-purple-800">Open tire hotel</a>
    </div>
</div>
