@php $customers = $providerData['recent_customers'] ?? collect(); @endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden h-full flex flex-col">
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">Recent Customers</h3>
        <a href="{{ route('customers.index') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">View all →</a>
    </div>

    @if ($customers->isEmpty())
        <div class="flex-1 flex items-center justify-center px-4 py-8 text-sm text-gray-500">
            No customers yet.
        </div>
    @else
        <ul class="divide-y divide-gray-100 flex-1">
            @foreach ($customers as $customer)
                <li class="flex items-center justify-between px-4 py-2.5 hover:bg-gray-50">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="h-7 w-7 flex-shrink-0 rounded-full bg-cyan-100 flex items-center justify-center text-cyan-700 font-bold text-xs">
                            {{ strtoupper(substr($customer['name'] ?? '?', 0, 2)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $customer['name'] ?? '—' }}</p>
                            <p class="text-[11px] text-gray-500 truncate">{{ ($customer['email'] ?? '') ?: ($customer['phone'] ?? '—') }}</p>
                        </div>
                    </div>
                    <a href="{{ route('customers.show', $customer['id']) }}" class="text-[11px] font-medium text-indigo-600 hover:text-indigo-800 flex-shrink-0 ml-2">Open</a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
