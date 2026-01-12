@extends('layouts.app')

@section('title', 'FINANCE')

@section('content')
    <div class="space-y-8">

        <!-- Top Stats Overview -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Revenue Month -->
            <div class="overflow-hidden rounded-xl bg-white px-4 py-5 shadow-sm ring-1 ring-gray-900/5 sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-500">Revenue (This Month)</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">CHF
                    {{ number_format($overview['revenue_month'], 2) }}</dd>
            </div>

            <!-- Revenue Year -->
            <div class="overflow-hidden rounded-xl bg-white px-4 py-5 shadow-sm ring-1 ring-gray-900/5 sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-500">Revenue (YTD)</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-indigo-600">CHF
                    {{ number_format($overview['revenue_year'], 2) }}</dd>
            </div>

            <!-- Outstanding -->
            <div class="overflow-hidden rounded-xl bg-white px-4 py-5 shadow-sm ring-1 ring-gray-900/5 sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-500">Outstanding (Pending)</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-yellow-600">CHF
                    {{ number_format($overview['outstanding_total'], 2) }}</dd>
            </div>

            <!-- Overdue -->
            <div class="overflow-hidden rounded-xl bg-white px-4 py-5 shadow-sm ring-1 ring-gray-900/5 sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-500">Overdue (Critical)</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-red-600">CHF
                    {{ number_format($overview['overdue_total'], 2) }}</dd>
            </div>
        </div>


        <!-- Main Content -->
        <div class="md:flex md:items-center md:justify-between">
            <div class="min-w-0 flex-1">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <a href="{{ route('finance.index', ['tab' => 'overview']) }}"
                            class="{{ $activeTab === 'overview' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Recent Payments
                        </a>
                        <a href="{{ route('finance.index', ['tab' => 'balances']) }}"
                            class="{{ $activeTab === 'balances' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Unpaid Balances
                        </a>
                        <a href="{{ route('finance.index', ['tab' => 'invoices']) }}"
                            class="{{ $activeTab === 'invoices' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Invoices
                        </a>

                    </nav>
                </div>
            </div>
            <div class="mt-4 flex md:ml-4 md:mt-0">
                <button type="button" onclick="openPaymentModal()"
                    class="ml-3 inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Register Payment
                </button>
            </div>
        </div>

        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
            @if($activeTab === 'overview')
                <!-- Payments Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">
                                    Date</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Customer</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Invoice</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Method</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($payments as $payment)
                                <tr>
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 sm:pl-6">
                                        {{ $payment->payment_date->format('d M Y') }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-gray-900">
                                        {{ $payment->invoice->customer->name ?? 'Unknown' }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                        {{ $payment->invoice->invoice_number ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 capitalize">
                                        {{ str_replace('_', ' ', $payment->method) }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm font-bold text-green-600">+ CHF
                                        {{ number_format($payment->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-8 text-center text-sm text-gray-500">No recent payments.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-gray-200 px-4 py-3 sm:px-6">
                    {{ $payments->appends(['tab' => 'overview'])->links() }}
                </div>

            @elseif($activeTab === 'balances')
                <!-- Unpaid Invoices Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">
                                    Invoice</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Customer</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Due Date</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Total</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Results</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6"><span class="sr-only">Action</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($unpaidInvoices as $inv)
                                <tr>
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-900 sm:pl-6 font-medium">
                                        {{ $inv->invoice_number }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $inv->customer->name }}</td>
                                    <td
                                        class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 {{ $inv->payment_status === 'overdue' ? 'text-red-600 font-bold' : '' }}">
                                        {{ $inv->due_date ? $inv->due_date->format('d M Y') : '-' }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900">CHF
                                        {{ number_format($inv->total, 2) }}</td>
                                    <td class="whitespace-nowrap px-3 py-4 text-sm font-bold text-red-600">CHF
                                        {{ number_format($inv->balance, 2) }}</td>
                                    <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                        <button type="button" onclick="openPaymentModal({{ $inv->id }}, {{ $inv->balance }}, '{{ $inv->invoice_number }} - {{ $inv->customer->name }}')" class="text-indigo-600 hover:text-indigo-900">Pay</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-8 text-center text-sm text-gray-500">No unpaid invoices! 🎉</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @elseif($activeTab === 'invoices')
                <!-- Invoices Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Invoice #</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Customer</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Due Date</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Total</th>
                                <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Balance</th>
                                <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse($invoices as $invoice)
                            <tr>
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">{{ $invoice->invoice_number }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                    <div class="font-medium text-gray-900">{{ $invoice->customer->name ?? 'Unknown Customer' }}</div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm">
                                    @php
                                        $statusColor = match($invoice->payment_status) {
                                            'paid' => 'bg-green-50 text-green-700 ring-green-600/20',
                                            'partial' => 'bg-yellow-50 text-yellow-800 ring-yellow-600/20',
                                            'overdue' => 'bg-red-50 text-red-700 ring-red-600/20',
                                            default => 'bg-gray-50 text-gray-600 ring-gray-500/10'
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset uppercase {{ $statusColor }}">{{ $invoice->payment_status }}</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $invoice->due_date ? $invoice->due_date->format('d M Y') : 'N/A' }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900">CHF {{ number_format($invoice->total, 2) }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm font-bold {{ $invoice->balance > 0 ? 'text-red-600' : 'text-green-600' }}">CHF {{ number_format($invoice->balance, 2) }}</td>
                                <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                    @if($invoice->balance > 0)
                                        <button type="button" onclick="openPaymentModal({{ $invoice->id }}, {{ $invoice->balance }}, '{{ $invoice->invoice_number }} - {{ $invoice->customer->name }}')" class="text-indigo-600 hover:text-indigo-900 mr-2">Pay</button>
                                    @endif
                                    <a href="{{ route('invoices.show', $invoice) }}" class="text-gray-600 hover:text-gray-900">View</a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="px-3 py-8 text-center text-sm text-gray-500">No invoices found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-gray-200 px-4 py-3 sm:px-6">
                    {{ $invoices->appends(['tab' => 'invoices'])->links() }}
                </div>
            
            @endif
    </div>
</div>

<!-- Register Payment Modal -->
<div id="payment-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity backdrop-blur-sm" onclick="closePaymentModal()"></div>
    <div class="flex min-h-screen items-center justify-center p-4 text-center">
        <div class="relative w-full max-w-lg transform rounded-xl bg-white p-8 text-left shadow-2xl ring-1 ring-indigo-100 transition-all">
            
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold leading-6 text-indigo-950" id="modal-title">Register Payment</h3>
                <button onclick="closePaymentModal()" class="text-indigo-300 hover:text-indigo-500 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form action="{{ route('payments.store') }}" method="POST" id="payment-form" class="space-y-6">
                @csrf

                <!-- Error Display -->
                @if($errors->any())
                    <div class="rounded-md bg-red-50 p-4 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Submission Error</h3>
                                <ul class="mt-1 list-disc pl-5 text-sm text-red-700">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif
                
                <!-- STEP 1: Method & Amount -->
                <div id="step-1" class="space-y-6">
                    <div>
                        <label for="invoice_id" class="block text-sm font-medium text-indigo-900 mb-2">Invoice</label>
                        <div class="relative">
                            <!-- Select View -->
                            <select id="invoice_id_select" name="invoice_id_select" onchange="updateAmount()" 
                                class="block w-full rounded-lg border-0 py-2 pl-4 pr-10 text-indigo-900 ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                <option value="">Select Invoice</option>
                                @foreach($unpaidInvoices as $inv)
                                    <option value="{{ $inv->id }}" data-balance="{{ $inv->balance }}" data-text="{{ $inv->invoice_number }} - {{ $inv->customer->name }}">
                                        {{ $inv->invoice_number }} - {{ $inv->customer->name }} (Bal: {{ $inv->balance }})
                                    </option>
                                @endforeach
                            </select>

                            <!-- Locked View -->
                            <div id="invoice_locked_display" class="hidden">
                                <div class="block w-full rounded-lg border-0 py-2 px-4 bg-gray-50 text-gray-500 ring-1 ring-inset ring-gray-200 sm:text-sm sm:leading-6">
                                    <span id="locked_invoice_text"></span>
                                </div>
                                <input type="hidden" name="invoice_id" id="invoice_id_hidden">
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label for="amount" class="block text-sm font-medium text-indigo-900 mb-2">Amount (CHF)</label>
                        <input type="number" step="0.01" name="amount" id="amount" required 
                            class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                            placeholder="0.00">
                    </div>
                    
                    <div>
                        <label for="method" class="block text-sm font-medium text-indigo-900 mb-2">Payment Method</label>
                        <select id="method" name="method" 
                            class="block w-full rounded-lg border-0 py-2 pl-4 pr-10 text-indigo-900 ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            <option value="cash">Cash</option>
                            <option value="card">Credit Card</option>
                            <option value="twint">Twint</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label for="payment_date" class="block text-sm font-medium text-indigo-900 mb-2">Date</label>
                        <input type="date" name="payment_date" id="payment_date" value="{{ date('Y-m-d') }}" required 
                            class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                    </div>
                    
                    <div class="pt-4 flex justify-end gap-3">
                        <button type="button" onclick="closePaymentModal()" 
                            class="inline-flex items-center px-4 py-2 border border-red-200 text-red-600 rounded-lg text-sm font-semibold hover:bg-red-50 transition-all duration-200 shadow-sm">
                            Cancel
                        </button>
                        <button type="button" onclick="goToStep2()" 
                            class="inline-flex items-center px-6 py-2 bg-indigo-900 text-white rounded-lg text-sm font-semibold hover:bg-indigo-800 hover:shadow-lg transition-all duration-200 shadow-sm">
                            Continue
                        </button>
                    </div>
                </div>

                <!-- STEP 2: Details (Dynamic) -->
                <div id="step-2" class="hidden space-y-6">
                    
                    <!-- Card Details Form -->
                    <div id="details-card" class="hidden space-y-4">
                        <div class="bg-indigo-50 p-4 rounded-lg border border-indigo-100 flex items-center gap-3">
                             <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                             <span class="text-indigo-900 font-medium">Enter Credit Card Details</span>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-indigo-900 mb-2">Cardholder Name</label>
                            <input type="text" class="block w-full rounded-lg border-0 py-2.5 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="John Doe">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-indigo-900 mb-2">Card Number</label>
                            <input type="text" id="fake_card_number" onchange="updateTransactionRef(this.value)" class="block w-full rounded-lg border-0 py-2.5 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="0000 0000 0000 0000">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-indigo-900 mb-2">Expiry</label>
                                <input type="text" class="block w-full rounded-lg border-0 py-2.5 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="MM/YY">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-indigo-900 mb-2">CVC</label>
                                <input type="text" class="block w-full rounded-lg border-0 py-2.5 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="123">
                            </div>
                        </div>
                    </div>

                    <!-- Generic/Twint Details -->
                    <div id="details-generic" class="hidden space-y-4">
                         <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-4">
                            <p class="text-sm text-gray-600">Please confirm payment details below.</p>
                        </div>
                         
                        <div id="generic-ref-container">
                            <label for="transaction_reference" class="block text-sm font-medium text-indigo-900 mb-2" id="generic-ref-label">Transaction Reference</label>
                            <input type="text" name="transaction_reference" id="transaction_reference"
                                class="block w-full rounded-lg border-0 py-2.5 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                placeholder="Receipt # or Reference">
                        </div>
                    </div>

                    <!-- Notes (Always Visible in Step 2) -->
                    <div>
                        <label for="notes" class="block text-sm font-medium text-indigo-900 mb-2">Notes (Optional)</label>
                        <textarea name="notes" id="notes" rows="2"
                            class="block w-full rounded-lg border-0 py-2.5 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                            placeholder="Add note..."></textarea>
                    </div>

                    <div class="pt-4 flex justify-end gap-3">
                        <button type="button" onclick="goToStep1()" 
                            class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-50 transition-all duration-200 shadow-sm">
                            Back
                        </button>
                        <button type="submit" id="pay-button"
                            class="inline-flex items-center px-6 py-2 bg-indigo-900 text-white rounded-lg text-sm font-semibold hover:bg-indigo-800 hover:shadow-lg transition-all duration-200 shadow-sm">
                            Pay <span id="pay-amount-display" class="ml-1"></span>
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
    // State
    let currentInvoiceId = null;

    function openPaymentModal(invoiceId = null, amount = null, invoiceText = null) {
        document.getElementById('payment-modal').classList.remove('hidden');
        resetWizard();
        
        const select = document.getElementById('invoice_id_select');
        const hiddenInput = document.getElementById('invoice_id_hidden');
        const lockedDisplay = document.getElementById('invoice_locked_display');
        const lockedText = document.getElementById('locked_invoice_text');
        const amountInput = document.getElementById('amount');

        if (invoiceId) {
            // Locked Mode
            currentInvoiceId = invoiceId;
            select.classList.add('hidden');
            select.removeAttribute('name'); 
            select.required = false;

            lockedDisplay.classList.remove('hidden');
            hiddenInput.setAttribute('name', 'invoice_id'); // Active
            hiddenInput.value = invoiceId;
            lockedText.textContent = invoiceText;
            
            amountInput.value = amount;
            amountInput.readOnly = true;
            amountInput.classList.add('bg-gray-50', 'text-gray-500');
        } else {
            // Select Mode
            currentInvoiceId = null;
            select.classList.remove('hidden');
            select.setAttribute('name', 'invoice_id'); // Active
            select.required = true;
            select.value = ""; 

            lockedDisplay.classList.add('hidden');
            hiddenInput.removeAttribute('name'); // Inactive
            hiddenInput.value = "";
            
            amountInput.value = "";
            amountInput.readOnly = false;
            amountInput.classList.remove('bg-gray-50', 'text-gray-500');
        }
    }
    
    function closePaymentModal() {
        document.getElementById('payment-modal').classList.add('hidden');
    }

    function resetWizard() {
        document.getElementById('step-1').classList.remove('hidden');
        document.getElementById('step-2').classList.add('hidden');
        document.getElementById('modal-title').innerText = "Register Payment";
    }

    function goToStep2() {
        const method = document.getElementById('method').value;
        const amount = document.getElementById('amount').value;
        
        if (!amount || amount <= 0) {
            alert('Please enter a valid amount');
            return;
        }

        // UI Updates
        document.getElementById('step-1').classList.add('hidden');
        document.getElementById('step-2').classList.remove('hidden');
        document.getElementById('modal-title').innerText = method === 'card' ? 'Payment Details' : 'Confirm Payment';
        document.getElementById('pay-amount-display').innerText = "CHF " + amount;

        // Toggle Details Sections
        document.getElementById('details-card').classList.add('hidden');
        document.getElementById('details-generic').classList.add('hidden');
        
        if (method === 'card') {
            document.getElementById('details-card').classList.remove('hidden');
        } else {
            document.getElementById('details-generic').classList.remove('hidden');
            const refLabel = document.getElementById('generic-ref-label');
            const refInput = document.getElementById('transaction_reference');
            
            if (method === 'twint') {
                refLabel.innerText = "Twint Reference";
                refInput.placeholder = "e.g. 123 456";
            } else if (method === 'bank_transfer') {
                refLabel.innerText = "Bank Transaction ID";
                refInput.placeholder = "e.g. EB123456";
            } else {
                 refLabel.innerText = "Reference (Optional)";
                 refInput.placeholder = "Receipt #";
            }
        }
    }

    function goToStep1() {
        document.getElementById('step-2').classList.add('hidden');
        document.getElementById('step-1').classList.remove('hidden');
        document.getElementById('modal-title').innerText = "Register Payment";
    }
    
    function updateAmount() {
        const select = document.getElementById('invoice_id_select');
        const option = select.options[select.selectedIndex];
        if(option && option.dataset.balance) {
            document.getElementById('amount').value = option.dataset.balance;
        }
    }

    function updateTransactionRef(cardVal) {
        // Auto-fill transaction reference with last 4 digits
        if (cardVal && cardVal.length > 4) {
            const last4 = cardVal.slice(-4);
            document.getElementById('transaction_reference').value = "Card Ending " + last4;
        }
    }
</script>
@endsection