@extends('layouts.app')

@section('title', 'Technician Live Board')

@section('content')
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <nav class="flex text-sm font-medium text-indigo-500 mb-2">
                    <a href="{{ route('dashboard') }}" class="hover:text-indigo-700">Dashboard</a>
                    <span class="mx-2">/</span>
                    <a href="{{ route('work-orders.index') }}" class="hover:text-indigo-700">Work Orders</a>
                    <span class="mx-2">/</span>
                    <span class="text-indigo-900">Live Board</span>
                </nav>
            </div>
            <div>
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

        <!-- Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach($technicians as $tech)
                @php
                    $activeJob = $tech->workOrders->first();
                    $isBusy = $activeJob !== null;
                @endphp

                <div
                    class="relative group bg-white rounded-xl shadow-sm border {{ $isBusy ? 'border-red-100 ring-1 ring-red-50' : 'border-gray-100' }} p-6 transition-all hover:shadow-md">
                    <!-- Status Indicator -->
                    <div class="absolute top-4 right-4">
                        @if($isBusy)
                            <span class="flex h-3 w-3">
                                <span
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-300 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-red-400"></span>
                            </span>
                        @else
                            <span class="inline-block w-3 h-3 rounded-full bg-green-400"></span>
                        @endif
                    </div>

                    <!-- Tech Info -->
                    <div class="flex items-center gap-4 mb-6">
                        <div
                            class="w-12 h-12 rounded-full {{ $isBusy ? 'bg-red-50 text-red-500' : 'bg-gray-100 text-gray-500' }} flex items-center justify-center font-bold text-xl">
                            {{ substr($tech->name, 0, 1) }}
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900">{{ $tech->name }}</h3>
                            <p class="text-xs {{ $isBusy ? 'text-red-500 font-medium' : 'text-gray-400' }}">
                                {{ $isBusy ? 'Busy' : 'Available' }}
                            </p>
                        </div>
                    </div>

                    <!-- Active Job Details -->
                    @if($isBusy)
                        <div class="space-y-4">
                            <div class="p-4 bg-red-50/30 rounded-lg border border-red-50">
                                <h4 class="text-xs font-bold text-red-400 uppercase tracking-wider mb-2">Current Activity</h4>
                                <div class="mb-2">
                                    <p class="font-bold text-gray-900 leading-tight">{{ $activeJob->vehicle->make }}
                                        {{ $activeJob->vehicle->model }}
                                    </p>
                                    <p class="text-xs text-gray-500">{{ $activeJob->vehicle->license_plate }}</p>
                                </div>
                                <div class="flex justify-between items-end">
                                    <span class="text-xs bg-white px-2 py-1 rounded text-gray-500 border border-red-100 shadow-sm">
                                        Started {{ $activeJob->started_at ? $activeJob->started_at->format('H:i') : '' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Button: Opens Modal -->
                            <button onclick="openJobDetails({{ $activeJob->id }}, '{{ addslashes($activeJob->technician_notes) }}')"
                                class="w-full py-2 bg-red-500 text-white rounded-lg font-medium hover:bg-red-600 transition-colors shadow-sm text-sm">
                                View Job
                            </button>
                        </div>
                    @else
                        <div class="h-32 flex items-center justify-center border-2 border-dashed border-gray-100 rounded-lg">
                            <p class="text-sm text-gray-400">No active job</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- Quick Update Modal -->
    <div id="job-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog"
        aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeJobModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            <div
                class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">

                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-lg leading-6 font-bold text-gray-900" id="modal-title">Job Details</h3>
                    <button onclick="closeJobModal()" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form id="job-form" method="POST" action="">
                    @csrf
                    @method('PUT')

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Technician Notes / Updates</label>
                            <textarea id="modal-notes" name="technician_notes" rows="6"
                                class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm text-sm"
                                placeholder="Update job status, notes, issues..."></textarea>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                            <a id="full-edit-link" href="#"
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-medium">Open
                                Full Sheet</a>
                            <button type="submit"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">Save
                                Notes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openJobDetails(id, notes) {
            console.log('Opening modal for job', id); // Debugging
            const modal = document.getElementById('job-modal');
            const form = document.getElementById('job-form');
            const notesField = document.getElementById('modal-notes');
            const fullEditLink = document.getElementById('full-edit-link');

            // Set Form Action
            form.action = `/work-orders/${id}`;

            // Set Notes
            notesField.value = notes;

            // Set Full Edit Link (Edit Page)
            // Fix: Ensure route is correct, if work-orders.edit is standard resource route then /work-orders/{id}/edit
            fullEditLink.href = `/work-orders/${id}/edit`;

            modal.classList.remove('hidden');
        }

        function closeJobModal() {
            document.getElementById('job-modal').classList.add('hidden');
        }
    </script>
@endsection