@extends('layouts.app')

@section('title', 'Appointments')

@section('content')
<div class="space-y-6">
    <!-- Header Controls -->
    <div class="flex flex-col md:flex-row md:items-center justify-end gap-4">
        <!-- Title and Subtitle removed -->
        
        <div class="flex items-center gap-3">
             <div class="flex bg-white rounded-lg shadow-sm border border-indigo-100 p-1">
                <a href="{{ route('appointments.index', ['date' => $startOfWeek->copy()->subWeek()->format('Y-m-d')]) }}" class="p-1 hover:bg-indigo-50 rounded text-indigo-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <span class="px-3 py-1 text-sm font-bold text-indigo-900">
                    {{ $startOfWeek->format('M d') }} - {{ $endOfWeek->format('M d, Y') }}
                </span>
                <a href="{{ route('appointments.index', ['date' => $startOfWeek->copy()->addWeek()->format('Y-m-d')]) }}" class="p-1 hover:bg-indigo-50 rounded text-indigo-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
            </div>
            
            <button onclick="document.getElementById('new-appointment-modal').classList.remove('hidden')" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-bold shadow-sm transition-colors text-sm flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                New Appointment
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 text-green-700 p-4 rounded-lg border border-green-200">
            {{ session('success') }}
        </div>
    @endif
    
    @if($errors->any())
        <div class="bg-red-50 text-red-700 p-4 rounded-lg border border-red-200">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>• {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Weekly Grid -->
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        @for($day = 0; $day < 6; $day++)
            @php 
                $currentDay = $startOfWeek->copy()->addDays($day);
                $dateKey = $currentDay->format('Y-m-d');
                $dayAppointments = $appointmentsByDate[$dateKey] ?? collect();
                $isToday = $currentDay->isToday();
            @endphp
            
            <div class="bg-white rounded-xl shadow-sm border {{ $isToday ? 'border-indigo-300 ring-1 ring-indigo-100' : 'border-indigo-50' }} flex flex-col h-[600px]">
                <!-- Day Header -->
                <div class="p-3 border-b border-indigo-50 {{ $isToday ? 'bg-indigo-50' : '' }} text-center">
                    <span class="block text-xs font-bold uppercase text-indigo-400">{{ $currentDay->format('D') }}</span>
                    <span class="block text-lg font-bold {{ $isToday ? 'text-indigo-700' : 'text-indigo-900' }}">{{ $currentDay->format('d') }}</span>
                </div>
                
                <!-- Slots -->
                <div class="flex-1 p-2 space-y-2 overflow-y-auto bg-gray-50/30">
                    @forelse($dayAppointments as $apt)
                        <div onclick="openAppointmentDetails({{ $apt }})" class="bg-white px-2 py-1.5 rounded border-l-2 {{ $apt->status === 'completed' ? 'border-green-500' : ($apt->status === 'failed' ? 'border-red-500' : 'border-indigo-500') }} shadow-sm hover:shadow transition-shadow cursor-pointer mb-1.5">
                             <!-- Row 1: Time + Title -->
                             <div class="flex items-baseline justify-between">
                                 <span class="text-xs font-bold text-indigo-900 w-10 shrink-0">{{ $apt->start_time->format('H:i') }}</span>
                                 <span class="text-[11px] font-semibold text-gray-800 truncate flex-1 text-right">{{ $apt->title ?: $apt->customer->name }}</span>
                             </div>
                             
                             <!-- Row 2: Type + Vehicle -->
                             <div class="flex items-center justify-between mt-0.5">
                                  <span class="text-[9px] text-gray-400 truncate max-w-[50%]">{{ $apt->vehicle->make ?? '' }} {{ $apt->vehicle->model ?? '' }}</span>
                                  <div class="flex items-center gap-1">
                                      <span class="text-[9px] text-gray-500 bg-gray-100 px-1 rounded">{{ $apt->type_label }}</span>
                                      @if($apt->status === 'completed')
                                        <span class="text-[9px] font-bold text-green-600">✓</span>
                                      @elseif($apt->status === 'failed')
                                        <span class="text-[9px] font-bold text-red-600">⚠</span>
                                      @endif
                                  </div>
                             </div>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center text-gray-300 pointer-events-none">
                            <span class="text-xs italic">Free</span>
                        </div>
                    @endforelse
                </div>
                
                <!-- Add Button (Footer) -->
                <div class="p-2 border-t border-indigo-50">
                    <button onclick="openModalWithDate('{{ $dateKey }}')" class="w-full py-1 text-xs text-indigo-400 hover:text-indigo-600 hover:bg-indigo-50 rounded text-center transition-colors">
                        + Add
                    </button>
                </div>
            </div>
        @endfor
    </div>
</div>

<!-- Details Modal -->
<div id="appointment-details-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="document.getElementById('appointment-details-modal').classList.add('hidden')"></div>

    <!-- Centering Container -->
    <div class="flex min-h-screen items-center justify-center p-4 text-center">
         <div class="relative w-full max-w-lg transform rounded-lg bg-white p-6 text-left shadow-xl transition-all">
             <!-- Modal Header -->
            <div class="flex justify-between items-start">
                <h3 class="text-lg leading-6 font-medium text-gray-900" id="details-title">Appointment Details</h3>
                <span id="details-status" class="px-2 py-1 text-xs font-bold rounded-full"></span>
            </div>
            
            <div class="mt-4 space-y-4">
                <div class="bg-indigo-50 p-3 rounded-lg">
                    <p class="text-xs text-indigo-500 uppercase font-bold">Time</p>
                    <p class="text-lg font-bold text-indigo-900"><span id="details-time"></span></p>
                    <p class="text-sm text-indigo-700" id="details-date"></p>
                </div>
                
                <div>
                     <p class="text-xs text-gray-500 uppercase font-bold">Customer</p>
                     <p class="text-base font-bold text-gray-900" id="details-customer"></p>
                     <p class="text-sm text-gray-600" id="details-vehicle"></p>
                </div>
                
                <div>
                     <p class="text-xs text-gray-500 uppercase font-bold">Service Check</p>
                     <p class="text-sm text-gray-900" id="details-type"></p>
                     <p class="text-sm text-gray-500 italic mt-1" id="details-notes"></p>
                </div>
            </div>
            
            <div class="mt-6">
                <!-- Main Actions Grid -->
                <div class="grid grid-cols-3 gap-3" id="action-buttons">
                     <!-- Complete Action -->
                     <form id="form-complete" method="POST" class="w-full">
                         @csrf
                         @method('PUT')
                         <input type="hidden" name="status" value="completed">
                         <button type="submit" id="btn-complete" class="w-full justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-green-600 text-sm font-bold text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                             Mark Done
                         </button>
                     </form>
    
                     <!-- Edit Action -->
                     <button type="button" onclick="openEditModal()" class="w-full justify-center rounded-lg border border-indigo-200 shadow-sm px-4 py-2 bg-white text-sm font-bold text-indigo-700 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        Edit
                     </button>
                     
                     <!-- Initial Failed Button (Toggles Reason) -->
                     <button type="button" id="btn-failed" onclick="toggleFailedReason()" class="w-full justify-center rounded-lg border border-red-200 shadow-sm px-4 py-2 bg-white text-sm font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500">
                         Failed
                     </button>
                </div>
                
                <!-- Failed Reason Form (Hidden by default) -->
                <div id="failed-reason-section" class="hidden mt-4 bg-red-50 p-4 rounded-lg border border-red-100">
                    <form id="form-failed" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="failed">
                        <label class="block text-xs font-bold text-red-700 uppercase mb-2">Reason for Failure / No Show</label>
                        <textarea name="notes" required class="w-full rounded-md border-red-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm" rows="2" placeholder="e.g. Client did not show up..."></textarea>
                        
                        <div class="mt-3 flex gap-2">
                             <button type="submit" class="flex-1 justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                 Confirm Failed
                             </button>
                             <button type="button" onclick="toggleFailedReason()" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">
                                 Cancel
                             </button>
                        </div>
                    </form>
                </div>
                
                <!-- Delete Action (Secondary) -->
                <div class="mt-2 text-center">
                     <form id="form-delete" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to completely DELETE this record? This cannot be undone.');">
                         @csrf
                         @method('DELETE')
                         <button type="submit" class="text-xs text-gray-400 hover:text-red-500 underline">
                             Delete Appointment
                         </button>
                     </form>
                </div>
            </div>
            
             <div class="mt-3">
                <button type="button" onclick="document.getElementById('appointment-details-modal').classList.add('hidden')" class="w-full justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-gray-50 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:outline-none">
                    Close
                </button>
            </div>
         </div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="new-appointment-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="document.getElementById('new-appointment-modal').classList.add('hidden')"></div>

    <!-- Centering Container -->
    <div class="flex min-h-screen items-center justify-center p-4 text-center">
        <div class="relative w-full max-w-lg transform rounded-xl bg-white p-8 text-left shadow-2xl transition-all border border-gray-100">
            <!-- Modal Content -->
            <div>
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-indigo-50 mb-6">
                    <svg class="h-8 w-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <div class="text-center sm:mt-5">
                    <h3 class="text-xl font-bold text-gray-900" id="form-modal-title">Schedule New Appointment</h3>
                    <p class="text-sm text-gray-500 mt-1">Enter the details for the upcoming service.</p>
                </div>
                
                <div class="mt-8">
                    <form id="appointment-form" action="{{ route('appointments.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="_method" id="form-method" value="POST">
                        
                        <!-- Customer Selection -->
                        <div>
                            <label class="text-sm font-medium text-indigo-900 mb-2 block">Customer *</label>
                            
                            <!-- Two Button Options -->
                            <div id="customer-choice-buttons" class="grid grid-cols-2 gap-3">
                                <button type="button" onclick="showSearchClient()" class="py-3 px-4 rounded-lg border-2 border-indigo-200 text-indigo-700 font-semibold hover:bg-indigo-50 hover:border-indigo-400 transition-all flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    Search Client
                                </button>
                                <button type="button" onclick="showAddNewClient()" class="py-3 px-4 rounded-lg border-2 border-indigo-200 text-indigo-700 font-semibold hover:bg-indigo-50 hover:border-indigo-400 transition-all flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    Add New Client
                                </button>
                            </div>
                            
                            <!-- Search Existing Customer (hidden by default) -->
                            <div id="search-customer-section" class="hidden">
                                <div class="mb-2 text-right">
                                    <button type="button" onclick="showCustomerChoice()" class="text-xs text-indigo-500 hover:text-indigo-700">← Back</button>
                                </div>
                                <input type="hidden" name="customer_id" id="form-customer_id" value="">
                                <div class="relative">
                                    <input type="text" id="customer-search-input" 
                                        placeholder="Type customer name..." 
                                        autocomplete="off"
                                        oninput="searchCustomers(this.value)"
                                        onfocus="showSearchResults()"
                                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                    
                                    <!-- Search Results Dropdown -->
                                    <div id="customer-search-results" class="hidden absolute z-10 mt-1 w-full bg-white rounded-lg shadow-lg ring-1 ring-indigo-200 max-h-48 overflow-y-auto">
                                        @foreach($customers as $customer)
                                            <div class="customer-result px-4 py-2 hover:bg-indigo-50 cursor-pointer text-sm" 
                                                data-id="{{ $customer->id }}" 
                                                data-name="{{ $customer->name }}"
                                                data-phone="{{ $customer->phone }}"
                                                onclick="selectCustomer({{ $customer->id }}, '{{ addslashes($customer->name) }}', '{{ $customer->phone }}')">
                                                <span class="font-medium text-indigo-900">{{ $customer->name }}</span>
                                                <span class="text-indigo-400 ml-2">{{ $customer->phone }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div id="selected-customer-display" class="hidden mt-2 p-2 bg-indigo-50 rounded-lg flex items-center justify-between">
                                    <span id="selected-customer-name" class="text-sm font-medium text-indigo-900"></span>
                                    <button type="button" onclick="clearSelectedCustomer()" class="text-xs text-red-500 hover:text-red-700">✕ Clear</button>
                                </div>
                            </div>
                            
                            <!-- New Customer Fields (hidden by default) -->
                            <div id="new-customer-section" class="hidden">
                                <div class="mb-2 text-right">
                                    <button type="button" onclick="showCustomerChoice()" class="text-xs text-indigo-500 hover:text-indigo-700">← Back</button>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <input type="text" name="new_customer_name" id="new-customer-name" placeholder="Full Name" class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                    </div>
                                    <div>
                                        <input type="tel" name="new_customer_phone" id="new-customer-phone" placeholder="Phone Number" class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Date & Time -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">Date *</label>
                                <input type="date" name="start_date" id="form-date" required class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            </div>
                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">Time *</label>
                                <input type="time" name="start_time" id="form-time" value="09:00" required class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            </div>
                        </div>
                        
                        <!-- Type & Duration -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">Service Type</label>
                                <select name="type" id="form-type" class="block w-full rounded-lg border-0 py-2 pl-4 pr-10 text-indigo-900 ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                    <option value="tire_change">Tire Change</option>
                                    <option value="service">General Service</option>
                                    <option value="repair">Repair</option>
                                    <option value="inspection">Inspection</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">Duration</label>
                                <select name="duration" id="form-duration" class="block w-full rounded-lg border-0 py-2 pl-4 pr-10 text-indigo-900 ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                    <option value="30">30 min</option>
                                    <option value="60" selected>1 Hour</option>
                                    <option value="90">1.5 Hours</option>
                                    <option value="120">2 Hours</option>
                                </select>
                            </div>
                        </div>
                        
                        <input type="hidden" name="status" value="scheduled">
                        
                        <div>
                            <label class="text-sm font-medium text-indigo-900 mb-1 block">Notes</label>
                            <textarea name="notes" id="form-notes" rows="3" class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="Add any specific details here..."></textarea>
                        </div>

                        <div class="flex justify-end space-x-4 mt-8">
                             <button type="button" onclick="document.getElementById('new-appointment-modal').classList.add('hidden')" class="inline-flex items-center px-6 py-3 border border-red-200 text-red-600 rounded-lg font-semibold hover:bg-red-50 transition-all duration-200 shadow-sm">
                                Cancel
                            </button>
                            <button type="submit" id="form-submit-btn" class="inline-flex items-center px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-all duration-200 shadow-sm">
                                Book Appointment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let currentAppointment = null;

    function resetForm() {
        const form = document.getElementById('appointment-form');
        form.action = "{{ route('appointments.store') }}";
        document.getElementById('form-method').value = "POST";
        document.getElementById('form-modal-title').textContent = "Schedule New Appointment";
        document.getElementById('form-submit-btn').textContent = "Book Appointment";
        
        // Reset values
        form.reset();
        document.getElementById('form-time').value = "09:00";
        document.getElementById('form-duration').value = "60";
    }

    function openModalWithDate(date) {
        resetForm();
        document.getElementById('form-date').value = date;
        document.getElementById('new-appointment-modal').classList.remove('hidden');
    }
    
    function toggleFailedReason() {
        const section = document.getElementById('failed-reason-section');
        const buttons = document.getElementById('action-buttons');
        
        if (section.classList.contains('hidden')) {
            section.classList.remove('hidden');
            buttons.classList.add('opacity-50', 'pointer-events-none');
        } else {
            section.classList.add('hidden');
            buttons.classList.remove('opacity-50', 'pointer-events-none');
        }
    }
    
    function openAppointmentDetails(apt) {
        currentAppointment = apt;
        
        // Reset Failed Section
        document.getElementById('failed-reason-section').classList.add('hidden');
        document.getElementById('action-buttons').classList.remove('opacity-50', 'pointer-events-none');
        
        // Populate Details
        document.getElementById('details-title').textContent = apt.title || 'Appointment Details';
        document.getElementById('details-time').textContent = new Date(apt.start_time).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        document.getElementById('details-date').textContent = new Date(apt.start_time).toLocaleDateString([], {weekday: 'long', month: 'long', day: 'numeric'});
        
        document.getElementById('details-customer').textContent = apt.customer ? apt.customer.name : 'Unknown';
        document.getElementById('details-vehicle').textContent = apt.vehicle ? (apt.vehicle.make + ' ' + apt.vehicle.model + ' (' + apt.vehicle.license_plate + ')') : 'No vehicle';
        
        document.getElementById('details-type').textContent = apt.type.replace('_', ' ').toUpperCase();
        document.getElementById('details-notes').textContent = apt.notes || 'No notes.';
        
        const statusSpan = document.getElementById('details-status');
        statusSpan.textContent = apt.status.toUpperCase();
        
        let statusClasses = 'bg-blue-100 text-blue-800';
        if(apt.status === 'completed') statusClasses = 'bg-green-100 text-green-800';
        if(apt.status === 'failed') statusClasses = 'bg-red-100 text-red-800';
        
        statusSpan.className = 'px-2 py-1 text-xs font-bold rounded-full ' + statusClasses;
        
        // Setup Actions
        const formComplete = document.getElementById('form-complete');
        const formDelete = document.getElementById('form-delete');
        const formFailed = document.getElementById('form-failed');
        
        formComplete.action = `/appointments/${apt.id}`;
        formDelete.action = `/appointments/${apt.id}`;
        formFailed.action = `/appointments/${apt.id}`; // Setup Failed Form Action
        
        // Button Logic
        const btnComplete = document.getElementById('btn-complete');
        const btnFailed = document.getElementById('btn-failed');

        // Reset classes
        btnComplete.className = "w-full justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 text-sm font-bold text-white focus:outline-none focus:ring-2 focus:ring-green-500 transition-colors";
        btnFailed.className = "w-full justify-center rounded-lg border border-red-200 shadow-sm px-4 py-2 bg-white text-sm font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors";

        if(apt.status === 'completed') {
            // Completed state
            btnComplete.disabled = true;
            btnComplete.classList.add('bg-gray-100', 'text-gray-400', 'cursor-not-allowed', 'border-gray-200');
            btnComplete.classList.remove('bg-green-600', 'hover:bg-green-700', 'text-white', 'shadow-sm');
            btnComplete.innerHTML = 'Completed ✓';

            btnFailed.disabled = true;
            btnFailed.classList.add('bg-gray-100', 'text-gray-400', 'cursor-not-allowed', 'border-gray-200');
            btnFailed.classList.remove('bg-white', 'text-red-700', 'border-red-200', 'hover:bg-red-50');
            btnFailed.innerHTML = 'Failed';
        } 
        else if(apt.status === 'failed') {
            // Failed state
            btnComplete.disabled = true;
            btnComplete.classList.add('bg-gray-100', 'text-gray-400', 'cursor-not-allowed', 'border-gray-200');
            btnComplete.classList.remove('bg-green-600', 'hover:bg-green-700', 'text-white', 'shadow-sm');
            btnComplete.innerHTML = 'Mark Done';

            btnFailed.disabled = true;
            btnFailed.classList.add('bg-gray-100', 'text-gray-400', 'cursor-not-allowed', 'border-gray-200');
            btnFailed.classList.remove('bg-white', 'text-red-700', 'border-red-200', 'hover:bg-red-50');
            btnFailed.innerHTML = 'Failed ⚠';
        } else {
            // Active state
            btnComplete.disabled = false;
            btnComplete.classList.add('bg-green-600', 'hover:bg-green-700');
            btnComplete.innerHTML = 'Mark Done';

            btnFailed.disabled = false;
            btnFailed.innerHTML = 'Failed';
        }
        
        // Show Modal
        document.getElementById('appointment-details-modal').classList.remove('hidden');
    }

    function openEditModal() {
        if (!currentAppointment) return;
        
        // Close details modal
        document.getElementById('appointment-details-modal').classList.add('hidden');
        
        // Setup Form for Edit
        const form = document.getElementById('appointment-form');
        form.action = `/appointments/${currentAppointment.id}`;
        document.getElementById('form-method').value = "PUT";
        document.getElementById('form-modal-title').textContent = "Edit Appointment";
        document.getElementById('form-submit-btn').textContent = "Update Appointment";
        
        // Populate Inputs
        document.getElementById('form-customer_id').value = currentAppointment.customer_id;
        
        // Parse dates (ISO string assumed)
        const dateObj = new Date(currentAppointment.start_time);
        
        // Format YYYY-MM-DD
        const yyyy = dateObj.getFullYear();
        const mm = String(dateObj.getMonth() + 1).padStart(2, '0');
        const dd = String(dateObj.getDate()).padStart(2, '0');
        document.getElementById('form-date').value = `${yyyy}-${mm}-${dd}`;
        
        // Format HH:mm
        const hh = String(dateObj.getHours()).padStart(2, '0');
        const min = String(dateObj.getMinutes()).padStart(2, '0');
        document.getElementById('form-time').value = `${hh}:${min}`;
        
        document.getElementById('form-type').value = currentAppointment.type;
        document.getElementById('form-duration').value = currentAppointment.duration;
        document.getElementById('form-notes').value = currentAppointment.notes || '';
        
        // Show Modal
        document.getElementById('new-appointment-modal').classList.remove('hidden');
    }

    let customerMode = 'choice'; // 'choice', 'search', 'new'
    
    function showSearchClient() {
        customerMode = 'search';
        document.getElementById('customer-choice-buttons').classList.add('hidden');
        document.getElementById('search-customer-section').classList.remove('hidden');
        document.getElementById('new-customer-section').classList.add('hidden');
        
        // Set required
        document.getElementById('form-customer_id').setAttribute('required', 'required');
        document.getElementById('new-customer-name').removeAttribute('required');
        document.getElementById('new-customer-phone').removeAttribute('required');
    }
    
    function showAddNewClient() {
        customerMode = 'new';
        document.getElementById('customer-choice-buttons').classList.add('hidden');
        document.getElementById('search-customer-section').classList.add('hidden');
        document.getElementById('new-customer-section').classList.remove('hidden');
        
        // Set required
        document.getElementById('form-customer_id').removeAttribute('required');
        document.getElementById('form-customer_id').value = '';
        document.getElementById('new-customer-name').setAttribute('required', 'required');
        document.getElementById('new-customer-phone').setAttribute('required', 'required');
    }
    
    function showCustomerChoice() {
        customerMode = 'choice';
        document.getElementById('customer-choice-buttons').classList.remove('hidden');
        document.getElementById('search-customer-section').classList.add('hidden');
        document.getElementById('new-customer-section').classList.add('hidden');
        
        // Clear fields
        document.getElementById('form-customer_id').value = '';
        document.getElementById('new-customer-name').value = '';
        document.getElementById('new-customer-phone').value = '';
        
        // Remove required from both
        document.getElementById('form-customer_id').removeAttribute('required');
        document.getElementById('new-customer-name').removeAttribute('required');
        document.getElementById('new-customer-phone').removeAttribute('required');
    }
    
    function resetCustomerMode() {
        showCustomerChoice();
    }

    // Customer Search Functions
    function searchCustomers(query) {
        const results = document.getElementById('customer-search-results');
        const items = results.querySelectorAll('.customer-result');
        const normalizedQuery = query.toLowerCase().trim();
        
        if (normalizedQuery.length === 0) {
            results.classList.add('hidden');
            return;
        }
        
        let hasResults = false;
        items.forEach(item => {
            const name = item.dataset.name.toLowerCase();
            const phone = item.dataset.phone.toLowerCase();
            if (name.includes(normalizedQuery) || phone.includes(normalizedQuery)) {
                item.classList.remove('hidden');
                hasResults = true;
            } else {
                item.classList.add('hidden');
            }
        });
        
        if (hasResults) {
            results.classList.remove('hidden');
        } else {
            results.classList.add('hidden');
        }
    }
    
    function showSearchResults() {
        const input = document.getElementById('customer-search-input');
        if (input.value.length > 0) {
            searchCustomers(input.value);
        }
    }
    
    function selectCustomer(id, name, phone) {
        document.getElementById('form-customer_id').value = id;
        document.getElementById('customer-search-input').value = '';
        document.getElementById('customer-search-results').classList.add('hidden');
        
        // Show selected customer
        document.getElementById('selected-customer-display').classList.remove('hidden');
        document.getElementById('selected-customer-name').textContent = name + ' (' + phone + ')';
        document.getElementById('customer-search-input').classList.add('hidden');
    }
    
    function clearSelectedCustomer() {
        document.getElementById('form-customer_id').value = '';
        document.getElementById('selected-customer-display').classList.add('hidden');
        document.getElementById('customer-search-input').classList.remove('hidden');
        document.getElementById('customer-search-input').value = '';
        document.getElementById('customer-search-input').focus();
    }
    
    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        const searchSection = document.getElementById('search-customer-section');
        const results = document.getElementById('customer-search-results');
        if (searchSection && results && !searchSection.contains(e.target)) {
            results.classList.add('hidden');
        }
    });
</script>
@endsection
