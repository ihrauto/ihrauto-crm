<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-indigo-100 flex flex-col">
    <div class="p-4 sm:p-5 flex-1">
        <div class="flex items-center gap-3 mb-3">
            <div class="flex-shrink-0 p-2 sm:p-3 rounded-lg bg-green-50 border border-green-100">
                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-green-600" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z" />
                </svg>
            </div>
            <span class="text-xs sm:text-sm font-medium text-indigo-500">Active Jobs</span>
        </div>
        <div class="flex items-baseline gap-2">
            <span class="text-2xl sm:text-3xl font-bold text-indigo-950">{{ $stats['active_jobs'] ?? 0 }}</span>
            <span class="text-xs sm:text-sm font-medium text-green-600">in progress</span>
        </div>
    </div>
    <div class="bg-green-50/50 px-4 sm:px-5 py-2 sm:py-3 border-t border-green-100">
        <a href="{{ route('work-orders.index') }}"
            class="text-xs sm:text-sm font-medium text-green-700 hover:text-green-800">View all jobs</a>
    </div>
</div>
