@extends('layouts.app')

@section('title', 'Tire Hotel')

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



        <!-- Main Action Boxes -->
        <div id="action-boxes" class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 my-6 lg:my-8">
            <!-- IN HOTEL Box -->
            <div class="group bg-white border border-indigo-100 rounded-xl p-6 cursor-pointer hover:bg-indigo-900 hover:text-white hover:shadow-xl hover:border-indigo-900 transition-all duration-300 ease-in-out min-h-[160px] flex items-center justify-center text-indigo-900 shadow-sm"
                id="in-hotel-box">
                <div class="text-center">
                    <div
                        class="w-16 h-16 bg-indigo-50 border border-indigo-200 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-6 group-hover:bg-white/10 group-hover:text-white transition-all duration-300 ease-in-out">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-2 tracking-tight">Retrieve Tires</h3>
                    <p class="text-base font-medium opacity-60 group-hover:opacity-80">Find stored tires for active
                        customers</p>
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
                    <h3 class="text-2xl font-bold mb-2 tracking-tight">Store Tires</h3>
                    <p class="text-base font-medium opacity-60 group-hover:opacity-80">Register new tires into storage</p>
                </div>
            </div>
        </div>

        <!-- =========================================
                                                                     INLINE FORM: Store New Tires (Hidden by default)
                                                                     ========================================= -->
        <div id="store-new-form" class="hidden" x-data="tireStorageForm()">
            <x-card>
                <!-- Header with Cancel -->
                <div class="absolute right-6 top-6 z-10">
                    <button type="button" id="cancel-store-form"
                        class="text-gray-400 hover:text-gray-600 transition-colors p-2 rounded-full hover:bg-gray-100">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                    </button>
                </div>

                <!-- Step Progress Indicator -->
                <div class="mb-6 mt-2">
                    <div class="flex items-center justify-center space-x-8">
                        <div class="flex flex-col items-center">
                            <div :class="step >= 1 ? 'bg-indigo-900 text-white shadow-md' : 'bg-white text-indigo-300 ring-1 ring-indigo-200'"
                                class="w-12 h-12 rounded-xl flex items-center justify-center mb-3 transition-all duration-300">
                                <span class="text-lg font-bold">1</span>
                            </div>
                            <span :class="step >= 1 ? 'text-indigo-900 font-bold' : 'text-indigo-300 font-medium'"
                                class="text-sm">Customer & Vehicle</span>
                        </div>

                        <div class="flex-1 h-0.5 bg-indigo-100 max-w-[100px]">
                            <div class="h-full bg-indigo-600 transition-all duration-300"
                                :style="'width: ' + (step > 1 ? '100%' : '0%')"></div>
                        </div>

                        <div class="flex flex-col items-center">
                            <div :class="step >= 2 ? 'bg-indigo-900 text-white shadow-md' : 'bg-white text-indigo-300 ring-1 ring-indigo-200'"
                                class="w-12 h-12 rounded-xl flex items-center justify-center mb-3 transition-all duration-300">
                                <span class="text-lg font-bold">2</span>
                            </div>
                            <span :class="step >= 2 ? 'text-indigo-900 font-bold' : 'text-indigo-300 font-medium'"
                                class="text-sm">Tire Details & Storage</span>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('tires-hotel.store') }}" class="space-y-6">
                    @csrf

                    <!-- Step 1: Customer & Vehicle Information -->
                    <div x-show="step === 1" x-transition>
                        <div class="bg-white rounded-xl shadow-sm ring-1 ring-indigo-100 p-6">


                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="text-sm font-medium text-indigo-900 mb-2 block">Full Name *</label>
                                    <input type="text" name="customer_name" x-model="customer_name" placeholder="John Doe"
                                        required
                                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 transition-all bg-indigo-50/30 focus:bg-white">
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-indigo-900 mb-2 block">Phone Number</label>
                                    <input type="tel" name="customer_phone" placeholder="+383 4X XXX XXX"
                                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 transition-all bg-indigo-50/30 focus:bg-white">
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-indigo-900 mb-2 block">Vehicle Info *</label>
                                    <input type="text" name="vehicle_info" x-model="vehicle_info"
                                        placeholder="VW Passat, 2018" required
                                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 transition-all bg-indigo-50/30 focus:bg-white">
                                    <p class="mt-1 text-xs text-indigo-400">Make, Model, Year</p>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-indigo-900 mb-2 block">License Plate
                                        *</label>
                                    <input type="text" name="registration" x-model="registration" placeholder="01-123-AB"
                                        required
                                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 transition-all bg-indigo-50/30 focus:bg-white uppercase font-medium">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Tire Details & Storage -->
                    <div x-show="step === 2" x-transition>
                        <div class="bg-white rounded-xl shadow-sm ring-1 ring-indigo-100 p-6">


                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                <div>
                                    <label class="text-sm font-medium text-indigo-900 mb-2 block">Brand *</label>
                                    <input type="text" name="brand" placeholder="Michelin" required
                                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 transition-all bg-indigo-50/30 focus:bg-white">
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-indigo-900 mb-2 block">Model</label>
                                    <input type="text" name="model" placeholder="Alpin 6"
                                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 transition-all bg-indigo-50/30 focus:bg-white">
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-indigo-900 mb-2 block">Size *</label>
                                    <input type="text" name="size" placeholder="205/55 R16" required
                                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 transition-all bg-indigo-50/30 focus:bg-white font-mono">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="text-sm font-medium text-indigo-900 mb-2 block">Season *</label>
                                    <select name="season" required
                                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 bg-indigo-50/30 focus:bg-white">
                                        <option value="winter">❄️ Winter</option>
                                        <option value="summer">☀️ Summer</option>
                                        <option value="all_season">🌤️ All Season</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-indigo-900 mb-2 block">Quantity *</label>
                                    <div class="flex items-center">
                                        <input type="number" name="quantity" value="4" min="1" max="8" required
                                            class="block w-full rounded-lg border-0 py-2.5 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 transition-all bg-indigo-50/30 focus:bg-white text-center font-bold">
                                        <span class="ml-3 text-sm text-indigo-500">tires</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-indigo-900 mb-2 block">Assign Technician
                                        *</label>
                                    <select name="technician_id" required
                                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 bg-indigo-50/30 focus:bg-white">
                                        <option value="">Select Technician</option>
                                        @foreach($users as $user)
                                            @php $isBusy = in_array($user->id, $busy_technician_ids ?? []); @endphp
                                            <option value="{{ $user->id }}" {{ $isBusy ? 'disabled' : '' }}
                                                class="{{ $isBusy ? 'text-gray-400' : '' }}">
                                                {{ $user->name }} {{ $isBusy ? '(Busy)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Storage Assignment -->
                            <div class="mt-8 pt-6 border-t border-indigo-50">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-lg font-bold text-indigo-900">Storage Assignment</h4>
                                    <span class="text-xs text-indigo-400 bg-indigo-50 px-2 py-1 rounded">Auto-suggests
                                        next available</span>
                                </div>

                                <div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                        <div>
                                            <label
                                                class="text-xs font-bold text-indigo-500 mb-2 block uppercase">Section</label>
                                            <select x-model="storage.section"
                                                class="block w-full rounded-lg border-0 py-2.5 px-3 text-indigo-900 shadow-sm ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                                <option value="S1">S1</option>
                                                <option value="S2">S2</option>
                                                <option value="S3">S3</option>
                                                <option value="S4">S4</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label
                                                class="text-xs font-bold text-indigo-500 mb-2 block uppercase">Row</label>
                                            <select x-model="storage.row"
                                                class="block w-full rounded-lg border-0 py-2.5 px-3 text-indigo-900 shadow-sm ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                                <option value="A">A</option>
                                                <option value="B">B</option>
                                                <option value="C">C</option>
                                                <option value="D">D</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label
                                                class="text-xs font-bold text-indigo-500 mb-2 block uppercase">Slot</label>
                                            <select x-model="storage.slot"
                                                class="block w-full rounded-lg border-0 py-2.5 px-3 text-indigo-900 shadow-sm ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                                @for ($i = 1; $i <= 20; $i++)
                                                    <option value="{{ sprintf('%02d', $i) }}">{{ sprintf('%02d', $i) }}
                                                    </option>
                                                @endfor
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-indigo-500 mb-2 block uppercase">Result
                                                Code</label>
                                            <div class="relative">
                                                <input type="text" name="storage_location" x-model="storage.code" readonly
                                                    :class="{'bg-red-50 text-red-700 ring-red-200': !storage.isAvailable, 'bg-green-50 text-green-700 ring-green-200': storage.isAvailable}"
                                                    class="block w-full rounded-lg border-0 py-2.5 px-3 shadow-sm ring-1 ring-inset focus:ring-indigo-500 sm:text-sm sm:leading-6 font-mono font-bold text-center transition-colors">

                                                <button type="button" x-show="!storage.isAvailable" @click="fetchNextSlot()"
                                                    class="absolute right-1 top-1 bottom-1 px-2 bg-white text-indigo-600 text-xs font-bold rounded border border-indigo-100 shadow-sm hover:bg-indigo-50">
                                                    Find Next
                                                </button>
                                            </div>
                                            <div class="mt-1 text-xs font-medium h-5" x-html="storage.message"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-between pt-6 border-t border-indigo-100 mt-6">
                        <div class="flex space-x-4">
                            <button type="button" id="cancel-store-inline"
                                class="inline-flex items-center px-5 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50 transition-all">
                                Cancel
                            </button>

                            <button type="button" x-show="step === 2" @click="step = 1"
                                class="inline-flex items-center px-5 py-2.5 bg-white border border-indigo-200 text-indigo-700 rounded-lg text-sm font-semibold hover:bg-indigo-50 transition-all">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Back
                            </button>
                        </div>

                        <div>
                            <button type="button" x-show="step === 1" @click="goToStep2()"
                                class="inline-flex items-center px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-500 shadow-sm hover:shadow-md transition-all">
                                Next Step
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                </svg>
                            </button>

                            <button type="submit" x-show="step === 2" :disabled="!storage.isAvailable || storage.isChecking"
                                :class="{'opacity-50 cursor-not-allowed': !storage.isAvailable || storage.isChecking, 'hover:bg-indigo-500 hover:shadow-lg': storage.isAvailable && !storage.isChecking}"
                                class="inline-flex items-center px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold shadow-sm transition-all">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span x-show="!storage.isChecking">Confirm & Create Work Order</span>
                                <span x-show="storage.isChecking">Checking...</span>
                            </button>
                        </div>
                    </div>
                </form>
            </x-card>
        </div>

        <!-- =========================================
                                                                                 INLINE FORM: Retrieve Tires (Hidden by default)
                                                                                 ========================================= -->
        <div id="retrieve-form" class="hidden">
            <x-card>

                <!-- Search Box -->
                <div class="bg-white rounded-xl p-6 ring-1 ring-indigo-100">
                    <label class="text-sm font-bold text-indigo-950 uppercase tracking-wide mb-3 block">
                        Find Customer / Storage Code
                    </label>
                    <div class="flex gap-3">
                        <div class="relative flex-grow">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-indigo-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </span>
                            <input type="text" id="search_registration_inline"
                                placeholder="Enter license plate, storage code, or customer name..."
                                class="block w-full rounded-lg border-0 py-3 pl-10 pr-10 text-indigo-900 shadow-sm ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                <svg class="animate-spin w-5 h-5 text-indigo-600 hidden" id="registration-loading-inline"
                                    fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                            </div>
                        </div>
                        <button type="button" id="manual-search-btn-inline"
                            class="px-6 py-2 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 shadow-sm transition-all duration-200">
                            Search
                        </button>
                    </div>
                    <div id="registration-status-inline" class="mt-2 text-sm hidden text-center font-medium"></div>
                </div>

                <!-- Results Container -->
                <div id="tires-results-inline" class="mt-6 space-y-4"></div>

                <div class="flex justify-start mt-6">
                    <button type="button" id="cancel-retrieve-inline"
                        class="inline-flex items-center px-5 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50 transition-all">
                        Cancel
                    </button>
                </div>
            </x-card>
        </div>

        <!-- Section Separator -->
        <div class="py-8" id="dashboard-section">
            <div class="flex items-center justify-center">
                <div class="flex-grow border-t border-indigo-100"></div>
                <div class="mx-6">
                    <h2 class="text-xs font-bold text-indigo-300 uppercase tracking-widest">Overview & Analytics</h2>
                </div>
                <div class="flex-grow border-t border-indigo-100"></div>
            </div>
        </div>

        <!-- Tire Hotel Information Section -->
        <div class="space-y-6">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
                <x-card class="border-l-4 border-indigo-600 shadow-sm ring-1 ring-indigo-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-indigo-500">Total Tire Sets</p>
                            <p class="text-2xl font-bold text-indigo-900">{{ $stats['total_sets'] }}</p>
                        </div>
                    </div>
                </x-card>

                <x-card class="border-l-4 border-indigo-500 shadow-sm ring-1 ring-indigo-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-indigo-500">Individual Tires</p>
                            <p class="text-2xl font-bold text-indigo-900">{{ $stats['total_tires'] }}</p>
                        </div>
                    </div>
                </x-card>

                <x-card class="border-l-4 border-indigo-400 shadow-sm ring-1 ring-indigo-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-indigo-500">Storage Utilization</p>
                            <p class="text-2xl font-bold text-indigo-900">{{ $stats['storage_utilization'] }}%</p>
                        </div>
                    </div>
                </x-card>

                <x-card class="border-l-4 border-purple-400 shadow-sm ring-1 ring-indigo-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-indigo-500">New This Month</p>
                            <p class="text-2xl font-bold text-indigo-900">{{ $stats['new_arrivals_month'] }}</p>
                        </div>
                    </div>
                </x-card>
            </div>
            <!-- Storage Capacity & Upcoming Pickups Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Storage Capacity -->
                <div class="bg-white rounded-xl ring-1 ring-indigo-100 p-4 shadow-sm">
                    <h3 class="text-sm font-bold text-indigo-950 mb-3">Storage Capacity</h3>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach($storage_map as $section)
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-indigo-900 w-16">{{ $section['section'] }}</span>
                                <div class="flex-1 bg-indigo-50 rounded-full h-1.5">
                                    <div class="bg-indigo-600 h-1.5 rounded-full" style="width: {{ $section['percentage'] }}%"></div>
                                </div>
                                <span class="text-xs text-indigo-500">{{ $section['used'] }}/{{ $section['total'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Upcoming Pickups -->
                <div class="bg-white rounded-xl ring-1 ring-indigo-100 p-4 shadow-sm">
                    <h3 class="text-sm font-bold text-indigo-950 mb-3">Upcoming Pickups</h3>
                    @if($upcoming_pickups->count() > 0)
                        <div class="space-y-2">
                            @foreach($upcoming_pickups as $pickup)
                                <div class="flex items-center justify-between py-1 border-b border-indigo-50 last:border-0">
                                    <div>
                                        <span class="text-xs font-semibold text-indigo-900">{{ $pickup['customer_name'] }}</span>
                                        <span class="text-xs text-indigo-400 ml-1">{{ $pickup['vehicle'] }}</span>
                                    </div>
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-700">{{ $pickup['urgency'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-indigo-300 text-center py-2 text-xs">No upcoming pickups</p>
                    @endif
                </div>
            </div>

            <!-- Stored Tires - Full Width -->
            <div class="bg-white rounded-xl ring-1 ring-indigo-100 p-6 shadow-sm">
                <div class="mb-4 border-b border-indigo-50 pb-2">
                    <h3 class="text-lg font-bold text-indigo-950">Stored Tires</h3>
                </div>
                @if($tires->count() > 0)
                    <!-- Desktop Table with scroll for 6 visible rows -->
                    <div class="hidden md:block overflow-x-auto max-h-[360px] overflow-y-auto">
                        <table class="min-w-full divide-y divide-indigo-50">
                            <thead class="bg-indigo-50/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-indigo-400 uppercase tracking-wider">
                                        Customer & Vehicle</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-indigo-400 uppercase tracking-wider">
                                        Season</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-indigo-400 uppercase tracking-wider">
                                        Location</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold text-indigo-400 uppercase tracking-wider">
                                        Storage Date</th>
                                    <th
                                        class="px-4 py-3 text-center text-xs font-bold text-indigo-400 uppercase tracking-wider">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-indigo-50">
                                @foreach($tires as $tire)
                                    <tr class="hover:bg-indigo-50/30 transition-colors">
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div>
                                                <p class="text-sm font-semibold text-indigo-900">
                                                    {{ explode(' - ', $tire->customer->name ?? 'Unknown')[0] }}
                                                </p>
                                                <p class="text-xs text-indigo-500">
                                                    {{ $tire->vehicle->display_name ?? 'Unknown Vehicle' }}
                                                </p>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $tire->season === 'winter' ? 'bg-sky-100 text-sky-800' : ($tire->season === 'summer' ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-800') }}">
                                                {{ ucfirst(str_replace('_', ' ', $tire->season)) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700 font-mono">
                                                {{ $tire->storage_location }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-indigo-600">
                                            {{ $tire->storage_date->format('M j, Y') }}
                                            <br>
                                            <span class="text-xs text-indigo-400">{{ $tire->storage_duration }}</span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            <div class="flex items-center justify-center space-x-2">
                                                <button type="button"
                                                    class="group inline-flex items-center justify-center w-8 h-8 text-indigo-600 bg-white border border-indigo-200 rounded-lg hover:bg-indigo-600 hover:text-white transition-all duration-200 shadow-sm"
                                                    data-action="edit-tire" data-tire-id="{{ $tire->id }}" title="Edit tire">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                                        </path>
                                                    </svg>
                                                </button>

                                                <form method="POST" action="{{ route('tires-hotel.destroy', $tire->id) }}"
                                                    onsubmit="return confirm('Are you sure you want to remove these tires?');"
                                                    class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="group inline-flex items-center justify-center w-8 h-8 text-red-500 bg-white border border-red-200 rounded-lg hover:bg-red-500 hover:text-white transition-all duration-200 shadow-sm"
                                                        title="Delete tire">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                            </path>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card Layout -->
                    <div class="md:hidden space-y-3">
                        @foreach($tires as $tire)
                            <div class="bg-white border border-indigo-100 rounded-lg p-4 shadow-sm">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <p class="text-sm font-bold text-indigo-900">
                                            {{ explode(' - ', $tire->customer->name ?? 'Unknown')[0] }}
                                        </p>
                                        <p class="text-xs text-indigo-500">
                                            {{ $tire->vehicle->display_name ?? 'Unknown Vehicle' }}
                                        </p>
                                    </div>
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700 font-mono">
                                        {{ $tire->storage_location }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between mt-3 pt-3 border-t border-indigo-50">
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $tire->season === 'winter' ? 'bg-sky-100 text-sky-800' : 'bg-orange-100 text-orange-800' }}">
                                        {{ ucfirst(str_replace('_', ' ', $tire->season)) }}
                                    </span>
                                    <span class="text-xs text-indigo-400">{{ $tire->storage_date->format('M j, Y') }}</span>
                                </div>

                                <div class="mt-3 flex justify-end space-x-2">
                                    <button type="button" data-action="edit-tire" data-tire-id="{{ $tire->id }}"
                                        class="text-xs font-medium text-indigo-600 hover:text-indigo-900 p-2">Edit</button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    <div class="mt-6">
                        {{ $tires->links() }}
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="w-12 h-12 mx-auto text-indigo-200 mb-4" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4">
                            </path>
                        </svg>
                        <p class="text-indigo-400 font-medium">No tires currently in storage</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Modals (Edit only - Store is now inline) -->
    <x-tire.edit-modal />

    <script>
        // --- GLOBAL HELPER: Move Modal to Body to prevention Stacking/Z-Index issues ---
        function ensureModalInBody(modalId) {
            const modal = document.getElementById(modalId);
            if (modal && modal.parentNode !== document.body) {
                document.body.appendChild(modal);
            }
        }

        // --- EDIT TIRE MODAL LOGIC ---
        function openEditModal(id) {
            console.log('Opening Edit Modal for ID:', id);
            const modal = document.getElementById('edit-tire-modal');
            const form = document.getElementById('edit-tire-form');
            const loading = document.getElementById('edit-loading');
            const fields = document.getElementById('edit-fields');

            if (!modal) return console.error('Edit modal not found');

            // 1. Move to body to fix stacking/visual bugs
            ensureModalInBody('edit-tire-modal');

            // 2. Show Modal (Force display)
            modal.classList.remove('hidden');
            modal.style.display = 'block'; // Safety override

            // 3. Reset UI State
            loading.classList.remove('hidden');
            fields.classList.add('hidden');

            // 4. Set Action
            form.action = `/tires-hotel/${id}`;

            // 5. Fetch Data
            fetch(`/api/tires/${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const t = data.tire;
                        // Populate fields
                        document.getElementById('edit-brand').value = t.brand || '';
                        document.getElementById('edit-model').value = t.model || '';
                        document.getElementById('edit-size').value = t.size || '';
                        document.getElementById('edit-season').value = t.season || '';
                        document.getElementById('edit-quantity').value = t.quantity || '';
                        document.getElementById('edit-location').value = t.storage_location || '';
                        document.getElementById('edit-status').value = t.status || '';

                        // Show fields
                        loading.classList.add('hidden');
                        fields.classList.remove('hidden');
                    } else {
                        alert('Error fetching tire details: ' + (data.message || 'Unknown error'));
                        closeEditModal();
                    }
                })
                .catch(e => {
                    console.error('Fetch error:', e);
                    alert('Network error fetching details');
                    closeEditModal();
                });
        }

        function closeEditModal() {
            const modal = document.getElementById('edit-tire-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.style.display = 'none'; // Safety override
            }
        }

        // EXPOSE GLOBALS for Legacy Onclick & Modal access
        window.openEditModal = openEditModal;
        window.closeEditModal = closeEditModal;
        window.editTire = openEditModal; // Alias

        // --- DOM Event Listeners ---
        document.addEventListener('DOMContentLoaded', function () {
            console.log('TireHotel Script Loaded.');

            // 1. Global Event Delegation for Edit Buttons
            document.body.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-action="edit-tire"]');
                if (btn) {
                    // Prevent default to stop any conflicting actions
                    e.preventDefault();
                    e.stopPropagation();

                    const id = btn.dataset.tireId;
                    if (id) {
                        openEditModal(id);
                    } else {
                        console.error('Edit button missing data-tire-id');
                    }
                }
            });

            // 2. INLINE FORM TOGGLE LOGIC (Replacing old modal logic)
            const inHotelBtn = document.getElementById('in-hotel-box');
            const addNewBtn = document.getElementById('add-new-box');
            const actionBoxes = document.getElementById('action-boxes');
            const dashboardSection = document.getElementById('dashboard-section');
            const statsSection = dashboardSection ? dashboardSection.nextElementSibling : null;

            // Inline forms
            const storeNewForm = document.getElementById('store-new-form');
            const retrieveForm = document.getElementById('retrieve-form');

            // Cancel buttons
            const cancelStoreForm = document.getElementById('cancel-store-form');
            const cancelStoreInline = document.getElementById('cancel-store-inline');
            const cancelRetrieveForm = document.getElementById('cancel-retrieve-form');
            const cancelRetrieveInline = document.getElementById('cancel-retrieve-inline');

            function hideAllForms() {
                if (storeNewForm) storeNewForm.classList.add('hidden');
                if (retrieveForm) retrieveForm.classList.add('hidden');
            }

            function showDashboard() {
                if (actionBoxes) actionBoxes.classList.remove('hidden');
                if (dashboardSection) dashboardSection.classList.remove('hidden');
                // Show all elements after dashboard separator
                let sibling = dashboardSection ? dashboardSection.nextElementSibling : null;
                while (sibling) {
                    sibling.classList.remove('hidden');
                    sibling = sibling.nextElementSibling;
                }
            }

            function hideDashboard() {
                if (actionBoxes) actionBoxes.classList.add('hidden');
                if (dashboardSection) dashboardSection.classList.add('hidden');
                // Hide all elements after dashboard separator
                let sibling = dashboardSection ? dashboardSection.nextElementSibling : null;
                while (sibling) {
                    sibling.classList.add('hidden');
                    sibling = sibling.nextElementSibling;
                }
            }

            function openStoreForm() {
                hideAllForms();
                hideDashboard();
                if (storeNewForm) storeNewForm.classList.remove('hidden');
                // Scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            function openRetrieveForm() {
                hideAllForms();
                hideDashboard();
                if (retrieveForm) retrieveForm.classList.remove('hidden');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            function closeAllForms() {
                hideAllForms();
                showDashboard();
                // Clear search input and results
                const searchInput = document.getElementById('search_registration_inline');
                const resultsContainer = document.getElementById('tires-results-inline');
                const statusDiv = document.getElementById('registration-status-inline');
                if (searchInput) searchInput.value = '';
                if (resultsContainer) resultsContainer.innerHTML = '';
                if (statusDiv) statusDiv.classList.add('hidden');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            // Event Listeners
            if (addNewBtn) addNewBtn.addEventListener('click', openStoreForm);
            if (inHotelBtn) inHotelBtn.addEventListener('click', openRetrieveForm);

            // Cancel buttons
            if (cancelStoreForm) cancelStoreForm.addEventListener('click', closeAllForms);
            if (cancelStoreInline) cancelStoreInline.addEventListener('click', closeAllForms);
            if (cancelRetrieveForm) cancelRetrieveForm.addEventListener('click', closeAllForms);
            if (cancelRetrieveInline) cancelRetrieveInline.addEventListener('click', closeAllForms);

            // Auto-open Store form if URL param is present (from dashboard quick action)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('openStore') === 'true') {
                openStoreForm();
            }
            if (urlParams.get('openRetrieve') === 'true') {
                openRetrieveForm();
            }

            // 3. Registration Search Logic
            const searchInput = document.getElementById('search_registration_inline');
            const manualSearchBtn = document.getElementById('manual-search-btn-inline');

            function performSearch() {
                const query = searchInput ? searchInput.value : '';
                if (query.length < 2) return;

                const loading = document.getElementById('registration-loading-inline');
                if (loading) loading.classList.remove('hidden');

                fetch(`/api/tires/search-by-registration?registration=${encodeURIComponent(query)}`)
                    .then(r => r.json())
                    .then(data => {
                        if (loading) loading.classList.add('hidden');

                        const statusDiv = document.getElementById('registration-status-inline');
                        if (statusDiv) statusDiv.classList.remove('hidden');

                        const resultsContainer = document.getElementById('tires-results-inline');

                        if (data.success) {
                            if (statusDiv) statusDiv.classList.add('hidden');

                            // Render Stored Tires
                            if (data.current_tires && data.current_tires.length > 0) {
                                resultsContainer.innerHTML = '';

                                const customerName = data.vehicle.customer ? data.vehicle.customer.name : 'Unknown Customer';

                                data.current_tires.forEach(tire => {
                                    // Helper for location parsing
                                    let section = '', row = '', slot = '';
                                    if (tire.storage_location) {
                                        const parts = tire.storage_location.split('-');
                                        if (parts.length >= 3) { section = parts[0]; row = parts[1]; slot = parts[2]; }
                                        else if (parts.length === 2) { section = parts[0]; row = '-'; slot = parts[1]; }
                                        else { section = tire.storage_location; }
                                    }

                                    const tireRow = document.createElement('div');
                                    tireRow.className = 'bg-indigo-50/50 rounded-lg p-4 border border-indigo-100 text-sm mb-4';
                                    tireRow.innerHTML = `
                                                                <div class="space-y-1">
                                                                    <div class="flex"><span class="font-bold w-20 text-indigo-900 inline-block">Name:</span> <span>${customerName}</span></div>
                                                                    <div class="flex"><span class="font-bold w-20 text-indigo-900 inline-block">Vehicle:</span> <span>${data.vehicle.make} ${data.vehicle.model}</span></div>
                                                                    <div class="h-px bg-indigo-100 my-2"></div>
                                                                    <div class="flex"><span class="font-bold w-20 text-indigo-900 inline-block">Brand:</span> <span>${tire.brand}</span></div>
                                                                    <div class="flex"><span class="font-bold w-20 text-indigo-900 inline-block">Size:</span> <span>${tire.size}</span></div>
                                                                    <div class="flex"><span class="font-bold w-20 text-indigo-900 inline-block">Season:</span> <span>${tire.season}</span></div>
                                                                    <div class="h-px bg-indigo-100 my-2"></div>
                                                                    <div class="flex items-center justify-between">
                                                                        <div class="font-mono text-indigo-700 font-bold">
                                                                            Loc: ${section}-${row}-${slot}
                                                                        </div>
                                                                        <div class="flex space-x-2">
                                                                        <a href="/tires-hotel/${tire.id}" 
                                                                            class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-xs font-bold uppercase tracking-wide rounded hover:bg-indigo-700 transition-colors shadow-sm">
                                                                            View
                                                                        </a>
                                                                    </div>
                                                                    </div>
                                                                </div>
                                                            `;
                                    resultsContainer.appendChild(tireRow);
                                });
                            } else {
                                resultsContainer.innerHTML = '<div class="text-indigo-400 text-center py-4">No tires found in storage for this vehicle.</div>';
                            }
                        } else {
                            if (statusDiv) statusDiv.innerHTML = '<span class="text-red-500 font-bold block p-2 bg-red-50 rounded">No vehicle or storage code found</span>';
                            if (resultsContainer) resultsContainer.innerHTML = '';
                        }
                    })
                    .catch(e => {
                        console.error(e);
                        if (loading) loading.classList.add('hidden');
                    });
            }

            if (manualSearchBtn) manualSearchBtn.addEventListener('click', performSearch);
            if (searchInput) {
                searchInput.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        performSearch();
                    }
                });
            }

            // 4. Storage Code Auto-Generator
            const sSection = document.getElementById('storage_section');
            const sRow = document.getElementById('storage_row');
            const sSlot = document.getElementById('storage_slot');
            const sCode = document.getElementById('storage_code');

            function updateStorageCode() {
                if (sSection && sRow && sSlot && sCode) {
                    sCode.value = `${sSection.value}-${sRow.value}-${sSlot.value}`;
                }
            }

            if (sSection && sRow && sSlot) {
                [sSection, sRow, sSlot].forEach(el => el.addEventListener('change', updateStorageCode));
                updateStorageCode();
            }
        });

        // Alpine.js Component for Tire Storage Form
        document.addEventListener('alpine:init', () => {
            Alpine.data('tireStorageForm', () => ({
                step: 1,
                customer_name: '',
                vehicle_info: '',
                registration: '',
                storage: {
                    section: 'S1',
                    row: 'A',
                    slot: '01',
                    code: 'S1-A-01',
                    isChecking: false,
                    isAvailable: true,
                    message: ''
                },

                init() {
                    this.$watch('storage.section', () => this.updateStorageCode());
                    this.$watch('storage.row', () => this.updateStorageCode());
                    this.$watch('storage.slot', () => this.updateStorageCode());
                },

                updateStorageCode() {
                    this.storage.code = `${this.storage.section}-${this.storage.row}-${this.storage.slot}`;
                    this.checkAvailability();
                },

                async fetchNextSlot() {
                    this.storage.isChecking = true;
                    try {
                        const res = await fetch('/api/tires/storage/check-availability');
                        const data = await res.json();

                        if (data.available && data.components) {
                            this.storage.section = data.components.section;
                            this.storage.row = data.components.row;
                            this.storage.slot = data.components.slot;
                        } else if (!data.available) {
                            this.storage.message = "Warning: Storage is full!";
                            this.storage.isAvailable = false;
                        }
                    } catch (e) {
                        console.error("Error fetching next slot:", e);
                    } finally {
                        this.storage.isChecking = false;
                    }
                },

                async checkAvailability() {
                    this.storage.isChecking = true;
                    this.storage.message = 'Checking...';
                    try {
                        const res = await fetch(`/api/tires/storage/check-availability?location=${this.storage.code}`);
                        const data = await res.json();

                        this.storage.isAvailable = data.available;
                        this.storage.message = data.available
                            ? '<span class="text-green-600 flex items-center"><svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Available</span>'
                            : '<span class="text-red-500 flex items-center"><svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Occupied</span>';
                    } catch (e) {
                        console.error("Error checking availability:", e);
                        this.storage.message = "Error checking";
                    } finally {
                        this.storage.isChecking = false;
                    }
                },

                goToStep2() {
                    if (!this.customer_name || !this.vehicle_info || !this.registration) {
                        alert('Please fill in all required customer and vehicle fields.');
                        return;
                    }
                    this.step = 2;
                    this.fetchNextSlot();
                }
            }));
        });
    </script>
@endsection