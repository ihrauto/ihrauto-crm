@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <nav class="flex text-sm font-medium text-indigo-500 mb-2">
                    <a href="{{ route('dashboard') }}" class="hover:text-indigo-700">Dashboard</a>
                    <span class="mx-2">/</span>
                    <a href="{{ route('work-orders.index') }}" class="hover:text-indigo-700">Work Orders</a>
                    <span class="mx-2">/</span>
                    <span class="text-indigo-900">WO #{{ $workOrder->id }}</span>
                </nav>
                <h1 class="text-2xl font-bold text-indigo-900">Work Order #{{ $workOrder->id }}</h1>
                <p class="text-sm text-indigo-500">Created {{ $workOrder->created_at->format('M d, Y H:i') }} • Assigned to
                    {{ $workOrder->technician ? $workOrder->technician->name : 'Unassigned' }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <span class="px-4 py-2 rounded-full text-sm font-bold {{ $workOrder->status_badge_color }}">
                    {{ $workOrder->status_label }}
                </span>

                <!-- Status Actions -->
                <form action="{{ route('work-orders.update', $workOrder) }}" method="POST">
                    @csrf
                    @method('PUT')

                    @if($workOrder->status === 'created')
                        <input type="hidden" name="status" value="in_progress">
                        <button type="submit"
                            class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium shadow-sm transition-colors">
                            Start Job
                        </button>
                    @elseif($workOrder->status === 'in_progress')
                        {{-- Mark Completed button triggers the main form submission via JS --}}
                        <button type="button" onclick="submitCompletion()"
                            class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium shadow-sm transition-colors">
                            Mark Completed
                        </button>
                    @endif
                </form>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-50 text-green-700 p-4 rounded-lg border border-green-200">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 text-red-700 p-4 rounded-lg border border-red-200">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- LEFT COLUMN: Scope & Context -->
            <div class="space-y-6 lg:col-span-1">

                <!-- Customer Card -->
                <div class="bg-white rounded-xl shadow-sm border border-indigo-50 p-6">
                    <h3 class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-4">Customer & Vehicle</h3>
                    <div class="flex items-start gap-4 mb-4">
                        <div
                            class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-lg">
                            {{ substr($workOrder->customer->name ?? '?', 0, 1) }}
                        </div>
                        <div>
                            <h4 class="font-bold text-indigo-900">{{ $workOrder->customer->name ?? 'Unknown Customer' }}
                            </h4>
                            <p class="text-sm text-indigo-500">{{ $workOrder->customer->phone ?? 'No Phone' }}</p>
                        </div>
                    </div>
                    <div class="border-t border-indigo-50 pt-4">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm text-indigo-500 font-medium">{{ $workOrder->vehicle->make ?? 'Unknown' }}
                                {{ $workOrder->vehicle->model ?? 'Vehicle' }}</span>
                            <span
                                class="text-xs bg-indigo-50 text-indigo-700 px-2 py-1 rounded font-mono">{{ $workOrder->vehicle->license_plate ?? 'No Plate' }}</span>
                        </div>
                        <p class="text-xs text-indigo-400">VIN: {{ $workOrder->vehicle->vin ?? 'N/A' }}</p>
                    </div>
                </div>

                <!-- Job Description Card -->
                <div class="bg-white rounded-xl shadow-sm border border-indigo-50 p-6">
                    <h3 class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-4">Job Description</h3>
                    <div class="bg-gray-50 text-gray-700 p-4 rounded-lg text-sm leading-relaxed border border-gray-100">
                        {{ $workOrder->customer_issues ?: 'No specific description provided.' }}
                    </div>
                </div>

                <!-- Scope Checklist moved to Job Sheet -->

                <!-- Billing Status -->
                @if($workOrder->invoice)
                    <div class="bg-white rounded-xl shadow-sm border border-indigo-50 p-6">
                        <h3 class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-4">Billing</h3>

                        <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg border border-green-100 mb-3">
                            <div>
                                <p class="text-xs text-green-600 font-bold uppercase">Invoiced</p>
                                <p class="text-sm font-bold text-green-900">{{ $workOrder->invoice->invoice_number }}</p>
                            </div>
                            <span
                                class="px-2 py-1 bg-green-200 text-green-800 text-xs rounded-full font-bold uppercase">{{ $workOrder->invoice->status }}</span>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('finance.index', ['tab' => 'invoices']) }}"
                                class="flex-1 text-center px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 shadow-sm">
                                View List
                            </a>
                        </div>
                    </div>
                @endif

            </div>

            <!-- RIGHT COLUMN: Execution -->
            <div class="lg:col-span-2">
                <form action="{{ route('work-orders.update', $workOrder) }}" method="POST" id="execution-form">
                    @csrf
                    @method('PUT')

                    <div class="bg-white rounded-xl shadow-sm border border-indigo-50 overflow-hidden">
                        <div class="border-b border-indigo-50 px-6 py-4 flex items-center justify-between">
                            <h2 class="font-bold text-indigo-900">Job Sheet / Execution Log</h2>
                            <button type="submit"
                                class="text-sm bg-indigo-50 text-indigo-700 px-4 py-2 rounded-lg font-medium hover:bg-indigo-100 transition-colors">
                                Save Changes
                            </button>
                        </div>

                        <div class="p-6 space-y-8">

                            <!-- Technician Notes -->
                            <div>
                                <label class="block text-sm font-medium text-indigo-900 mb-2">Technician Notes &
                                    Observations</label>
                                <textarea name="technician_notes" rows="4"
                                    class="w-full rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                    placeholder="Record measurements, observations, and work details here...">{{ $workOrder->technician_notes }}</textarea>
                            </div>



                        </div>
                    </div>
                </form>

                <!-- Metadata Footer -->
                <div class="mt-6 grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Checkin Time -->
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-indigo-50">
                        <span class="block text-xs text-gray-400 uppercase">Check-in</span>
                        <span
                            class="block text-sm font-bold text-gray-700">{{ $workOrder->checkin ? $workOrder->checkin->checkin_time->format('H:i') : '--:--' }}</span>
                        <span
                            class="block text-xs text-gray-400">{{ $workOrder->checkin ? $workOrder->checkin->checkin_time->format('M d') : '-' }}</span>
                    </div>

                    <!-- Started Time -->
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-indigo-50">
                        <span class="block text-xs text-gray-400 uppercase">Started</span>
                        <span
                            class="block text-sm font-bold text-indigo-700">{{ $workOrder->started_at ? $workOrder->started_at->format('H:i') : '--:--' }}</span>
                        <span
                            class="block text-xs text-gray-400">{{ $workOrder->started_at ? $workOrder->started_at->format('M d') : '-' }}</span>
                    </div>

                    <!-- Completed Time -->
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-indigo-50">
                        <span class="block text-xs text-gray-400 uppercase">Completed</span>
                        <span
                            class="block text-sm font-bold text-green-700">{{ $workOrder->completed_at ? $workOrder->completed_at->format('H:i') : '--:--' }}</span>
                        <span
                            class="block text-xs text-gray-400">{{ $workOrder->completed_at ? $workOrder->completed_at->format('M d') : '-' }}</span>
                    </div>

                    <!-- Duration -->
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-indigo-50">
                        <span class="block text-xs text-gray-400 uppercase">Total Duration</span>
                        <span class="block text-sm font-bold text-gray-900">
                            @if($workOrder->started_at && $workOrder->completed_at)
                                {{ $workOrder->started_at->diff($workOrder->completed_at)->format('%Hh %Im') }}
                            @else
                                -
                            @endif
                        </span>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        function submitCompletion() {
            const form = document.getElementById('execution-form');
            if (!form) return;

            // Add hidden status input
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'status';
            input.value = 'completed';
            form.appendChild(input);

            form.submit();
        }

        function addServiceRow() {
            const tbody = document.getElementById('services-table-body');
            const noMsg = document.getElementById('no-services-msg');
            if (noMsg) noMsg.style.display = 'none';

            const index = Date.now();
            const row = `
                                         <tr>
                                            <td class="p-2">
                                                <input type="text" name="service_tasks[${index}][name]"
                                                    class="w-full border-0 bg-transparent focus:ring-0 p-2 text-sm font-medium"
                                                    placeholder="Service Name" required>
                                            </td>
                                            <td class="p-2">
                                                <input type="number" step="0.01" name="service_tasks[${index}][price]"
                                                    class="w-full border-0 bg-transparent focus:ring-0 p-2 text-sm"
                                                    placeholder="0.00">
                                            </td>
                                            <td class="p-2 text-center"><button type="button"
                                                    onclick="this.closest('tr').remove()"
                                                    class="text-red-400 hover:text-red-600">×</button></td>
                                        </tr>
                                    `;
            tbody.insertAdjacentHTML('beforeend', row);
        }

        function addPartRow(data = null) {
            const tbody = document.getElementById('parts-table-body');
            const noPartsMsg = document.getElementById('no-parts-msg');
            if (noPartsMsg) noPartsMsg.style.display = 'none';

            const index = Date.now();
            const name = data ? data.name : '';
            const productId = data ? data.id : '';

            const row = `
                                                            <tr class="bg-indigo-50/30">
                                                                <td class="p-2">
                                                                     <input type="hidden" name="parts_used[${index}][product_id]" value="${productId}">
                                                                    <input type="text" name="parts_used[${index}][name]" value="${name}" class="w-full border-0 bg-transparent focus:ring-0 p-2 text-sm" placeholder="Part Name" required>
                                                                </td>
                                                                <td class="p-2">
                                                                    <input type="number" name="parts_used[${index}][qty]" value="1" class="w-full border-0 bg-transparent focus:ring-0 p-2 text-sm" placeholder="1">
                                                                </td>
                                                                <td class="p-2">
                                                                    <input type="number" step="0.01" name="parts_used[${index}][price]" value="0.00" class="w-full border-0 bg-transparent focus:ring-0 p-2 text-sm" placeholder="0.00">
                                                                </td>
                                                                <td class="p-2 text-center">
                                                                    <button type="button" onclick="this.closest('tr').remove()" class="text-red-500 hover:text-red-700 text-sm">Remove</button>
                                                                </td>
                                                            </tr>
                                                        `;
            tbody.insertAdjacentHTML('beforeend', row);
        }

        // WO Inventory Search Logic
        const woSearchInput = document.getElementById('wo-inventory-search');
        const woResultsDiv = document.getElementById('wo-search-results');
        let woDebounceTimer;

        woSearchInput.addEventListener('input', function () {
            clearTimeout(woDebounceTimer);
            const query = this.value;

            if (query.length < 2) {
                woResultsDiv.classList.add('hidden');
                return;
            }

            woDebounceTimer = setTimeout(() => {
                fetch(`/api/products-services/search?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        woResultsDiv.innerHTML = '';
                        // Filter for products only? Or allow services too? Assuming parts for now.
                        const parts = data.filter(item => item.type === 'product');

                        if (parts.length > 0) {
                            woResultsDiv.classList.remove('hidden');
                            parts.forEach(item => {
                                const div = document.createElement('div');
                                div.className = 'px-4 py-2 hover:bg-indigo-50 cursor-pointer text-sm border-b border-gray-50 last:border-0';
                                div.textContent = item.label;
                                div.onclick = () => {
                                    addPartRow({
                                        id: item.id,
                                        name: item.name
                                    });
                                    woSearchInput.value = '';
                                    woResultsDiv.classList.add('hidden');
                                };
                                woResultsDiv.appendChild(div);
                            });
                        } else {
                            woResultsDiv.classList.add('hidden');
                        }
                    });
            }, 300);
        });

        // Close results when clicking outside
        document.addEventListener('click', function (e) {
            if (!woSearchInput.contains(e.target) && !woResultsDiv.contains(e.target)) {
                woResultsDiv.classList.add('hidden');
            }
        });
    </script>
@endsection