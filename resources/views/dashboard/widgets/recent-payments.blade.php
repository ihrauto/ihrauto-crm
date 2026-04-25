@php $payments = $providerData['recent_payments'] ?? collect(); @endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden h-full flex flex-col">
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">Recent Payments</h3>
        <a href="{{ route('finance.index') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">View all →</a>
    </div>

    @if ($payments->isEmpty())
        <div class="flex-1 flex items-center justify-center px-4 py-8 text-sm text-gray-500">
            No payments received yet.
        </div>
    @else
        <ul class="divide-y divide-gray-100 flex-1">
            @foreach ($payments as $payment)
                <li class="flex items-center justify-between px-4 py-2.5 hover:bg-gray-50">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $payment['customer'] }}</p>
                        <p class="text-[11px] text-gray-500 mt-0.5">{{ ucfirst(str_replace('_', ' ', $payment['method'])) }} · {{ $payment['date'] }}</p>
                    </div>
                    <span class="text-sm font-semibold text-emerald-700 flex-shrink-0 ml-3">{{ chf($payment['amount']) }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
