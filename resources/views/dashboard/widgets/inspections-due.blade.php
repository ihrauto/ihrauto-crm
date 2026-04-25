@php $vehicles = $providerData['inspections_due'] ?? collect(); @endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden h-full flex flex-col">
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Inspections Due</h3>
            <p class="text-[11px] text-gray-500 mt-0.5">Next 30 days · TÜV / MFK / §57a</p>
        </div>
        <a href="{{ route('customers.index') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">All →</a>
    </div>

    @if ($vehicles->isEmpty())
        <div class="flex-1 flex items-center justify-center px-4 py-8 text-sm text-gray-500">
            No inspections due in the next 30 days.
        </div>
    @else
        <ul class="divide-y divide-gray-100 flex-1">
            @foreach ($vehicles as $v)
                @php
                    $daysOut = (int) $v['days_out'];
                    $tone = $daysOut <= 3 ? 'rose' : ($daysOut <= 14 ? 'amber' : 'gray');
                    $toneClasses = [
                        'rose' => 'bg-rose-50 text-rose-700 ring-rose-200',
                        'amber' => 'bg-amber-50 text-amber-700 ring-amber-200',
                        'gray' => 'bg-gray-50 text-gray-600 ring-gray-200',
                    ];
                @endphp
                <li class="flex items-center justify-between px-4 py-2.5 hover:bg-gray-50">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-900 truncate">{{ $v['plate'] ?? $v['make_model'] }}</span>
                            <span class="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded ring-1 ring-inset {{ $toneClasses[$tone] }}">
                                {{ $daysOut === 0 ? 'today' : "in {$daysOut}d" }}
                            </span>
                        </div>
                        <p class="text-[11px] text-gray-500 mt-0.5 truncate">
                            {{ $v['customer_name'] ?? '—' }} · {{ $v['authority'] }} · due {{ $v['due_date'] }}
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
