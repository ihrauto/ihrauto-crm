@extends('layouts.app')

@section('title', 'Job Details - WO #' . $workOrder->id)

@section('content')
    <div class="space-y-6">
        <!-- Header / Nav -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Job Details <span class="text-gray-400 font-normal">#{{ $workOrder->id }}</span></h1>
            <a href="{{ route('work-orders.show', $workOrder) }}"
                class="inline-flex items-center px-4 py-2 border border-indigo-200 text-indigo-600 rounded-lg text-sm font-medium hover:bg-indigo-50 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Work Order
            </a>
        </div>

        <!-- Main Content Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            
            <!-- Context Header: Customer & Vehicle -->
            <div class="bg-gray-50 border-b border-gray-200 p-6 flex flex-col md:flex-row md:justify-between gap-6">
                <!-- Customer -->
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-lg flex-shrink-0">
                        {{ substr($workOrder->customer->name ?? '?', 0, 1) }}
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">{{ $workOrder->customer->name ?? 'Unknown Customer' }}</h2>
                        <p class="text-sm text-gray-500">{{ $workOrder->customer->phone ?? 'No Phone' }}</p>
                    </div>
                </div>
                
                <!-- Vehicle -->
                <div class="md:text-right">
                    <h3 class="text-lg font-bold text-gray-900">{{ $workOrder->vehicle->make ?? 'Unknown' }} {{ $workOrder->vehicle->model ?? 'Vehicle' }}</h3>
                    <div class="flex items-center md:justify-end gap-2 mt-1">
                        <span class="px-2 py-0.5 bg-gray-200 text-gray-700 rounded text-xs font-mono font-bold">{{ $workOrder->vehicle->license_plate ?? 'No Plate' }}</span>
                    </div>
                </div>
            </div>

            <!-- Content Body -->
            <div class="p-6 space-y-8">
                
                <!-- Job Type Badge -->
                <div>
                   <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $workOrder->checkin_id ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                        {{ $workOrder->checkin_id ? 'Check-in Service' : 'Tire Hotel Service' }}
                    </span>
                    <span class="text-xs text-gray-400 ml-2">Completed {{ $workOrder->completed_at ? $workOrder->completed_at->format('M d, Y H:i') : 'In Progress' }}</span>
                </div>

                <!-- Services List -->
                <div>
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-3 border-b border-gray-100 pb-2">Services Performed</h3>
                    @if($workOrder->service_tasks && count($workOrder->service_tasks) > 0)
                        <ul class="space-y-2">
                            @foreach($workOrder->service_tasks as $task)
                                <li class="flex items-center gap-3 text-sm text-gray-700">
                                    <svg class="w-5 h-5 {{ isset($task['completed']) && $task['completed'] ? 'text-green-500' : 'text-gray-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    {{ $task['name'] ?? 'Service' }}
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-400 italic">No services recorded.</p>
                    @endif
                </div>

                <!-- Parts List -->
                <div>
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-3 border-b border-gray-100 pb-2">Parts & Materials</h3>
                    @if($workOrder->parts_used && count($workOrder->parts_used) > 0)
                        <div class="space-y-2">
                            @foreach($workOrder->parts_used as $part)
                                <div class="flex justify-between items-center text-sm py-1">
                                    <span class="text-gray-700 font-medium">{{ $part['name'] ?? 'Part' }}</span>
                                    <span class="text-xs font-bold bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">x{{ $part['qty'] ?? 1 }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400 italic">No parts recorded.</p>
                    @endif
                </div>

                <!-- Technician Notes -->
                @if($workOrder->technician_notes)
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-3 border-b border-gray-100 pb-2">Notes</h3>
                        <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line">{{ $workOrder->technician_notes }}</p>
                    </div>
                @endif

            </div>
        </div>
    </div>
@endsection