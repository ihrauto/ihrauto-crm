@props([])

<div id="tire-form"
    class="hidden fixed inset-0 bg-gray-900/60 backdrop-blur-sm overflow-y-auto h-full w-full z-50 transition-opacity duration-300"
    x-data="tireStorageWizard()"
    @open-new-wizard.window="step = 1; mode = 'new'; document.getElementById('common-tire-fields').classList.add('hidden');"
    x-on:switch-mode.window="mode = $event.detail.mode">
    <!-- We rely on parent JS to toggle the 'hidden' class on the root div #tire-form -->

    <div
        class="relative top-10 mx-auto p-0 border-0 w-11/12 md:w-3/4 lg:w-2/3 shadow-2xl rounded-2xl bg-white overflow-hidden ring-1 ring-black/5 mb-10">

        <!-- Header -->
        <div class="bg-white px-8 py-6 border-b border-indigo-50 flex justify-between items-center sticky top-0 z-10">
            <div>
            </div>

            <!-- Step Indicator (Only for New Mode) -->
            <div id="wizard-steps" class="flex items-center space-x-2" x-show="mode === 'new'">
                <span :class="{'bg-indigo-600 text-white': step >= 1, 'bg-gray-100 text-gray-400': step < 1}"
                    class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-colors">1</span>
                <div class="w-8 h-1 bg-gray-100">
                    <div class="h-full bg-indigo-600 transition-all duration-300"
                        :style="'width: ' + (step > 1 ? '100%' : '0%')"></div>
                </div>
                <span :class="{'bg-indigo-600 text-white': step >= 2, 'bg-gray-100 text-gray-400': step < 2}"
                    class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold transition-colors">2</span>
            </div>

            <button type="button" id="cancel-tire-x"
                class="text-gray-400 hover:text-gray-600 transition-colors p-2 rounded-full hover:bg-gray-100">
                <svg class="w-6 h-6 " fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>

        <div class="p-8 bg-gray-50/50">
            <form method="POST" action="{{ route('tires-hotel.store') }}" class="space-y-8">
                @csrf

                <!-- ==============================
                     SCENARIO A: EXISTING CUSTOMER (Retrieve)
                     ============================== -->
                <div id="in-hotel-fields" class="hidden space-y-6">
                    <!-- Search Box -->
                    <div class="bg-white rounded-xl shadow-sm ring-1 ring-indigo-100 p-6">
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
                                <input type="text" name="search_registration" id="search_registration"
                                    placeholder="Enter license plate or storage code (e.g. S1-A-01)..."
                                    class="block w-full rounded-lg border-0 py-3 pl-10 pr-10 text-indigo-900 shadow-sm ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">

                                <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                    <svg class="animate-spin w-5 h-5 text-indigo-600 hidden" id="registration-loading"
                                        fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                            <button type="button" id="manual-search-btn"
                                class="px-6 py-2 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 shadow-sm transition-all duration-200">
                                Search
                            </button>
                        </div>
                        <div id="registration-status" class="mt-2 text-sm hidden text-center font-medium"></div>
                    </div>

                    <!-- Auto-populated Info Card -->
                    <div id="customer-vehicle-info"
                        class="hidden bg-indigo-50/50 rounded-xl p-6 border border-indigo-100 flex flex-col md:flex-row gap-6">
                        <div class="flex-1">
                            <h4 class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-2">Customer</h4>
                            <p id="display-customer-name" class="text-lg font-bold text-indigo-900">-</p>
                        </div>
                        <div class="w-px bg-indigo-200 hidden md:block"></div>
                        <div class="flex-1">
                            <h4 class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-2">Vehicle</h4>
                            <p id="display-vehicle-info" class="text-lg font-bold text-indigo-900">-</p>
                        </div>
                    </div>

                    <!-- Stored Tires List -->
                    <div id="stored-tires-list" class="mt-4 hidden">
                        <div class="flex items-center justify-between mb-4">
                            <h5 class="text-sm font-bold text-indigo-950 uppercase tracking-wide">Currently Stored Types
                            </h5>
                        </div>
                        <div id="tires-container" class="space-y-3">
                            <!-- Results injected via JS -->
                        </div>
                    </div>
                </div>

                <!-- ==============================
                     SCENARIO B: NEW CUSTOMER (Wizard)
                     ============================== -->
                <div id="add-new-fields" class="hidden space-y-6" x-show="step === 1"> <!-- Step 1 Container -->

                    <!-- Section: Customer & Vehicle -->
                    <div class="bg-white rounded-xl shadow-sm ring-1 ring-indigo-100 p-6">


                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Customer Name -->
                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1.5 block">Full Name *</label>
                                <input type="text" name="customer_name" placeholder="Runor Derti"
                                    class="block w-full rounded-lg border-0 py-2.5 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 transition-all bg-indigo-50/30 focus:bg-white">
                            </div>
                            <!-- Phone -->
                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1.5 block">Phone Number</label>
                                <input type="tel" name="customer_phone" placeholder="+383 4X XXX XXX"
                                    class="block w-full rounded-lg border-0 py-2.5 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 transition-all bg-indigo-50/30 focus:bg-white">
                            </div>

                            <!-- Vehicle Info -->
                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1.5 block">Vehicle Info *</label>
                                <input type="text" name="vehicle_info" placeholder="VW Passat, 2018"
                                    class="block w-full rounded-lg border-0 py-2.5 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 transition-all bg-indigo-50/30 focus:bg-white">
                                <p class="mt-1 text-xs text-indigo-400">Make, Model, Year</p>
                            </div>

                            <!-- License Plate -->
                            <div>
                                <label class="text-sm font-medium text-indigo-900 mb-1.5 block">License Plate *</label>
                                <input type="text" name="registration" placeholder="01-123-AB"
                                    class="block w-full rounded-lg border-0 py-2.5 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 transition-all bg-indigo-50/30 focus:bg-white uppercase font-medium">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==============================
                     COMMON: TIRE DETAILS (Step 2)
                     ============================== -->
                <div id="common-tire-fields" class="hidden bg-white rounded-xl shadow-sm ring-1 ring-indigo-100 p-6"
                    x-show="step === 2">


                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label class="text-sm font-medium text-indigo-900 mb-1.5 block">Brand *</label>
                            <input type="text" name="brand" placeholder="Michelin"
                                class="block w-full rounded-lg border-0 py-2.5 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 transition-all bg-indigo-50/30 focus:bg-white">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-indigo-900 mb-1.5 block">Model</label>
                            <input type="text" name="model" placeholder="Alpin 6"
                                class="block w-full rounded-lg border-0 py-2.5 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 transition-all bg-indigo-50/30 focus:bg-white">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-indigo-900 mb-1.5 block">Size *</label>
                            <input type="text" name="size" placeholder="205/55 R16"
                                class="block w-full rounded-lg border-0 py-2.5 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 transition-all bg-indigo-50/30 focus:bg-white font-mono">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="text-sm font-medium text-indigo-900 mb-1.5 block">Season *</label>
                            <select name="season"
                                class="block w-full rounded-lg border-0 py-2.5 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 bg-indigo-50/30 focus:bg-white">
                                <option value="winter">❄️ Winter</option>
                                <option value="summer">☀️ Summer</option>
                                <option value="all_season">🌤️ All Season</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-indigo-900 mb-1.5 block">Quantity *</label>
                            <div class="flex items-center">
                                <input type="number" name="quantity" value="4" min="1" max="8"
                                    class="block w-full rounded-lg border-0 py-2.5 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 transition-all bg-indigo-50/30 focus:bg-white text-center font-bold">
                                <span class="ml-3 text-sm text-indigo-500">tires</span>
                            </div>
                        </div>
                    </div>

                    <!-- Storage Assignment Sub-section -->
                    <div class="mt-8 pt-6 border-t border-indigo-50">
                        <div class="flex items-center justify-between mb-4">
                            <h5 class="text-sm font-bold text-indigo-900 uppercase tracking-wide">Storage Assignment
                            </h5>
                            <span class="text-xs text-indigo-400 bg-indigo-50 px-2 py-1 rounded">Auto-generates
                                Code</span>
                        </div>

                        <div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 items-end">
                                <div>
                                    <label
                                        class="text-xs font-bold text-indigo-500 mb-1 block uppercase">Section</label>
                                    <select id="storage_section" x-model="storage.section"
                                        class="block w-full rounded-lg border-0 py-2 px-3 text-indigo-900 shadow-sm ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                        <option value="S1">S1</option>
                                        <option value="S2">S2</option>
                                        <option value="S3">S3</option>
                                        <option value="S4">S4</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-indigo-500 mb-1 block uppercase">Row</label>
                                    <select id="storage_row" x-model="storage.row"
                                        class="block w-full rounded-lg border-0 py-2 px-3 text-indigo-900 shadow-sm ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                        <option value="A">A</option>
                                        <option value="B">B</option>
                                        <option value="C">C</option>
                                        <option value="D">D</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-indigo-500 mb-1 block uppercase">Slot</label>
                                    <select id="storage_slot" x-model="storage.slot"
                                        class="block w-full rounded-lg border-0 py-2 px-3 text-indigo-900 shadow-sm ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                        @for ($i = 1; $i <= 20; $i++)
                                            <option value="{{ sprintf('%02d', $i) }}">{{ sprintf('%02d', $i) }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-indigo-500 mb-1 block uppercase">Result
                                        Code</label>
                                    <div class="relative">
                                        <input type="text" name="storage_location" id="storage_code" readonly
                                            x-model="storage.code"
                                            :class="{'bg-red-50 text-red-700 ring-red-200': !storage.isAvailable, 'bg-green-50 text-green-700 ring-green-200': storage.isAvailable}"
                                            class="block w-full rounded-lg border-0 py-2 px-3 shadow-sm ring-1 ring-inset focus:ring-indigo-500 sm:text-sm sm:leading-6 font-mono font-bold text-center transition-colors">

                                        <!-- Quick Find Next Button if occupied -->
                                        <button type="button" x-show="!storage.isAvailable" @click="fetchNextSlot()"
                                            class="absolute right-1 top-1 bottom-1 px-2 bg-white text-indigo-600 text-xs font-bold rounded border border-indigo-100 shadow-sm hover:bg-indigo-50"
                                            title="Find next available slot">
                                            Find Next
                                        </button>
                                    </div>
                                    <div class="mt-1 text-xs font-medium h-5" x-html="storage.message"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="flex items-center justify-end space-x-4 pt-4 border-t border-indigo-50">
                    <!-- Standard Cancel Button (for Retrieve mode or Step 1) -->
                    <button type="button" id="cancel-tire" @click="reset()"
                        class="px-6 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all">
                        Cancel
                    </button>

                    <!-- Wizard Buttons -->
                    <template x-if="mode === 'new'">
                        <div class="flex space-x-4">
                            <!-- Back Button (Step 2) -->
                            <button type="button" x-show="step === 2" @click="back()"
                                class="px-6 py-2.5 bg-white border border-indigo-200 text-indigo-700 rounded-lg text-sm font-semibold hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all">
                                Back
                            </button>

                            <!-- Next Button (Step 1) -->
                            <button type="button" x-show="step === 1" @click="next()"
                                class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-500 shadow-sm hover:shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 transition-all">
                                Next &rarr;
                            </button>

                            <!-- Submit Button (Step 2) -->
                            <button type="submit" x-show="step === 2"
                                :disabled="!storage.isAvailable || storage.isChecking"
                                :class="{'opacity-50 cursor-not-allowed': !storage.isAvailable || storage.isChecking, 'hover:bg-indigo-500 hover:shadow-md': storage.isAvailable && !storage.isChecking}"
                                class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 transition-all">
                                <span x-show="!storage.isChecking">Confirm & Create Work Order</span>
                                <span x-show="storage.isChecking">Checking...</span>
                            </button>
                        </div>
                    </template>

                    <!-- Fallback confirm for Retrieve Mode -->
                    <div class="hidden" id="fallback-submit">
                        <!-- This usually doesn't have a submit unless we add one for retrieve... but retrieve is mostly search. 
                              We'll leave this empty as retrieve mode logic is handled by search. -->
                    </div>
                </div>

                <!-- Hidden inputs for ID -->
                <input type="hidden" name="customer_id" id="hidden_customer_id">
                <input type="hidden" name="vehicle_id" id="hidden_vehicle_id">

            </form>
        </div>
    </div>
</div>

<script>
    // Define Alpine component as a separate function to avoid x-data syntax issues in Blade
    document.addEventListener('alpine:init', () => {
        Alpine.data('tireStorageWizard', () => ({
            step: 1,
            mode: 'new',
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
                this.$watch('step', value => {
                    window.dispatchEvent(new CustomEvent('wizard-step-change', { detail: { step: value } }));
                });

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
                    this.storage.message = "Error checking availability";
                } finally {
                    this.storage.isChecking = false;
                }
            },

            validateStep1() {
                const name = document.querySelector('[name="customer_name"]').value;
                const vehicle = document.querySelector('[name="vehicle_info"]').value;
                const plate = document.querySelector('[name="registration"]').value;

                if (!name || !vehicle || !plate) {
                    alert('Please fill in all required customer and vehicle fields.');
                    return false;
                }
                return true;
            },

            next() {
                if (this.validateStep1()) {
                    this.step = 2;
                    document.getElementById('common-tire-fields').classList.remove('hidden');
                    this.fetchNextSlot();
                }
            },

            back() {
                this.step = 1;
                document.getElementById('common-tire-fields').classList.add('hidden');
            },

            reset() {
                this.step = 1;
                document.getElementById('common-tire-fields').classList.add('hidden');
            }
        }));
    });
</script>