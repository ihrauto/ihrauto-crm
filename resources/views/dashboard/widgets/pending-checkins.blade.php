<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-inset ring-gray-200 flex flex-col">
    <div class="p-4 sm:p-5 flex-1">
        <div class="flex items-center gap-3 mb-3">
            <div class="flex-shrink-0 p-2 sm:p-3 rounded-lg bg-yellow-50 border border-yellow-100">
                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <span class="text-xs sm:text-sm font-medium text-gray-500">Pending Check-ins</span>
        </div>
        <div class="flex items-baseline gap-2">
            <span class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $stats['pending_checkins'] ?? 0 }}</span>
            <span class="text-xs sm:text-sm font-medium text-yellow-600">awaiting</span>
        </div>
    </div>
    <div class="bg-gray-50 px-4 sm:px-5 py-2 sm:py-3 border-t border-gray-100">
        <a href="{{ route('checkin') }}" class="text-xs sm:text-sm font-medium text-yellow-700 hover:text-yellow-800">Process check-ins</a>
    </div>
</div>
