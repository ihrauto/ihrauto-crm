@extends('layouts.app')

@section('title', 'System Settings')

@section('content')
    <div class="max-w-5xl mx-auto space-y-6">

        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">System Settings</h1>
                <p class="text-xs text-gray-500 font-medium">Manage application configuration and preferences.</p>
            </div>
            <a href="{{ route('management') }}"
                class="group inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-500 transition-colors">
                <div
                    class="w-6 h-6 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mr-2 group-hover:bg-indigo-100 transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7 7-7"></path>
                    </svg>
                </div>
                Back to Dashboard
            </a>
        </div>

        @if(session('success'))
            <div class="rounded-xl bg-green-50 p-4 border border-green-100 flex items-center animate-fade-in-down">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-bold text-green-800">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <form action="{{ route('management.settings.update') }}" method="POST">
                @csrf

                <!-- Company Information -->
                <div class="p-8 border-b border-gray-100">
                    <div class="md:grid md:grid-cols-3 md:gap-10">
                        <div class="md:col-span-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <div class="w-6 h-6 rounded bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </div>
                                <h3 class="text-base font-bold text-gray-900">Company Information</h3>
                            </div>
                            <p class="text-xs text-gray-500 leading-relaxed ml-8">Basic company details and application
                                settings defined for your organization.</p>
                        </div>
                        <div class="mt-6 md:mt-0 md:col-span-2 space-y-6">
                            <div class="group">
                                <label for="company_name"
                                    class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">Company
                                    Name</label>
                                <input type="text" name="company_name" id="company_name"
                                    value="{{ auth()->user()->tenant->name ?? config('app.name', 'IHRAUTO CRM') }}"
                                    class="w-full bg-gray-50/50 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-gray-300">
                            </div>

                            <!-- Address Section -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="group md:col-span-2">
                                    <label for="address"
                                        class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">Street
                                        & Number</label>
                                    <input type="text" name="address" id="address"
                                        value="{{ auth()->user()->tenant->address ?? 'Main Street 123' }}"
                                        class="w-full bg-gray-50/50 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-gray-300">
                                </div>

                                <div class="group">
                                    <label for="postal_code"
                                        class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">Postal
                                        Code</label>
                                    <input type="text" name="postal_code" id="postal_code"
                                        value="{{ auth()->user()->tenant->postal_code ?? '8000' }}"
                                        class="w-full bg-gray-50/50 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-gray-300">
                                </div>

                                <div class="group">
                                    <label for="city"
                                        class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">City</label>
                                    <input type="text" name="city" id="city"
                                        value="{{ auth()->user()->tenant->city ?? 'Zurich' }}"
                                        class="w-full bg-gray-50/50 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-gray-300">
                                </div>

                                <div class="group md:col-span-2">
                                    <label for="country"
                                        class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">Country</label>
                                    <input type="text" name="country" id="country"
                                        value="{{ auth()->user()->tenant->country ?? 'Switzerland' }}"
                                        class="w-full bg-gray-50/50 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-gray-300">
                                </div>
                            </div>

                            <!-- Registration & VAT -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-gray-100">
                                <div class="group">
                                    <label for="uid_number"
                                        class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">UID
                                        / CHE Number</label>
                                    <input type="text" name="uid_number" id="uid_number"
                                        value="{{ auth()->user()->tenant->uid_number ?? '' }}"
                                        class="w-full bg-gray-50/50 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-gray-300">
                                </div>

                                <div class="group">
                                    <label for="vat_registered_select"
                                        class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">VAT
                                        Registered</label>
                                    <select id="vat_registered_select" name="vat_registered" onchange="toggleVatNumber()"
                                        class="w-full bg-gray-50/50 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all appearance-none">
                                        <option value="0" {{ !(auth()->user()->tenant->vat_registered ?? false) ? 'selected' : '' }}>No</option>
                                        <option value="1" {{ (auth()->user()->tenant->vat_registered ?? false) ? 'selected' : '' }}>Yes</option>
                                    </select>
                                </div>

                                <div class="group md:col-span-2 {{ (auth()->user()->tenant->vat_registered ?? false) ? '' : 'hidden' }}"
                                    id="vat_number_group">
                                    <label for="vat_number"
                                        class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">VAT
                                        Number</label>
                                    <input type="text" name="vat_number" id="vat_number"
                                        value="{{ auth()->user()->tenant->vat_number ?? '' }}"
                                        class="w-full bg-gray-50/50 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-gray-300">
                                </div>
                            </div>

                            <!-- Financial / Bank -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-gray-100">
                                <div class="group md:col-span-2">
                                    <label for="bank_name"
                                        class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">Bank
                                        Name</label>
                                    <input type="text" name="bank_name" id="bank_name"
                                        value="{{ auth()->user()->tenant->bank_name ?? '' }}"
                                        class="w-full bg-gray-50/50 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-gray-300">
                                </div>

                                <div class="group md:col-span-2">
                                    <label for="iban"
                                        class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">IBAN</label>
                                    <input type="text" name="iban" id="iban"
                                        value="{{ auth()->user()->tenant->iban ?? '' }}"
                                        class="w-full bg-gray-50/50 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-gray-300">
                                </div>

                                <div class="group md:col-span-2">
                                    <label for="account_holder"
                                        class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">Account
                                        Holder</label>
                                    <input type="text" name="account_holder" id="account_holder"
                                        value="{{ auth()->user()->tenant->account_holder ?? '' }}"
                                        class="w-full bg-gray-50/50 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-gray-300">
                                </div>

                                <div class="group">
                                    <label for="invoice_email"
                                        class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">Invoice
                                        Email</label>
                                    <input type="email" name="invoice_email" id="invoice_email"
                                        value="{{ auth()->user()->tenant->invoice_email ?? '' }}"
                                        class="w-full bg-gray-50/50 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-gray-300">
                                </div>

                                <div class="group">
                                    <label for="invoice_phone"
                                        class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">Invoice
                                        Phone</label>
                                    <input type="text" name="invoice_phone" id="invoice_phone"
                                        value="{{ auth()->user()->tenant->invoice_phone ?? '' }}"
                                        class="w-full bg-gray-50/50 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-gray-300">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    function toggleVatNumber() {
                        const select = document.getElementById('vat_registered_select');
                        const vatGroup = document.getElementById('vat_number_group');
                        if (select.value === '1') {
                            vatGroup.classList.remove('hidden');
                        } else {
                            vatGroup.classList.add('hidden');
                        }
                    }
                </script>

                <!-- Financial Settings -->
                <div class="p-8 border-b border-gray-100">
                    <div class="md:grid md:grid-cols-3 md:gap-10">
                        <div class="md:col-span-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <div
                                    class="w-6 h-6 rounded bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h3 class="text-base font-bold text-gray-900">Financial Settings</h3>
                            </div>
                            <p class="text-xs text-gray-500 leading-relaxed ml-8">Currency, tax rates, and invoicing
                                configuration.</p>
                        </div>
                        <div class="mt-6 md:mt-0 md:col-span-2 space-y-6">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div class="group">
                                    <label for="currency"
                                        class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">Currency</label>
                                    <div class="relative">
                                        <select id="currency" name="currency"
                                            class="w-full bg-gray-50/50 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all appearance-none">
                                            <option value="CHF" selected>CHF (Swiss Franc)</option>
                                            <option value="EUR">EUR (Euro)</option>
                                            <option value="USD">USD (US Dollar)</option>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                <div class="group">
                                    <label for="tax_rate"
                                        class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-1.5 group-focus-within:text-indigo-600 transition-colors">Default
                                        Tax Rate (%)</label>
                                    <input type="number" name="tax_rate" id="tax_rate" value="7.7" step="0.1"
                                        class="w-full bg-gray-50/50 border text-gray-900 text-sm font-medium border-gray-200 rounded-lg px-3 py-2.5 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-gray-300">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>



                <!-- Footer Actions -->
                <div class="px-8 py-5 bg-gray-50 flex items-center justify-end">
                    <a href="{{ route('management') }}"
                        class="rounded-xl border border-gray-200 bg-white py-2.5 px-5 text-sm font-bold text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-all mr-3 shadow-sm">Cancel</a>
                    <button type="submit"
                        class="inline-flex justify-center rounded-xl border border-transparent bg-indigo-600 py-2.5 px-6 text-sm font-bold text-white shadow-lg shadow-indigo-200 hover:bg-indigo-500 active:scale-95 transition-all">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection