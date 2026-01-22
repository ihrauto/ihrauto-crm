@extends('layouts.app')

@section('title', 'Appointments')

@push('styles')
    <style>
        /* FullCalendar Custom Theme - Indigo CRM */
        :root {
            --fc-border-color: #e0e7ff;
            --fc-page-bg-color: #ffffff;
            --fc-today-bg-color: #eef2ff;
            --fc-neutral-bg-color: #f8fafc;
        }

        .fc {
            font-family: 'Inter', sans-serif;
        }

        .fc .fc-toolbar-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e1b4b;
        }

        .fc .fc-button {
            background-color: #ffffff;
            border: 1px solid #e0e7ff;
            color: #4f46e5;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-transform: capitalize;
        }

        .fc .fc-button:hover {
            background-color: #eef2ff;
            border-color: #c7d2fe;
            color: #4338ca;
        }

        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            background-color: #4f46e5;
            border-color: #4f46e5;
            color: #ffffff;
        }

        .fc .fc-button-primary:focus {
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.3);
        }

        .fc-theme-standard .fc-scrollgrid {
            border-radius: 0.75rem;
            overflow: hidden;
            border: 1px solid #e0e7ff;
        }

        .fc .fc-col-header-cell {
            background-color: #f8fafc;
            padding: 0.75rem 0;
        }

        .fc .fc-col-header-cell-cushion {
            color: #4f46e5;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .fc .fc-daygrid-day-number,
        .fc .fc-timegrid-slot-label {
            color: #6366f1;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .fc .fc-timegrid-slot {
            height: 3rem;
        }

        .fc-timegrid-slot-label-cushion {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .fc .fc-event {
            border-radius: 0.375rem;
            border: none;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .fc .fc-event:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .fc .fc-event-main {
            padding: 2px 4px;
        }

        .fc .fc-event-title {
            font-weight: 600;
        }

        .fc .fc-event-time {
            font-weight: 500;
            opacity: 0.9;
        }

        .fc .fc-day-today {
            background-color: #f5f3ff !important;
        }

        .fc .fc-timegrid-now-indicator-line {
            border-color: #ef4444;
            border-width: 2px;
        }

        .fc .fc-timegrid-now-indicator-arrow {
            border-color: #ef4444;
        }

        /* View Toggle Buttons */
        .view-toggle-btn {
            transition: all 0.2s ease;
        }

        .view-toggle-btn.active {
            background-color: #4f46e5;
            color: white;
            border-color: #4f46e5;
        }

        /* Loading state */
        .calendar-loading {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
    </style>
@endpush

@section('content')
    <div class="space-y-4" x-data="appointmentsCalendar()">
        <!-- Header Controls -->
        <div
            class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white rounded-xl shadow-sm border border-indigo-100 p-4">
            <!-- Left: Navigation -->
            <div class="flex items-center gap-3">
                <button @click="calendar.prev()"
                    class="p-2 hover:bg-indigo-50 rounded-lg text-indigo-500 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </button>
                <button @click="calendar.today()"
                    class="px-4 py-2 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">
                    Today
                </button>
                <button @click="calendar.next()"
                    class="p-2 hover:bg-indigo-50 rounded-lg text-indigo-500 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
                <span class="ml-2 text-lg font-bold text-indigo-900" x-text="currentTitle"></span>
            </div>

            <!-- Center: View Toggle -->
            <div class="flex bg-gray-100 rounded-lg p-1 gap-1">
                <button @click="changeView('dayGridMonth')" :class="currentView === 'dayGridMonth' ? 'active' : ''"
                    class="view-toggle-btn px-4 py-2 text-sm font-semibold rounded-md text-indigo-600 hover:bg-indigo-50 transition-colors">
                    Month
                </button>
                <button @click="changeView('timeGridWeek')" :class="currentView === 'timeGridWeek' ? 'active' : ''"
                    class="view-toggle-btn px-4 py-2 text-sm font-semibold rounded-md text-indigo-600 hover:bg-indigo-50 transition-colors">
                    Week
                </button>
                <button @click="changeView('timeGridDay')" :class="currentView === 'timeGridDay' ? 'active' : ''"
                    class="view-toggle-btn px-4 py-2 text-sm font-semibold rounded-md text-indigo-600 hover:bg-indigo-50 transition-colors">
                    Day
                </button>
            </div>

            <!-- Right: New Appointment -->
            <button @click="openNewAppointmentModal()"
                class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-bold shadow-sm transition-colors text-sm flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                New Appointment
            </button>
        </div>

        @if(session('success'))
            <div class="bg-green-50 text-green-700 p-4 rounded-lg border border-green-200 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 text-red-700 p-4 rounded-lg border border-red-200">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Calendar Container -->
        <div class="bg-white rounded-xl shadow-sm border border-indigo-100 p-4 relative min-h-[700px]">
            <div id="calendar"></div>

            <!-- Loading Overlay -->
            <div x-show="loading" class="calendar-loading" x-cloak>
                <div class="flex flex-col items-center gap-2">
                    <svg class="animate-spin h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    <span class="text-sm text-indigo-600 font-medium">Loading...</span>
                </div>
            </div>
        </div>

        <!-- Appointment Details Modal -->
        <div x-show="detailsModalOpen" x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="detailsModalOpen = false"></div>
            <div class="flex min-h-screen items-center justify-center p-4">
                <div x-show="detailsModalOpen" x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl p-6">

                    <!-- Header -->
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-indigo-900" x-text="selectedEvent?.title || 'Appointment'">
                            </h3>
                            <p class="text-sm text-indigo-500 mt-1" x-text="selectedEvent?.extendedProps?.type_label"></p>
                        </div>
                        <span :class="getStatusClasses(selectedEvent?.extendedProps?.status)"
                            class="px-3 py-1 text-xs font-bold rounded-full uppercase"
                            x-text="selectedEvent?.extendedProps?.status"></span>
                    </div>

                    <!-- Details -->
                    <div class="space-y-4">
                        <div class="bg-indigo-50 rounded-xl p-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-lg font-bold text-indigo-900" x-text="formatEventTime(selectedEvent)">
                                    </p>
                                    <p class="text-sm text-indigo-600" x-text="formatEventDate(selectedEvent)"></p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-bold mb-1">Customer</p>
                                <p class="text-sm font-semibold text-gray-900"
                                    x-text="selectedEvent?.extendedProps?.customer_name || 'Unknown'"></p>
                                <p class="text-sm text-gray-500" x-text="selectedEvent?.extendedProps?.customer_phone"></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-bold mb-1">Vehicle</p>
                                <p class="text-sm text-gray-900"
                                    x-text="selectedEvent?.extendedProps?.vehicle || 'Not specified'"></p>
                            </div>
                        </div>

                        <div x-show="selectedEvent?.extendedProps?.notes">
                            <p class="text-xs text-gray-500 uppercase font-bold mb-1">Notes</p>
                            <p class="text-sm text-gray-600 italic" x-text="selectedEvent?.extendedProps?.notes"></p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 grid grid-cols-3 gap-3"
                        x-show="selectedEvent?.extendedProps?.status === 'scheduled' || selectedEvent?.extendedProps?.status === 'confirmed'">
                        <form :action="'/appointments/' + selectedEvent?.id" method="POST">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="status" value="completed">
                            <button type="submit"
                                class="w-full py-2.5 bg-green-600 text-white rounded-lg font-semibold text-sm hover:bg-green-700 transition-colors">
                                Mark Done
                            </button>
                        </form>
                        <button @click="openEditModal()"
                            class="w-full py-2.5 border border-indigo-200 text-indigo-700 rounded-lg font-semibold text-sm hover:bg-indigo-50 transition-colors">
                            Edit
                        </button>
                        <form :action="'/appointments/' + selectedEvent?.id" method="POST"
                            onsubmit="return confirm('Delete this appointment?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="w-full py-2.5 border border-red-200 text-red-600 rounded-lg font-semibold text-sm hover:bg-red-50 transition-colors">
                                Delete
                            </button>
                        </form>
                    </div>

                    <button @click="detailsModalOpen = false"
                        class="mt-4 w-full py-2.5 bg-gray-100 text-gray-700 rounded-lg font-semibold text-sm hover:bg-gray-200 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- New/Edit Appointment Modal -->
        <div x-show="formModalOpen" x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
            <div class="fixed inset-0 bg-black/50" @click="formModalOpen = false"></div>
            <div class="flex min-h-screen items-center justify-center p-4">
                <div x-show="formModalOpen" x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="relative w-full max-w-md bg-white rounded-xl shadow-xl">

                    <!-- Header -->
                    <div class="p-6 border-b border-gray-100">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-gray-900"
                                x-text="isEditing ? 'Edit Appointment' : 'New Appointment'"></h3>
                            <button type="button" @click="formModalOpen = false" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <form :action="formAction" method="POST" class="p-6 space-y-5">
                        @csrf
                        <input type="hidden" name="_method" :value="isEditing ? 'PUT' : 'POST'">

                        <!-- Customer -->
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Customer
                                *</label>

                            <!-- Toggle Buttons -->
                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <button type="button" @click="customerMode = 'new'" :class="customerMode === 'new' 
                                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700' 
                                                : 'border-gray-200 bg-white text-gray-500 hover:bg-gray-50'"
                                    class="py-2.5 px-4 rounded-lg border text-sm font-semibold transition-all">
                                    New Customer
                                </button>
                                <button type="button" @click="customerMode = 'existing'" :class="customerMode === 'existing' 
                                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700' 
                                                : 'border-gray-200 bg-white text-gray-500 hover:bg-gray-50'"
                                    class="py-2.5 px-4 rounded-lg border text-sm font-semibold transition-all">
                                    Existing Customer
                                </button>
                            </div>

                            <!-- New Customer Fields -->
                            <div x-show="customerMode === 'new'" x-transition class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <input type="text" name="new_customer_name" x-model="formData.new_customer_name"
                                        placeholder="Full Name" :required="customerMode === 'new'"
                                        class="block w-full rounded-lg border border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3 placeholder:text-gray-400">
                                    <input type="tel" name="new_customer_phone" x-model="formData.new_customer_phone"
                                        placeholder="Phone Number" :required="customerMode === 'new'"
                                        class="block w-full rounded-lg border border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3 placeholder:text-gray-400">
                                </div>
                            </div>

                            <!-- Existing Customer Dropdown -->
                            <div x-show="customerMode === 'existing'" x-transition>
                                <select name="customer_id" x-model="formData.customer_id"
                                    :required="customerMode === 'existing'"
                                    class="block w-full rounded-lg border border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3">
                                    <option value="">Select customer...</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }} - {{ $customer->phone }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Date & Time -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Date
                                    *</label>
                                <input type="date" name="start_date" x-model="formData.start_date" required
                                    class="block w-full rounded-lg border border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Time
                                    *</label>
                                <input type="time" name="start_time" x-model="formData.start_time" required
                                    class="block w-full rounded-lg border border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3">
                            </div>
                        </div>

                        <!-- Type & Duration -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Service
                                    Type</label>
                                <select name="type" x-model="formData.type"
                                    class="block w-full rounded-lg border border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3">
                                    <option value="tire_change">Tire Change</option>
                                    <option value="service">General Service</option>
                                    <option value="repair">Repair</option>
                                    <option value="inspection">Inspection</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Duration</label>
                                <select name="duration" x-model="formData.duration"
                                    class="block w-full rounded-lg border border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2.5 px-3">
                                    <option value="30">30 min</option>
                                    <option value="60">1 Hour</option>
                                    <option value="90">1.5 Hours</option>
                                    <option value="120">2 Hours</option>
                                </select>
                            </div>
                        </div>

                        <input type="hidden" name="status" value="scheduled">

                        <!-- Notes -->
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Notes</label>
                            <textarea name="notes" x-model="formData.notes" rows="3"
                                class="block w-full rounded-lg border border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm placeholder:text-gray-400 py-2.5 px-3"
                                placeholder="Add any notes..."></textarea>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="formModalOpen = false"
                                class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 hover:text-gray-800 transition-colors">
                                Cancel
                            </button>
                            <button type="submit"
                                class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 shadow-sm transition-colors">
                                <span x-text="isEditing ? 'Update' : 'Book Appointment'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    <script>
        function appointmentsCalendar() {
            return {
                calendar: null,
                loading: false,
                currentView: 'timeGridWeek',
                currentTitle: '',
                detailsModalOpen: false,
                formModalOpen: false,
                selectedEvent: null,
                isEditing: false,
                formAction: '{{ route("appointments.store") }}',
                customerMode: 'new',
                formData: {
                    customer_id: '',
                    new_customer_name: '',
                    new_customer_phone: '',
                    start_date: '',
                    start_time: '09:00',
                    type: 'service',
                    duration: '60',
                    notes: ''
                },

                init() {
                    const calendarEl = document.getElementById('calendar');
                    const self = this;

                    this.calendar = new FullCalendar.Calendar(calendarEl, {
                        initialView: 'timeGridWeek',
                        headerToolbar: false, // Using custom header
                        height: 'auto',
                        slotMinTime: '07:00:00',
                        slotMaxTime: '20:00:00',
                        slotDuration: '00:30:00',
                        allDaySlot: false,
                        weekends: true,
                        hiddenDays: [0], // Hide Sunday
                        nowIndicator: true,
                        editable: true,
                        droppable: true,
                        eventStartEditable: true,
                        eventDurationEditable: true,

                        events: {
                            url: '{{ route("appointments.events") }}',
                            failure: function () {
                                console.error('Error loading appointments');
                            }
                        },

                        loading: function (isLoading) {
                            self.loading = isLoading;
                        },

                        datesSet: function (dateInfo) {
                            self.currentTitle = dateInfo.view.title;
                            self.currentView = dateInfo.view.type;
                        },

                        eventClick: function (info) {
                            self.selectedEvent = {
                                id: info.event.id,
                                title: info.event.title,
                                start: info.event.start,
                                end: info.event.end,
                                extendedProps: info.event.extendedProps
                            };
                            self.detailsModalOpen = true;
                        },

                        dateClick: function (info) {
                            self.formData.start_date = info.dateStr.split('T')[0];
                            if (info.dateStr.includes('T')) {
                                self.formData.start_time = info.dateStr.split('T')[1].substring(0, 5);
                            }
                            self.isEditing = false;
                            self.formAction = '{{ route("appointments.store") }}';
                            self.formModalOpen = true;
                        },

                        eventDrop: function (info) {
                            self.rescheduleEvent(info.event);
                        },

                        eventResize: function (info) {
                            self.rescheduleEvent(info.event);
                        },

                        eventDidMount: function (info) {
                            // Add tooltip
                            const props = info.event.extendedProps;
                            info.el.title = `${info.event.title}\n${props.type_label}\n${props.vehicle || 'No vehicle'}`;
                        }
                    });

                    this.calendar.render();
                    this.currentTitle = this.calendar.view.title;
                },

                changeView(view) {
                    this.calendar.changeView(view);
                    this.currentView = view;
                },

                openNewAppointmentModal() {
                    this.customerMode = 'new';
                    // Keep date/time if already set via dateClick
                    const existingDate = this.formData.start_date || new Date().toISOString().split('T')[0];
                    const existingTime = this.formData.start_time || '09:00';

                    this.formData = {
                        customer_id: '',
                        new_customer_name: '',
                        new_customer_phone: '',
                        start_date: existingDate,
                        start_time: existingTime,
                        type: 'service',
                        duration: '60',
                        notes: ''
                    };
                    this.isEditing = false;
                    this.formAction = '{{ route("appointments.store") }}';
                    this.formModalOpen = true;
                },

                openEditModal() {
                    if (!this.selectedEvent) return;

                    const event = this.selectedEvent;
                    const startDate = new Date(event.start);

                    this.customerMode = 'existing';

                    this.formData = {
                        customer_id: event.extendedProps.customer_id,
                        new_customer_name: '',
                        new_customer_phone: '',
                        start_date: startDate.toISOString().split('T')[0],
                        start_time: startDate.toTimeString().substring(0, 5),
                        type: event.extendedProps.type,
                        duration: event.extendedProps.duration || '60',
                        notes: event.extendedProps.notes || ''
                    };

                    this.isEditing = true;
                    this.formAction = '/appointments/' + event.id;
                    this.detailsModalOpen = false;
                    this.formModalOpen = true;
                },

                async rescheduleEvent(event) {
                    try {
                        const response = await fetch(`/api/appointments/${event.id}/reschedule`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                start: event.start.toISOString(),
                                end: event.end ? event.end.toISOString() : null
                            })
                        });

                        if (!response.ok) {
                            throw new Error('Failed to reschedule');
                        }
                    } catch (error) {
                        console.error('Reschedule error:', error);
                        this.calendar.refetchEvents();
                    }
                },

                formatEventTime(event) {
                    if (!event || !event.start) return '';
                    const start = new Date(event.start);
                    const end = event.end ? new Date(event.end) : null;
                    const timeStr = start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    if (end) {
                        return timeStr + ' - ' + end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    }
                    return timeStr;
                },

                formatEventDate(event) {
                    if (!event || !event.start) return '';
                    return new Date(event.start).toLocaleDateString([], {
                        weekday: 'long',
                        month: 'long',
                        day: 'numeric',
                        year: 'numeric'
                    });
                },

                getStatusClasses(status) {
                    const classes = {
                        'scheduled': 'bg-indigo-100 text-indigo-800',
                        'confirmed': 'bg-purple-100 text-purple-800',
                        'completed': 'bg-green-100 text-green-800',
                        'failed': 'bg-red-100 text-red-800',
                        'cancelled': 'bg-gray-100 text-gray-800'
                    };
                    return classes[status] || classes['scheduled'];
                }
            };
        }
    </script>
@endpush