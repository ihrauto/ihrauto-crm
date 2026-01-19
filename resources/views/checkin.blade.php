@extends('layouts.app')

@section('title', 'Check-in')

@section('content')
    <div class="space-y-6">
        <!-- Success/Error Messages -->
        @if(session('success'))
            <div
                class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg flex items-center shadow-sm">
                <svg class="w-5 h-5 mr-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center shadow-sm">
                <svg class="w-5 h-5 mr-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                {{ session('error') }}
            </div>
        @endif

        <!-- Success Notification Bar (Hidden by default) -->
        <div id="success-notification"
            class="fixed top-0 left-0 right-0 z-[9999] transform -translate-y-full transition-transform duration-500 ease-in-out hidden">
            <div class="bg-indigo-900 text-white shadow-2xl border-b border-indigo-700">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between py-4">
                        <div class="flex items-center space-x-4">
                            <div
                                class="w-12 h-12 bg-white/10 rounded-full flex items-center justify-center backdrop-blur-sm">
                                <svg class="w-6 h-6 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white">CHECK-IN COMPLETED</h3>
                                <p class="text-sm text-indigo-200" id="success-message">Service request processed and ticket
                                    created.</p>
                            </div>
                        </div>
                        <button onclick="hideSuccessNotification()"
                            class="text-indigo-300 hover:text-white transition-colors duration-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Action Boxes -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 my-6 lg:my-8">
            <!-- ACTIVE USER Box -->
            <div class="group bg-white border border-indigo-100 rounded-xl p-6 cursor-pointer hover:bg-indigo-900 hover:text-white hover:shadow-xl hover:border-indigo-900 transition-all duration-300 ease-in-out min-h-[160px] flex items-center justify-center text-indigo-900 shadow-sm"
                id="active-user-box">
                <div class="text-center">
                    <div
                        class="w-16 h-16 bg-indigo-50 border border-indigo-200 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-6 group-hover:bg-white/10 group-hover:text-white transition-all duration-300 ease-in-out">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-2 tracking-tight">Existing Customer</h3>
                    <p class="text-base font-medium opacity-60 group-hover:opacity-80">Find and select an registered user
                    </p>
                </div>
            </div>

            <!-- ADD NEW Box -->
            <div class="group bg-indigo-50 border border-indigo-100 rounded-xl p-6 cursor-pointer hover:bg-indigo-900 hover:text-white hover:shadow-xl hover:border-indigo-900 transition-all duration-300 ease-in-out min-h-[160px] flex items-center justify-center text-indigo-900 shadow-sm"
                id="add-new-box">
                <div class="text-center">
                    <div
                        class="w-16 h-16 bg-white border border-indigo-200 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-6 group-hover:bg-white/10 group-hover:text-white transition-all duration-300 ease-in-out shadow-sm">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-2 tracking-tight">New Customer</h3>
                    <p class="text-base font-medium opacity-60 group-hover:opacity-80">Register a new client and vehicle</p>
                </div>
            </div>
        </div>

        <!-- ACTIVE USER Form (Hidden by default) -->
        <div id="active-user-form" class="hidden">
            <div class="bg-white shadow-sm ring-1 ring-indigo-100 sm:rounded-xl overflow-hidden">
                <div class="p-6 border-b border-indigo-100">
                    <h3 class="text-lg font-bold leading-6 text-indigo-950">Find Active Customer</h3>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('checkin.store') }}" class="space-y-6"
                        enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="form_type" value="active_user">

                        <!-- Customer Search -->
                        <div class="mb-6">
                            <label class="text-sm font-medium text-indigo-900 mb-2 block">Search by Car Registration</label>
                            <div class="relative">
                                <input id="customer-search" type="text" placeholder="Enter car registration number..."
                                    class="block w-full rounded-lg border-0 py-2 pl-4 pr-10 text-indigo-900 shadow-sm ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div id="customer-results" class="mt-2 hidden"></div>
                        </div>

                        <!-- Selected Customer Info (Hidden until customer is selected) -->
                        <div id="selected-customer-info" class="hidden">
                            <div class="bg-indigo-50/50 border border-indigo-100 rounded-lg p-4 mb-6">
                                <h3 class="text-sm font-semibold text-indigo-900 mb-2 uppercase tracking-wider">Selected
                                    Customer</h3>
                                <div id="customer-display" class="text-sm text-indigo-700"></div>
                            </div>

                            <!-- Vehicle Selection -->
                            <div class="mb-6">
                                <label class="text-sm font-medium text-indigo-900 mb-2 block">Select Vehicle</label>
                                <select id="vehicle-select" name="vehicle_id"
                                    class="block w-full rounded-lg border-0 py-2 pl-4 pr-10 text-indigo-900 ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                    <option value="">Select a vehicle...</option>
                                </select>
                            </div>

                            <!-- Service Information -->
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
                                <div>
                                    <label class="text-sm font-medium text-indigo-900 mb-1 block">Service Type *</label>
                                    <select name="service_type" required
                                        class="block w-full rounded-lg border-0 py-2 pl-4 pr-10 text-indigo-900 ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                        <option value="">Select service type</option>
                                        @foreach($services as $category => $categoryServices)
                                            <optgroup label="{{ ucfirst($category) }}">
                                                @foreach($categoryServices as $service)
                                                    <option value="{{ $service->name }}">{{ $service->name }}
                                                        ({{ number_format($service->price, 2) }})</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                        <option value="Other">Other</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-indigo-900 mb-1 block">Priority *</label>
                                    <select name="priority" required
                                        class="block w-full rounded-lg border-0 py-2 pl-4 pr-10 text-indigo-900 ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                        <option value="">Select priority</option>
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="text-sm font-medium text-indigo-900 mb-1 block">Service Bay *</label>
                                    <select name="service_bay" required
                                        class="block w-full rounded-lg border-0 py-2 pl-4 pr-10 text-indigo-900 ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                        <option value="">Select service bay</option>
                                        @foreach($service_bays as $bay)
                                            <option value="{{ $bay['name'] }}" {{ $bay['count'] >= 5 ? 'disabled' : '' }}>
                                                {{ $bay['name'] }}{{ $bay['count'] > 0 ? ' • ' . $bay['count'] . ' vehicles' : ' • Available' }}{{ $bay['count'] >= 5 ? ' • FULL' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">Service Description</label>
                                <textarea name="service_description" rows="3"
                                    class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                    placeholder="Describe the service needed..."></textarea>
                            </div>

                            <!-- Technician Assignment -->
                            <div class="mt-4">
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">Assign Technician *</label>
                                <select name="technician_id" required
                                    class="block w-full rounded-lg border-0 py-2 pl-4 pr-10 text-indigo-900 ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                    <option value="">Select Technician</option>
                                    @foreach($users as $user)
                                        @php $isBusy = in_array($user->id, $busy_technician_ids ?? []); @endphp
                                        <option value="{{ $user->id }}" {{ $isBusy ? 'disabled' : '' }}
                                            class="{{ $isBusy ? 'text-gray-400' : '' }}">
                                            {{ $user->name }}{{ $isBusy ? ' (Busy)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Vehicle Photos (Before Service) -->
                            <div class="mt-6">
                                <label class="block text-sm font-medium text-indigo-900 mb-2">Vehicle Condition Photos
                                    (Before Service)</label>
                                <label
                                    class="flex flex-col items-center justify-center w-full h-32 border-2 border-indigo-200 border-dashed rounded-xl cursor-pointer bg-white hover:bg-indigo-50 hover:border-indigo-400 transition-colors group">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <svg class="w-8 h-8 mb-2 text-indigo-300 group-hover:text-indigo-500 transition-colors"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z">
                                            </path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <p class="mb-1 text-sm text-gray-500 group-hover:text-indigo-700">Click to upload
                                            photos</p>
                                        <p class="text-xs text-gray-400" id="active-user-photo-count">Max 5MB each</p>
                                    </div>
                                    <input type="file" name="photos[]" multiple accept="image/*" class="hidden"
                                        onchange="document.getElementById('active-user-photo-count').textContent = this.files.length + ' photo(s) selected'">
                                </label>
                            </div>

                            <!-- Form Actions -->
                            <div class="flex justify-end space-x-4 mt-8">
                                <!-- Cancel Button -->
                                <button type="button"
                                    class="cancel-btn inline-flex items-center px-6 py-3 border border-red-200 text-red-600 rounded-lg font-semibold hover:bg-red-50 transition-all duration-200 shadow-sm">
                                    Cancel
                                </button>

                                <!-- Start Service Button -->
                                <button type="submit"
                                    class="inline-flex items-center px-8 py-3 bg-indigo-900 text-white rounded-lg font-semibold hover:bg-indigo-800 hover:shadow-lg transition-all duration-200 shadow-sm">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Start Service
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ADD NEW Form (Hidden by default) -->
        <div id="add-new-form" class="hidden">
            <!-- Success Notification Bar -->
            <div id="success-notification"
                class="hidden fixed top-4 left-1/2 transform -translate-x-1/2 z-50 w-full max-w-md">
                <div class="bg-indigo-900 text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <p class="font-semibold">Success!</p>
                        <p class="text-sm">Check-in was successfully created and saved.</p>
                    </div>
                </div>
            </div>

            <x-card>
                <!-- Step Progress Indicator -->
                <div class="mb-16">
                    <div class="flex items-center justify-center space-x-8">
                        <!-- Step 1: Customer -->
                        <div class="flex flex-col items-center" id="step-1-indicator">
                            <div
                                class="w-12 h-12 bg-indigo-900 text-white rounded-xl flex items-center justify-center mb-3 shadow-md">
                                <span class="text-lg font-bold">1</span>
                            </div>
                            <span class="text-sm font-bold text-indigo-900">Customer</span>
                        </div>

                        <!-- Connector Line -->
                        <div class="flex-1 h-0.5 bg-indigo-100" id="connector-1"></div>

                        <!-- Step 2: Vehicle -->
                        <div class="flex flex-col items-center" id="step-2-indicator">
                            <div
                                class="w-12 h-12 bg-white text-indigo-300 ring-1 ring-indigo-200 rounded-xl flex items-center justify-center mb-3 shadow-sm">
                                <span class="text-lg font-bold">2</span>
                            </div>
                            <span class="text-sm font-medium text-indigo-300">Vehicle</span>
                        </div>

                        <!-- Connector Line -->
                        <div class="flex-1 h-0.5 bg-indigo-100" id="connector-2"></div>

                        <!-- Step 3: Services -->
                        <div class="flex flex-col items-center" id="step-3-indicator">
                            <div
                                class="w-12 h-12 bg-white text-indigo-300 ring-1 ring-indigo-200 rounded-xl flex items-center justify-center mb-3 shadow-sm">
                                <span class="text-lg font-bold">3</span>
                            </div>
                            <span class="text-sm font-medium text-indigo-300">Services</span>
                        </div>

                        <!-- Connector Line -->
                        <div class="flex-1 h-0.5 bg-indigo-100" id="connector-3"></div>

                        <!-- Step 4: Review -->
                        <div class="flex flex-col items-center" id="step-4-indicator">
                            <div
                                class="w-12 h-12 bg-white text-indigo-300 ring-1 ring-indigo-200 rounded-xl flex items-center justify-center mb-3 shadow-sm">
                                <span class="text-lg font-bold">4</span>
                            </div>
                            <span class="text-sm font-medium text-indigo-300">Review</span>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('checkin.store') }}" class="space-y-4" id="multi-step-form"
                    enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="form_type" value="new_customer">

                    <!-- Step 1: Customer Information -->
                    <div class="step-content" id="step-1">
                        <h3 class="text-lg font-bold text-indigo-950 mb-4">Customer Details</h3>
                        <!-- First Row: Name, Surname, Email -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">Name *</label>
                                <input name="customer_first_name" type="text" required
                                    value="{{ old('customer_first_name') }}" placeholder="First name"
                                    class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                @error('customer_first_name')
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">Surname *</label>
                                <input name="customer_last_name" type="text" required
                                    value="{{ old('customer_last_name') }}" placeholder="Last name"
                                    class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                @error('customer_last_name')
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">Email</label>
                                <input name="email" type="email" value="{{ old('email') }}" placeholder="Email address"
                                    class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                @error('email')
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Second Row: Phone, Postal Code, City/Town, Street Address -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">Phone *</label>
                                <input name="phone" type="tel" required value="{{ old('phone') }}"
                                    placeholder="Phone number"
                                    class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                @error('phone')
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">Postal Code</label>
                                <input name="postal_code" type="text" value="{{ old('postal_code') }}"
                                    placeholder="Zip Code"
                                    class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                @error('postal_code')
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">City / Town</label>
                                <input name="city" type="text" value="{{ old('city') }}" placeholder="City"
                                    class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                @error('city')
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">Street Address</label>
                                <input name="street_address" type="text" value="{{ old('street_address') }}"
                                    placeholder="Address"
                                    class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                @error('street_address')
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Vehicle Information -->
                    <div class="step-content hidden" id="step-2">
                        <h3 class="text-lg font-bold text-indigo-950 mb-4">Vehicle Details</h3>
                        <!-- First Row: License Plate, Brand, Model, Year -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">License Plate *</label>
                                <input name="license_plate" type="text" required value="{{ old('license_plate') }}"
                                    placeholder="Plate number"
                                    class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                @error('license_plate')
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">Brand *</label>
                                <input name="make" type="text" required value="{{ old('make') }}" placeholder="Brand"
                                    class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                @error('make')
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">Model *</label>
                                <input name="model" type="text" required value="{{ old('model') }}" placeholder="Model"
                                    class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                @error('model')
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">Year *</label>
                                <input name="year" type="number" min="1900" max="{{ date('Y') + 1 }}" required
                                    value="{{ old('year') }}" placeholder="Year"
                                    class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                @error('year')
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">Mileage</label>
                                <input name="mileage" type="number" min="0" value="{{ old('mileage') }}" placeholder="KM"
                                    class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                @error('mileage')
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">VIN</label>
                                <input name="vin" type="text" value="{{ old('vin') }}" placeholder="VIN number"
                                    class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                @error('vin')
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1 block">Color</label>
                                <input name="color" type="text" value="{{ old('color') }}" placeholder="Vehicle color"
                                    class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                @error('color')
                                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Vehicle Photos (Before) -->
                            <div class="mt-6">
                                <label class="block text-sm font-medium text-indigo-900 mb-2">Vehicle Condition Photos
                                    (Before Service)</label>
                                <label
                                    class="flex flex-col items-center justify-center w-full h-32 border-2 border-indigo-200 border-dashed rounded-xl cursor-pointer bg-white hover:bg-indigo-50 hover:border-indigo-400 transition-colors group">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <svg class="w-8 h-8 mb-2 text-indigo-300 group-hover:text-indigo-500 transition-colors"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z">
                                            </path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <p class="mb-1 text-sm text-gray-500 group-hover:text-indigo-700">Click to upload
                                            photos</p>
                                        <p class="text-xs text-gray-400" id="photo-count">Max 5MB each</p>
                                    </div>
                                    <input type="file" name="photos[]" multiple accept="image/*" class="hidden"
                                        onchange="document.getElementById('photo-count').textContent = this.files.length + ' photo(s) selected'">
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Service Information -->
                    <div class="step-content hidden" id="step-3">
                        <div class="bg-indigo-50/50 rounded-2xl p-8 border border-indigo-100">
                            <!-- Service Categories -->
                            <div class="mb-6">
                                <label class="text-lg font-bold text-indigo-950 mb-4 block">Select Services *</label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">

                                    <!-- Service Categories Loop -->
                                    @foreach(['Oil Services' => 'oil', 'Brake Services' => 'brakes', 'Tire Services' => 'tires', 'Engine Services' => 'engine', 'Electrical Services' => 'electrical', 'General Maintenance' => 'maintenance'] as $label => $key)
                                        @if(isset($services[$key]))
                                            <div
                                                class="service-category bg-white border border-indigo-100 rounded-lg shadow-sm hover:shadow-md transition-all duration-200">
                                                <div class="service-category-header flex items-center justify-between p-4 cursor-pointer hover:bg-indigo-50 transition-all duration-200 rounded-t-lg"
                                                    data-category="{{ $key }}">
                                                    <span class="font-medium text-indigo-900">{{ $label }}</span>
                                                    <div class="flex items-center space-x-3">
                                                        <span
                                                            class="service-count hidden bg-indigo-100 text-indigo-700 text-xs px-2 py-1 rounded-full font-bold min-w-[20px] text-center">0</span>
                                                        <svg class="w-4 h-4 text-indigo-400 transform transition-transform duration-200 category-arrow"
                                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M19 9l-7 7-7-7"></path>
                                                        </svg>
                                                    </div>
                                                </div>
                                                <div
                                                    class="service-category-content max-h-0 overflow-hidden transition-all duration-300 ease-in-out">
                                                    <div class="p-5 border-t border-indigo-50 bg-white">
                                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                            @foreach($services[$key] as $service)
                                                                <label
                                                                    class="flex items-center space-x-3 cursor-pointer p-3 rounded-lg hover:bg-indigo-50 transition-all duration-200 border border-transparent hover:border-indigo-100">
                                                                    <input type="checkbox" name="services[]"
                                                                        value="{{ $service->name }}"
                                                                        class="service-checkbox w-4 h-4 text-indigo-600 border-indigo-300 rounded focus:ring-indigo-500"
                                                                        data-price="{{ $service->price }}">
                                                                    <div class="flex flex-col">
                                                                        <span
                                                                            class="text-sm font-medium text-indigo-900">{{ $service->name }}</span>
                                                                        <span
                                                                            class="text-xs text-indigo-500">{{ number_format($service->price, 2) }}</span>
                                                                    </div>
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>

                                <!-- Selected Services Display -->
                                <div id="selected-services-display"
                                    class="hidden mt-6 p-4 bg-white border border-indigo-200 rounded-lg shadow-sm">
                                    <h5 class="text-sm font-bold text-indigo-900 mb-3">Selected Services:</h5>
                                    <div id="selected-services-list" class="flex flex-wrap gap-2"></div>
                                </div>
                            </div>

                            <!-- Priority, Service Bay, and Notes -->
                            <div class="mt-8 bg-white rounded-xl border border-indigo-100 p-6 shadow-sm">
                                <h4 class="text-lg font-bold text-indigo-950 mb-6">Additional Information</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
                                    <div>
                                        <label class="text-sm font-medium text-indigo-900 mb-2 block">Priority *</label>
                                        <select name="priority" required
                                            class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                            <option value="">Select priority</option>
                                            <option value="low">Low</option>
                                            <option value="medium">Medium</option>
                                            <option value="high">High</option>
                                            <option value="urgent">Urgent</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="text-sm font-medium text-indigo-900 mb-2 block">Service Bay *</label>
                                        <select name="service_bay" required
                                            class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                            <option value="">Select service bay</option>
                                            @foreach($service_bays as $bay)
                                                <option value="{{ $bay['name'] }}" {{ $bay['count'] >= 5 ? 'disabled' : '' }}>
                                                    {{ $bay['name'] }}{{ $bay['count'] > 0 ? ' • ' . $bay['count'] . ' vehicles' : ' • Available' }}{{ $bay['count'] >= 5 ? ' • FULL' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="text-sm font-medium text-indigo-900 mb-2 block">Assign
                                            Technician *</label>
                                        <select name="technician_id" required
                                            class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                            <option value="">Select Technician</option>
                                            @foreach($users as $user)
                                                @php $isBusy = in_array($user->id, $busy_technician_ids ?? []); @endphp
                                                <option value="{{ $user->id }}" {{ $isBusy ? 'disabled' : '' }}
                                                    class="{{ $isBusy ? 'text-gray-400' : '' }}">
                                                    {{ $user->name }}{{ $isBusy ? ' (Busy)' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="mt-6">
                                    <label class="text-sm font-medium text-indigo-900 mb-2 block">Additional Notes</label>
                                    <textarea name="service_description" rows="3"
                                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                        placeholder="Any additional notes...">{{ old('service_description') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Review -->
                    <div class="step-content hidden" id="step-4">
                        <h3 class="text-lg font-bold text-indigo-950 mb-4">Review Information</h3>
                        <div class="space-y-4">
                            <!-- Customer Review -->
                            <div class="bg-indigo-50/50 p-4 rounded-lg border border-indigo-100">
                                <h4 class="font-semibold text-indigo-900 mb-3 text-sm uppercase tracking-wide">Customer</h4>
                                <div class="grid grid-cols-2 gap-4 text-sm text-indigo-800">
                                    <div><span class="font-medium text-indigo-500">Name:</span> <span
                                            id="review-customer-name">-</span></div>
                                    <div><span class="font-medium text-indigo-500">Phone:</span> <span
                                            id="review-phone">-</span></div>
                                    <div><span class="font-medium text-indigo-500">Email:</span> <span
                                            id="review-email">-</span></div>
                                </div>
                            </div>

                            <!-- Vehicle Review -->
                            <div class="bg-indigo-50/50 p-4 rounded-lg border border-indigo-100">
                                <h4 class="font-semibold text-indigo-900 mb-3 text-sm uppercase tracking-wide">Vehicle</h4>
                                <div class="grid grid-cols-2 gap-4 text-sm text-indigo-800">
                                    <div><span class="font-medium text-indigo-500">Plate:</span> <span
                                            id="review-license-plate">-</span></div>
                                    <div><span class="font-medium text-indigo-500">Make/Model:</span> <span
                                            id="review-make">-</span> <span id="review-model"></span></div>
                                    <div><span class="font-medium text-indigo-500">VIN:</span> <span
                                            id="review-vin">-</span></div>
                                </div>
                            </div>

                            <!-- Service Review -->
                            <div class="bg-indigo-50/50 p-4 rounded-lg border border-indigo-100">
                                <h4 class="font-semibold text-indigo-900 mb-3 text-sm uppercase tracking-wide">Service</h4>
                                <div class="grid grid-cols-2 gap-4 text-sm text-indigo-800">
                                    <div><span class="font-medium text-indigo-500">Type:</span> <span
                                            id="review-service-type">-</span></div>
                                    <div><span class="font-medium text-indigo-500">Bay:</span> <span
                                            id="review-service-bay">-</span></div>
                                    <div class="col-span-2"><span class="font-medium text-indigo-500">Notes:</span> <span
                                            id="review-service-description">-</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-between pt-8 border-t border-indigo-100 mt-8">
                        <div class="flex space-x-4">
                            <!-- Cancel Button -->
                            <button type="button"
                                class="cancel-btn inline-flex items-center px-4 py-2 border border-red-200 text-red-600 rounded-lg text-sm font-medium hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                Cancel
                            </button>

                            <!-- Previous Button -->
                            <button type="button"
                                class="hidden inline-flex items-center px-4 py-2 border border-indigo-200 text-indigo-700 rounded-lg text-sm font-medium hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                id="prev-btn">
                                Back
                            </button>
                        </div>

                        <div>
                            <!-- Next Button -->
                            <button type="button"
                                class="inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-indigo-900 hover:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-900"
                                id="next-btn">
                                Next Step
                                <svg class="ml-2 -mr-1 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                    fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>

                            <!-- Submit Button -->
                            <button type="submit"
                                class="hidden inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-lg shadow-sm text-white bg-indigo-900 hover:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-900"
                                id="submit-btn">
                                Complete Check-in
                            </button>
                        </div>
                    </div>
                </form>
            </x-card>
        </div>

        <!-- Section Separator -->
        <div class="py-8">
            <div class="flex items-center justify-center">
                <div class="flex-grow border-t border-indigo-100"></div>
                <div class="mx-6">
                    <h2 class="text-xs font-bold text-indigo-300 uppercase tracking-widest">Dashboard Overview</h2>
                </div>
                <div class="flex-grow border-t border-indigo-100"></div>
            </div>
        </div>

        <!-- Check-in Information Section -->
        <div class="space-y-6">
            <!-- Check-in Statistics -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
                <x-card class="border-l-4 border-indigo-500 shadow-sm ring-1 ring-indigo-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-indigo-500">Today's Check-ins</p>
                            <p class="text-2xl font-bold text-indigo-900">{{ $checkin_stats['today_checkins'] }}</p>
                        </div>
                    </div>
                </x-card>

                <x-card class="border-l-4 border-indigo-400 shadow-sm ring-1 ring-indigo-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-indigo-500">In Progress</p>
                            <p class="text-2xl font-bold text-indigo-900">{{ $checkin_stats['in_progress'] }}</p>
                        </div>
                    </div>
                </x-card>

                <x-card class="border-l-4 border-indigo-300 shadow-sm ring-1 ring-indigo-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-indigo-500">Completed Today</p>
                            <p class="text-2xl font-bold text-indigo-900">{{ $checkin_stats['completed'] }}</p>
                        </div>
                    </div>
                </x-card>

                <x-card class="border-l-4 border-purple-400 shadow-sm ring-1 ring-indigo-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-indigo-500">Avg. Service Time</p>
                            <p class="text-2xl font-bold text-indigo-900">{{ $checkin_stats['avg_time'] }}</p>
                        </div>
                    </div>
                </x-card>
            </div>

            <!-- Active Check-ins and Service Bays -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
                <div class="bg-white rounded-xl ring-1 ring-indigo-100 p-6 shadow-sm">
                    <div class="mb-4 border-b border-indigo-50 pb-2">
                        <h3 class="text-lg font-bold text-indigo-950">Active Queues</h3>
                    </div>
                    @if($active_checkins->count() > 0)
                        <div class="space-y-4">
                            @foreach($active_checkins as $checkin)
                                <div class="border border-indigo-100 rounded-lg p-4 hover:bg-indigo-50/50 transition-colors">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center space-x-2">
                                            <span
                                                class="text-sm font-bold text-indigo-900">{{ $checkin->vehicle->display_name ?? 'Unknown Vehicle' }}</span>
                                            <span
                                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                                                {{ ucfirst(str_replace('_', ' ', $checkin->status)) }}
                                            </span>
                                        </div>
                                        <span
                                            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                            {{ ucfirst($checkin->priority) }}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-sm">
                                        <p class="text-indigo-900"><span class="text-indigo-400">Owner:</span>
                                            {{ $checkin->customer->name ?? 'Unknown Customer' }}</p>
                                        <p class="text-indigo-900"><span class="text-indigo-400">Bay:</span>
                                            {{ $checkin->service_bay }}</p>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex items-center justify-end space-x-3 mt-4 pt-3 border-t border-indigo-50">
                                        @if($checkin->status !== 'completed' && !$checkin->checkout_time)
                                            <!-- Create Work Order -->
                                            <form method="POST" action="{{ route('work-orders.generate', $checkin) }}" class="inline">
                                                @csrf
                                                <button type="submit"
                                                    class="text-sm font-semibold text-purple-600 hover:text-purple-900 mr-3">
                                                    + Job Sheet
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('checkin.update', $checkin) }}" class="inline">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit"
                                                    class="text-sm font-semibold text-indigo-600 hover:text-indigo-900">
                                                    Mark Complete
                                                </button>
                                            </form>
                                        @elseif($checkin->status === 'completed' && !$checkin->checkout_time)
                                            <form method="POST" action="{{ route('checkin.update', $checkin) }}" class="inline">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="status" value="done">
                                                <input type="hidden" name="checkout_time" value="{{ now() }}">
                                                <button type="submit"
                                                    class="inline-flex items-center px-4 py-2 bg-indigo-900 text-white rounded-lg text-sm font-medium hover:bg-indigo-800">
                                                    Archive
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-indigo-300 text-center py-8">No active check-ins</p>
                    @endif
                </div>

                <x-card class="ring-1 ring-indigo-100 shadow-sm">
                    <div class="flex items-center justify-between mb-4 border-b border-indigo-50 pb-2">
                        <h3 class="text-lg font-bold text-indigo-950">Bay Status</h3>
                        <div class="text-sm text-indigo-400">
                            {{ collect($service_bays)->sum('count') }} vehicles active
                        </div>
                    </div>

                    <!-- Minimal Bay Cards -->
                    <div class="space-y-2">
                        @foreach($service_bays as $bay)
                            <div
                                class="flex items-center justify-between py-2 px-4 bg-indigo-50/30 rounded-lg hover:bg-indigo-50 transition-colors duration-200">
                                <div class="flex items-center space-x-3">
                                    <div
                                        class="w-8 h-8 bg-white border border-indigo-100 rounded-lg flex items-center justify-center text-indigo-600 font-bold">
                                        {{ substr($bay['name'], -1) }}
                                    </div>
                                    <span class="text-sm font-medium text-indigo-900">{{ $bay['name'] }}</span>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $bay['status'] === 'available' ? 'bg-green-100 text-green-800' : 'bg-rose-100 text-rose-800' }}">
                                        <span
                                            class="w-1.5 h-1.5 {{ $bay['status'] === 'available' ? 'bg-green-500' : 'bg-rose-500' }} rounded-full mr-1.5"></span>
                                        {{ $bay['status'] === 'available' ? 'Available' : 'Occupied' }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Basic Toggle Logic for Boxes
            const activeUserBox = document.getElementById('active-user-box');
            const addNewBox = document.getElementById('add-new-box');
            const activeUserForm = document.getElementById('active-user-form');
            const addNewForm = document.getElementById('add-new-form');
            const cancelBtns = document.querySelectorAll('.cancel-btn');

            activeUserBox.addEventListener('click', () => {
                activeUserBox.parentElement.classList.add('hidden'); // Hide the grid container
                activeUserForm.classList.remove('hidden');
            });

            addNewBox.addEventListener('click', () => {
                activeUserBox.parentElement.classList.add('hidden');
                addNewForm.classList.remove('hidden');
            });

            cancelBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    activeUserForm.classList.add('hidden');
                    addNewForm.classList.add('hidden');
                    activeUserBox.parentElement.classList.remove('hidden'); // Show grid

                    // Reset forms
                    if (!addNewForm.classList.contains('hidden')) {
                        // Reset logic here if needed
                    }
                });
            });

            // REOPEN FORM IF VALIDATION FAILED
            @if(old('form_type') == 'new_customer')
                activeUserBox.parentElement.classList.add('hidden');
                addNewForm.classList.remove('hidden');
            @else
                                                // Auto-open Check-in form if URL param is present (from dashboard quick action)
                                                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('openForm') === 'true') {
                    // Open New Customer form directly as requested
                    activeUserBox.parentElement.classList.add('hidden');
                    addNewForm.classList.remove('hidden');
                }
            @endif

                                                                                // EXISTING CUSTOMER SEARCH LOGIC
                                                                                const searchInput = document.getElementById('customer-search');
            const resultsDiv = document.getElementById('customer-results');
            let searchTimeout;

            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    clearTimeout(searchTimeout);
                    const query = this.value;

                    if (query.length < 2) {
                        resultsDiv.classList.add('hidden');
                        return;
                    }

                    searchTimeout = setTimeout(() => {
                        fetch(`/api/customers/search?query=${encodeURIComponent(query)}`)
                            .then(response => response.json())
                            .then(data => {
                                resultsDiv.innerHTML = '';
                                if (data.length > 0) {
                                    resultsDiv.classList.remove('hidden');
                                    const ul = document.createElement('ul');
                                    ul.className = 'bg-white border border-indigo-200 rounded-lg shadow-lg max-h-60 overflow-y-auto divide-y divide-indigo-50';

                                    data.forEach(customer => {
                                        const li = document.createElement('li');
                                        li.className = 'p-3 hover:bg-indigo-50 cursor-pointer transition-colors';
                                        li.innerHTML = `
                                                                                                                                <div class="flex justify-between items-center">
                                                                                                                                    <div>
                                                                                                                                        <p class="font-bold text-indigo-900 text-sm">${customer.name}</p>
                                                                                                                                        <p class="text-xs text-indigo-500">${customer.phone || 'No phone'}</p>
                                                                                                                                    </div>
                                                                                                                                    <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full">Select</span>
                                                                                                                                </div>
                                                                                                                            `;
                                        li.addEventListener('click', () => selectCustomer(customer));
                                        ul.appendChild(li);
                                    });
                                    resultsDiv.appendChild(ul);
                                } else {
                                    resultsDiv.classList.remove('hidden');
                                    resultsDiv.innerHTML = `
                                                                                                                            <div class="bg-white border border-indigo-200 rounded-lg p-3 text-sm text-indigo-500 text-center">
                                                                                                                                No customers found
                                                                                                                            </div>
                                                                                                                        `;
                                }
                            })
                            .catch(error => {
                                console.error('Error fetching customers:', error);
                            });
                    }, 300);
                });
            }

            function selectCustomer(customer) {
                // Populate selected customer info
                const display = document.getElementById('customer-display');
                if (display) {
                    display.innerHTML = `
                                                                                                            <div class="flex justify-between items-center">
                                                                                                                <div>
                                                                                                                    <span class="font-bold block text-lg">${customer.name}</span>
                                                                                                                    <span class="text-xs text-indigo-500">${customer.email || ''} • ${customer.phone || ''}</span>
                                                                                                                </div>
                                                                                                                <input type="hidden" name="customer_id" value="${customer.id}">
                                                                                                            </div>
                                                                                                        `;
                }

                document.getElementById('selected-customer-info').classList.remove('hidden');
                document.getElementById('customer-results').classList.add('hidden');
                document.getElementById('customer-search').value = customer.name;

                // Load vehicles for this customer
                loadVehicles(customer.id);
            }

            function loadVehicles(customerId) {
                const vehicleSelect = document.getElementById('vehicle-select');
                if (!vehicleSelect) return;

                vehicleSelect.innerHTML = '<option value="">Loading vehicles...</option>';

                fetch(`/api/vehicles/by-customer/${customerId}`)
                    .then(r => r.json())
                    .then(vehicles => {
                        vehicleSelect.innerHTML = '<option value="">Select a vehicle...</option>';
                        if (vehicles.length > 0) {
                            vehicles.forEach(v => {
                                const option = document.createElement('option');
                                option.value = v.id;
                                option.textContent = `${v.make} ${v.model} (${v.license_plate})`;
                                vehicleSelect.appendChild(option);
                            });
                        } else {
                            const option = document.createElement('option');
                            option.disabled = true;
                            option.textContent = 'No vehicles found for this customer';
                            vehicleSelect.appendChild(option);
                        }
                    })
                    .catch(e => {
                        console.error('Error loading vehicles:', e);
                        vehicleSelect.innerHTML = '<option value="">Error loading vehicles</option>';
                    });
            }

            // Simple Multi-step Logic
            const nextBtn = document.getElementById('next-btn');
            const prevBtn = document.getElementById('prev-btn');
            const submitBtn = document.getElementById('submit-btn');
            let currentStep = 1;

            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    // VALIDATION CHECK
                    const currentStepContainer = document.getElementById(`step-${currentStep}`);
                    const inputs = currentStepContainer.querySelectorAll('input, select, textarea');
                    let isValid = true;

                    for (const input of inputs) {
                        if (!input.checkValidity()) {
                            isValid = false;
                            input.reportValidity(); // Show the pop-up
                            return; // Stop immediately to let user fix it
                        }
                    }

                    // Special Check for Step 3 (Services)
                    if (currentStep === 3) {
                        const checkedServices = document.querySelectorAll('input[name="services[]"]:checked');
                        if (checkedServices.length === 0) {
                            alert('Please select at least one service.'); // Simple fallback
                            return;
                        }
                    }

                    // Proceed if valid
                    currentStepContainer.classList.add('hidden');
                    document.getElementById(`step-${currentStep}-indicator`).querySelector('div').classList.remove('bg-indigo-900', 'text-white');
                    document.getElementById(`step-${currentStep}-indicator`).querySelector('div').classList.add('bg-indigo-50', 'text-indigo-600');

                    currentStep++;
                    document.getElementById(`step-${currentStep}`).classList.remove('hidden');

                    // Update indicator
                    const nextIndicator = document.getElementById(`step-${currentStep}-indicator`).querySelector('div');
                    nextIndicator.classList.remove('bg-white', 'text-indigo-300');
                    nextIndicator.classList.add('bg-indigo-900', 'text-white');

                    if (currentStep > 1) prevBtn.classList.remove('hidden');

                    if (currentStep === 4) {
                        nextBtn.style.display = 'none'; // Force hide
                        submitBtn.classList.remove('hidden');
                        submitBtn.style.display = 'inline-flex'; // Ensure visible
                        populateReview();
                    }
                });
            }

            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    document.getElementById(`step-${currentStep}`).classList.add('hidden');
                    const currentIndicator = document.getElementById(`step-${currentStep}-indicator`).querySelector('div');
                    currentIndicator.classList.remove('bg-indigo-900', 'text-white');
                    currentIndicator.classList.add('bg-white', 'text-indigo-300');

                    currentStep--;
                    document.getElementById(`step-${currentStep}`).classList.remove('hidden');

                    // Update indicator
                    const prevIndicator = document.getElementById(`step-${currentStep}-indicator`).querySelector('div');
                    prevIndicator.classList.remove('bg-indigo-50', 'text-indigo-600');
                    prevIndicator.classList.add('bg-indigo-900', 'text-white');

                    if (currentStep === 1) prevBtn.classList.add('hidden');

                    if (currentStep < 4) {
                        nextBtn.style.display = 'inline-flex'; // Restore
                        submitBtn.classList.add('hidden');
                        submitBtn.style.display = 'none';
                    }
                });
            }

            // Service Category Expansion
            document.querySelectorAll('.service-category-header').forEach(header => {
                header.addEventListener('click', () => {
                    const content = header.nextElementSibling;
                    const arrow = header.querySelector('.category-arrow');

                    if (content.style.maxHeight) {
                        // Closing the active one
                        content.style.maxHeight = null;
                        arrow.style.transform = 'rotate(0deg)';

                        // Reset styles: remove active dark blue/white, add back original text color and hover effect
                        header.classList.remove('bg-indigo-900', 'text-white');
                        header.classList.add('hover:bg-indigo-50');

                        header.querySelector('span').classList.remove('text-white');
                        header.querySelector('span').classList.add('text-indigo-900');
                    } else {
                        // Close others
                        document.querySelectorAll('.service-category-content').forEach(c => c.style.maxHeight = null);
                        document.querySelectorAll('.service-category-header').forEach(h => {
                            // Reset other headers
                            h.classList.remove('bg-indigo-900', 'text-white');
                            h.classList.add('hover:bg-indigo-50'); // Ensure hover is back for others

                            h.querySelector('span').classList.remove('text-white');
                            h.querySelector('span').classList.add('text-indigo-900');
                            h.querySelector('.category-arrow').style.transform = 'rotate(0deg)';
                        });

                        // Open this one
                        content.style.maxHeight = content.scrollHeight + "px";
                        arrow.style.transform = 'rotate(180deg)';

                        // Set active styles: Add dark blue/white, REMOVE hover light blue
                        header.classList.add('bg-indigo-900', 'text-white');
                        header.classList.remove('hover:bg-indigo-50'); // Remove hover to prevent flickering/invisible text

                        header.querySelector('span').classList.remove('text-indigo-900');
                        header.querySelector('span').classList.add('text-white');
                    }
                });
            });

            function populateReview() {
                // Customer
                const fname = document.querySelector('[name="customer_first_name"]').value || '';
                const lname = document.querySelector('[name="customer_last_name"]').value || '';
                document.getElementById('review-customer-name').innerText = fname + ' ' + lname;
                document.getElementById('review-phone').innerText = document.querySelector('[name="phone"]').value || '-';
                document.getElementById('review-email').innerText = document.querySelector('[name="email"]').value || '-';

                // Vehicle
                document.getElementById('review-license-plate').innerText = document.querySelector('[name="license_plate"]').value || '-';
                document.getElementById('review-make').innerText = document.querySelector('[name="make"]').value || '-';
                document.getElementById('review-model').innerText = document.querySelector('[name="model"]').value || '';
                document.getElementById('review-vin').innerText = document.querySelector('[name="vin"]').value || '-';

                // Services
                const selectedServices = Array.from(document.querySelectorAll('input[name="services[]"]:checked'))
                    .map(cb => {
                        // Map values to human readable text if needed, or use a data attribute label
                        // For now we try to map common values or fallback to title case
                        const val = cb.value.replace(/_/g, ' ');
                        return val.charAt(0).toUpperCase() + val.slice(1);
                    });

                document.getElementById('review-service-type').innerText = selectedServices.length > 0 ? selectedServices.join(', ') : 'None selected';

                // Service Details
                // Step 3 repeats priority/bay fields, we need to grab the ones from step 3 container if they exist there, 
                // or ensure we select the correct inputs. 
                // In the HTML structure, Priority/Bay are in step-3.

                const step3 = document.getElementById('step-3');
                const prioritySelect = step3.querySelector('select[name="priority"]');
                const baySelect = step3.querySelector('select[name="service_bay"]');
                const notes = step3.querySelector('textarea[name="service_description"]');

                if (prioritySelect) document.getElementById('review-service-type').innerText += ` (${prioritySelect.value} Priority)`;
                if (baySelect) document.getElementById('review-service-bay').innerText = baySelect.value || '-';
                if (notes) document.getElementById('review-service-description').innerText = notes.value || '-';
            }
        });
    </script>
@endsection