<div class="bg-white rounded-xl shadow-sm border border-indigo-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-indigo-100">
        <h3 class="text-lg font-semibold text-indigo-950">Technician Status</h3>
    </div>
    <ul role="list" class="divide-y divide-gray-100">
        @foreach($technician_status ?? [] as $tech)
            <li class="flex flex-col gap-y-2 py-4 px-6 hover:bg-gray-50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-x-3">
                        <div
                            class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-xs">
                            {{ substr($tech['name'], 0, 2) }}
                        </div>
                        <h3 class="text-sm font-semibold leading-6 text-gray-900">{{ $tech['name'] }}</h3>
                    </div>
                    <span
                        class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $tech['status'] === 'busy' ? 'bg-red-50 text-red-700 ring-red-600/20' : 'bg-green-50 text-green-700 ring-green-600/20' }}">
                        {{ ucfirst($tech['status']) }}
                    </span>
                </div>
                @if($tech['status'] === 'busy' && $tech['current_job'])
                    <div class="ml-11 text-xs text-gray-500">
                        <p>Working on: <span class="font-medium text-gray-900">{{ $tech['current_job']['vehicle'] }}</span>
                        </p>
                        <p class="mt-1">Bay {{ $tech['current_job']['bay'] }} • Started
                            {{ $tech['current_job']['started_at'] }} ({{ $tech['current_job']['duration'] }})
                        </p>
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
</div>
