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
                    <span class="text-indigo-900">Edit WO #{{ $workOrder->id }}</span>
                </nav>
                <h1 class="text-2xl font-bold text-indigo-900">Edit Work Order #{{ $workOrder->id }}</h1>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-50 text-green-700 p-4 rounded-lg border border-green-200">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('work-orders.update', $workOrder) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- LEFT COLUMN: Info & Scope -->
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
                            <p class="text-sm text-indigo-900 font-medium">{{ $workOrder->vehicle->make ?? 'Unknown' }}
                                {{ $workOrder->vehicle->model ?? '' }}</p>
                            <p class="text-xs text-indigo-400 mt-1">Plate: {{ $workOrder->vehicle->license_plate ?? 'N/A' }}
                            </p>
                        </div>
                    </div>

                    <!-- Scope -->
                    <div class="bg-white rounded-xl shadow-sm border border-indigo-50 p-6">
                        <h3 class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-4">Service Scope</h3>
                        <div class="space-y-2">
                            @if($workOrder->service_tasks)
                                @foreach($workOrder->service_tasks as $task)
                                    <div class="flex items-center p-2 rounded border border-indigo-50 bg-indigo-50/10">
                                        <span class="text-sm text-indigo-900 font-medium">{{ $task['name'] ?? $task }}</span>
                                    </div>
                                @endforeach
                            @else
                                <p class="text-sm text-indigo-400 italic">No specific tasks defined.</p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Actions -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Technician Notes -->
                    <div class="bg-white rounded-xl shadow-sm border border-indigo-50 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xs font-bold text-indigo-400 uppercase tracking-wider">Technician Notes</h3>
                            <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-bold text-sm hover:bg-indigo-700 transition-colors shadow-sm">
                                Save Notes
                            </button>
                        </div>
                        <textarea name="technician_notes" rows="6"
                            class="w-full rounded-lg border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            placeholder="Work performed, observations...">{{ $workOrder->technician_notes }}</textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div class="bg-white rounded-xl shadow-sm border border-indigo-50 p-6">
                        <div class="flex items-center justify-between">
                            <a href="{{ route('work-orders.show', $workOrder) }}"
                                class="px-6 py-3 bg-indigo-600 text-white rounded-lg font-bold hover:bg-indigo-700 transition-colors shadow-sm">
                                Open Job Sheet
                            </a>
                            <a href="{{ route('work-orders.index') }}"
                                class="inline-flex items-center px-4 py-2 border border-indigo-200 text-indigo-600 rounded-lg text-sm font-medium hover:bg-indigo-50 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Back to Work Orders
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        function addPartRow() {
            const tbody = document.getElementById('parts-table-body');
            const noPartsMsg = document.getElementById('no-parts-msg');
            if (noPartsMsg) noPartsMsg.style.display = 'none';

            const index = Date.now();
            const row = `
                    <tr class="bg-indigo-50/30">
                        <td class="p-2"><input type="text" name="parts_used[${index}][name]" class="w-full border-0 bg-transparent focus:ring-0 p-2 text-sm" placeholder="Part Name" required></td>
                        <td class="p-2"><input type="number" name="parts_used[${index}][qty]" value="1" class="w-full border-0 bg-transparent focus:ring-0 p-2 text-sm"></td>
                        <td class="p-2 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-red-500 hover:text-red-700">x</button></td>
                    </tr>
                `;
            tbody.insertAdjacentHTML('beforeend', row);
        }
    </script>
@endsection