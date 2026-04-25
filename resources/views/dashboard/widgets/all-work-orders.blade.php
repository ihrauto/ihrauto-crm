<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-indigo-100 flex flex-col">
    <div class="p-4 sm:p-5 flex-1">
        <div class="flex items-center gap-3 mb-3">
            <div class="flex-shrink-0 p-2 sm:p-3 rounded-lg bg-indigo-50 border border-indigo-100">
                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-indigo-600" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                </svg>
            </div>
            <span class="text-xs sm:text-sm font-medium text-indigo-500">All Jobs</span>
        </div>
        <div class="flex items-baseline gap-2">
            <span class="text-2xl sm:text-3xl font-bold text-indigo-950">{{ $stats['all_work_orders'] ?? 0 }}</span>
            <span class="text-xs sm:text-sm font-medium text-indigo-500">total</span>
        </div>
    </div>
    <div class="bg-indigo-50/50 px-4 sm:px-5 py-2 sm:py-3 border-t border-indigo-100">
        <a href="{{ route('work-orders.index') }}"
            class="text-xs sm:text-sm font-medium text-indigo-600 hover:text-indigo-700">View all</a>
    </div>
</div>
