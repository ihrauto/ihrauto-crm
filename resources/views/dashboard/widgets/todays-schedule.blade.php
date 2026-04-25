<div class="bg-white rounded-xl shadow-sm border border-indigo-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-indigo-100">
        <h3 class="text-lg font-semibold text-indigo-950">Today's Schedule</h3>
    </div>
    <ul role="list" class="divide-y divide-gray-100 schedule-scroll">
        @forelse($todays_schedule ?? [] as $job)
            <li class="flex items-center justify-between gap-x-6 py-5 px-6 hover:bg-gray-50">
                <div class="min-w-0">
                    <div class="flex items-start gap-x-3">
                        <p class="text-sm font-semibold leading-6 text-gray-900">{{ $job['time'] }} -
                            {{ $job['customer'] }}
                        </p>
                        <p
                            class="rounded-md whitespace-nowrap mt-0.5 px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $job['status_color'] }}">
                            {{ $job['status_label'] }}
                        </p>
                    </div>
                    <div class="mt-1 flex items-center gap-x-2 text-xs leading-5 text-gray-500">
                        <p class="truncate">{{ $job['vehicle'] }}</p>
                        <svg viewBox="0 0 2 2" class="h-0.5 w-0.5 fill-current">
                            <circle cx="1" cy="1" r="1" />
                        </svg>
                        <p class="whitespace-nowrap">Bay {{ $job['bay'] }}</p>
                        <svg viewBox="0 0 2 2" class="h-0.5 w-0.5 fill-current">
                            <circle cx="1" cy="1" r="1" />
                        </svg>
                        <p class="truncate">Tech: {{ $job['technician'] }}</p>
                    </div>
                </div>
                <div class="flex flex-none items-center gap-x-4">
                    <a href="{{ route('work-orders.show', $job['id']) }}"
                        class="hidden rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:block">View
                        job<span class="sr-only">, {{ $job['customer'] }}</span></a>
                </div>
            </li>
        @empty
            <li class="px-6 py-8 text-center text-sm text-gray-500">
                No jobs scheduled for today.
                <a href="{{ route('work-orders.create') }}"
                    class="text-indigo-600 hover:text-indigo-500 font-medium">Schedule one now</a>
            </li>
        @endforelse
    </ul>
</div>
