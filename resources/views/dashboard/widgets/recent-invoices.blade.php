@php
    $invoices = $providerData['recent_invoices'] ?? collect();
    $statusColors = [
        'issued' => 'bg-blue-50 text-blue-700 ring-blue-200',
        'paid' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'partial' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'void' => 'bg-gray-100 text-gray-600 ring-gray-200',
        'overdue' => 'bg-rose-50 text-rose-700 ring-rose-200',
    ];
@endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden h-full flex flex-col">
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">Recent Invoices</h3>
        <a href="{{ route('finance.index') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">View all →</a>
    </div>

    @if ($invoices->isEmpty())
        <div class="flex-1 flex items-center justify-center px-4 py-8 text-sm text-gray-500">
            No invoices issued yet.
        </div>
    @else
        <ul class="divide-y divide-gray-100 flex-1">
            @foreach ($invoices as $invoice)
                @php $statusClass = $statusColors[$invoice['status']] ?? $statusColors['void']; @endphp
                <li class="flex items-center justify-between px-4 py-2.5 hover:bg-gray-50">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $invoice['number'] }}</p>
                            <span class="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded ring-1 ring-inset {{ $statusClass }}">{{ $invoice['status'] }}</span>
                        </div>
                        <p class="text-[11px] text-gray-500 mt-0.5 truncate">{{ $invoice['customer'] }} · {{ $invoice['issued_at'] }}</p>
                    </div>
                    <div class="text-right flex-shrink-0 ml-3">
                        <p class="text-sm font-semibold text-gray-900">{{ chf($invoice['total']) }}</p>
                        @if ($invoice['balance'] > 0.01)
                            <p class="text-[11px] text-rose-600">{{ chf($invoice['balance']) }} due</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
