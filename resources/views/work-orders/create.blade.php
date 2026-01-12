@extends('layouts.app')

@section('content')
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="sm:flex sm:items-center">
            <div class="sm:flex-auto">
                <h1 class="text-2xl font-semibold leading-6 text-indigo-950">Schedule New Work Order</h1>
                <p class="mt-2 text-sm text-indigo-700">Create a new work order specifically for future jobs.</p>
            </div>
            <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                <a href="{{ route('dashboard') }}"
                    class="block rounded-md bg-white px-3 py-2 text-center text-sm font-semibold text-indigo-900 shadow-sm ring-1 ring-inset ring-indigo-200 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Cancel
                </a>
            </div>
        </div>

        <div class="mt-8 flow-root">
            <div class="bg-white shadow-sm ring-1 ring-indigo-100 sm:rounded-xl md:col-span-2">
                <form action="{{ route('work-orders.store') }}" method="POST">
                    @csrf
                    <div class="px-4 py-6 sm:p-8">
                        <div class="grid max-w-2xl grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">

                            <!-- Customer Selection -->
                            <div class="sm:col-span-3">
                                <label for="customer_id"
                                    class="block text-sm font-medium leading-6 text-indigo-950">Customer</label>
                                <div class="mt-2">
                                    <select id="customer_id" name="customer_id"
                                        class="block w-full rounded-md border-0 py-1.5 px-3 text-indigo-950 shadow-sm ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                        required onchange="loadVehicles(this.value)">
                                        <option value="">Select a customer</option>
                                        @foreach($customers as $customer)
                                            <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('customer_id')
                                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Vehicle Selection -->
                            <div class="sm:col-span-3">
                                <label for="vehicle_id"
                                    class="block text-sm font-medium leading-6 text-indigo-950">Vehicle</label>
                                <div class="mt-2">
                                    <select id="vehicle_id" name="vehicle_id"
                                        class="block w-full rounded-md border-0 py-1.5 px-3 text-indigo-950 shadow-sm ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                        required disabled>
                                        <option value="">Select a customer first</option>
                                    </select>
                                    @error('vehicle_id')
                                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Scheduled Date -->
                            <div class="sm:col-span-3">
                                <label for="scheduled_at"
                                    class="block text-sm font-medium leading-6 text-indigo-950">Schedule Date & Time</label>
                                <div class="mt-2">
                                    <input type="datetime-local" name="scheduled_at" id="scheduled_at"
                                        class="block w-full rounded-md border-0 py-1.5 px-3 text-indigo-950 shadow-sm ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                        required value="{{ old('scheduled_at') }}">
                                    @error('scheduled_at')
                                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Estimated Duration -->
                            <div class="sm:col-span-3">
                                <label for="estimated_minutes"
                                    class="block text-sm font-medium leading-6 text-indigo-950">Estimated Duration
                                    (minutes)</label>
                                <div class="mt-2">
                                    <input type="number" name="estimated_minutes" id="estimated_minutes"
                                        class="block w-full rounded-md border-0 py-1.5 px-3 text-indigo-950 shadow-sm ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                        value="{{ old('estimated_minutes', 60) }}">
                                </div>
                            </div>

                            <!-- Service Bay -->
                            <div class="sm:col-span-3">
                                <label for="service_bay" class="block text-sm font-medium leading-6 text-indigo-950">Service
                                    Bay</label>
                                <div class="mt-2">
                                    <select id="service_bay" name="service_bay"
                                        class="block w-full rounded-md border-0 py-1.5 px-3 text-indigo-950 shadow-sm ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                        <option value="">Any Bay</option>
                                        @foreach(range(1, 6) as $bay)
                                            <option value="{{ $bay }}" {{ old('service_bay') == $bay ? 'selected' : '' }}>Bay
                                                {{ $bay }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Technician -->
                            <div class="sm:col-span-3">
                                <label for="technician_id"
                                    class="block text-sm font-medium leading-6 text-indigo-950">Assign Technician</label>
                                <div class="mt-2">
                                    <select id="technician_id" name="technician_id"
                                        class="block w-full rounded-md border-0 py-1.5 px-3 text-indigo-950 shadow-sm ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                        <option value="">Unassigned</option>
                                        @foreach($technicians as $tech)
                                            @php $isBusy = in_array($tech->id, $busy_technician_ids ?? []); @endphp
                                            <option value="{{ $tech->id }}" {{ old('technician_id') == $tech->id ? 'selected' : '' }} {{ $isBusy ? 'disabled' : '' }}>
                                                {{ $tech->name }}{{ $isBusy ? ' (Busy)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="col-span-full">
                                <label for="service_description"
                                    class="block text-sm font-medium leading-6 text-indigo-950">Service Requirements /
                                    Issues</label>
                                <div class="mt-2">
                                    <textarea id="service_description" name="service_description" rows="3"
                                        class="block w-full rounded-md border-0 py-1.5 px-3 text-indigo-950 shadow-sm ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                        required>{{ old('service_description') }}</textarea>
                                </div>
                                <p class="mt-3 text-sm leading-6 text-indigo-600">Briefly describe the work requested by the
                                    customer.</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-x-6 border-t border-indigo-100 px-4 py-4 sm:px-8">
                        <button type="button" onclick="window.location='{{ route('dashboard') }}'"
                            class="text-sm font-semibold leading-6 text-indigo-950 hover:text-indigo-700">Cancel</button>
                        <button type="submit"
                            class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Schedule
                            Work Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function loadVehicles(customerId) {
            const vehicleSelect = document.getElementById('vehicle_id');

            if (!customerId) {
                vehicleSelect.innerHTML = '<option value="">Select a customer first</option>';
                vehicleSelect.disabled = true;
                return;
            }

            vehicleSelect.disabled = true;
            vehicleSelect.innerHTML = '<option>Loading...</option>';

            fetch(`/api/vehicles/by-customer/${customerId}`)
                .then(response => response.json())
                .then(data => {
                    vehicleSelect.innerHTML = '<option value="">Select a vehicle</option>';
                    data.forEach(vehicle => {
                        const option = document.createElement('option');
                        option.value = vehicle.id;
                        option.textContent = `${vehicle.make} ${vehicle.model} (${vehicle.license_plate})`;
                        vehicleSelect.appendChild(option);
                    });
                    vehicleSelect.disabled = false;
                })
                .catch(error => {
                    console.error('Error loading vehicles:', error);
                    vehicleSelect.innerHTML = '<option>Error loading vehicles</option>';
                });
        }
    </script>
@endsection