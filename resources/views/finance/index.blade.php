@extends('layouts.app')

@section('title', 'FINANCE')

@section('content')
    {{-- Bug review UX-02: payment-modal JS is declared at the TOP of the
         section so every function is parsed before any inline onclick
         handler below can fire. Function declarations reference DOM IDs
         that are defined later in the markup; that's fine — getElementById
         is evaluated at call time (click time), not at parse time. --}}
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

        // Bug review UX-05: updateAmount now also syncs the hidden
        // invoice_id field when the user switches selection mid-flow.
        // Previously only the amount was updated; if the form was opened
        // in "locked" mode and then the user somehow changed the visible
        // select, the submit would target the original locked invoice
        // while displaying the new balance. Keep both fields in lock-step.
        function updateAmount() {
            const select = document.getElementById('invoice_id_select');
            const option = select.options[select.selectedIndex];
            const hiddenInput = document.getElementById('invoice_id_hidden');

            if (option && option.dataset.balance) {
                document.getElementById('amount').value = option.dataset.balance;
                // Keep the hidden input in sync in case the form is in a
                // hybrid state — harmless when the select itself carries
                // the invoice_id name.
                if (hiddenInput) {
                    hiddenInput.value = option.value;
                }
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

    <div class="space-y-8">

        <!-- Top Stats Overview -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Revenue Month -->
            <div class="overflow-hidden rounded-xl bg-white px-4 py-5 shadow-sm ring-1 ring-gray-900/5 sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-500">Revenue (This Month)</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900">CHF
                    {{ number_format($overview['revenue_month'], 2) }}
                </dd>
            </div>

            <!-- Revenue Year -->
            <div class="overflow-hidden rounded-xl bg-white px-4 py-5 shadow-sm ring-1 ring-gray-900/5 sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-500">Revenue (YTD)</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-indigo-600">CHF
                    {{ number_format($overview['revenue_year'], 2) }}
                </dd>
            </div>

            <!-- Unpaid -->
            <div class="overflow-hidden rounded-xl bg-white px-4 py-5 shadow-sm ring-1 ring-gray-900/5 sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-500">Unpaid</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-yellow-600">CHF
                    {{ number_format($overview['unpaid_total'], 2) }}
                </dd>
            </div>

            <!-- Overdue -->
            <div class="overflow-hidden rounded-xl bg-white px-4 py-5 shadow-sm ring-1 ring-gray-900/5 sm:p-6">
                <dt class="truncate text-sm font-medium text-gray-500">Overdue (Critical)</dt>
                <dd class="mt-1 text-3xl font-semibold tracking-tight text-red-600">CHF
                    {{ number_format($overview['overdue_total'], 2) }}
                </dd>
            </div>
        </div>


        <!-- Main Content -->
        <div class="md:flex md:items-center md:justify-between">
            <div class="min-w-0 flex-1">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <a href="{{ route('finance.index', ['tab' => 'issued']) }}"
                            class="{{ $activeTab === 'issued' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            ISSUED
                        </a>
                        <a href="{{ route('finance.index', ['tab' => 'unpaid']) }}"
                            class="{{ $activeTab === 'unpaid' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            UNPAID
                        </a>
                        <a href="{{ route('finance.index', ['tab' => 'draft']) }}"
                            class="{{ $activeTab === 'draft' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            DRAFT
                            {{-- Uses the pre-computed $draftCount so the badge
                                 shows on EVERY tab, not only when Draft itself
                                 is active. $draftInvoices isn't populated on
                                 other tabs any more (B-5 scalability branch). --}}
                            @if(($draftCount ?? 0) > 0)
                                <span class="ml-1 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ $draftCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('finance.index', ['tab' => 'paid']) }}"
                            class="{{ $activeTab === 'paid' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            PAID
                        </a>
                        <a href="{{ route('finance.index', ['tab' => 'all']) }}"
                            class="{{ $activeTab === 'all' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            ALL
                        </a>

                    </nav>
                </div>
            </div>
            <div class="mt-4 flex md:ml-4 md:mt-0">
                <form action="{{ route('finance.index') }}" method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">
                    <div class="relative">
                        <input type="text" name="search" value="{{ $search ?? '' }}" 
                            placeholder="Search by name, vehicle..."
                            class="block w-64 rounded-lg border-0 py-2.5 pl-4 pr-10 text-indigo-900 bg-white shadow-sm ring-1 ring-inset ring-indigo-300 placeholder:text-indigo-400 focus:ring-2 focus:ring-inset focus:ring-indigo-500 sm:text-sm sm:leading-6">
                        <button type="submit" class="absolute inset-y-0 right-0 flex items-center pr-3">
                            <svg class="h-5 w-5 text-indigo-500 hover:text-indigo-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </button>
                    </div>
                    @if($search)
                        <a href="{{ route('finance.index', ['tab' => $activeTab]) }}" class="text-sm text-gray-500 hover:text-indigo-600">Clear</a>
                    @endif
                </form>
            </div>
        </div>

        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
            @if($activeTab === 'unpaid')
                <!-- UNPAID Invoices Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">
                                            Invoice</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Customer</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Due Date</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Total</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Balance</th>
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
                                                <button type="button"
                                                    data-invoice-id="{{ $inv->id }}"
                                                    data-invoice-balance="{{ $inv->balance }}"
                                                    data-invoice-text="{{ $inv->invoice_number }} - {{ $inv->customer->name }}"
                                                    onclick="openPaymentModal(this.dataset.invoiceId, this.dataset.invoiceBalance, this.dataset.invoiceText)"
                                                    class="text-indigo-600 hover:text-indigo-900">Pay</button>
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
                        <div class="px-4 py-3 sm:px-6"></div>

            @elseif($activeTab === 'draft')
                <!-- DRAFT Invoices Table with bulk-select toolbar -->
                <div x-data="{
                        selected: [],
                        all: {{ $draftInvoices->pluck('id')->toJson() }},
                        toggleAll(e) { this.selected = e.target.checked ? [...this.all] : []; }
                    }">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-gray-50/50 sm:px-6">
                        <p class="text-sm text-gray-600">
                            <span x-text="selected.length"></span> selected
                            <span class="text-gray-400">/ {{ $draftInvoices->count() }} draft(s)</span>
                        </p>
                        <form method="post" action="{{ route('invoices.bulk-issue') }}" x-show="selected.length > 0">
                            @csrf
                            <template x-for="id in selected" :key="id">
                                <input type="hidden" name="invoice_ids[]" :value="id">
                            </template>
                            <button type="submit"
                                    class="inline-flex items-center rounded-md bg-accent-500 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-accent-500"
                                    onclick="return confirm('Issue all selected drafts?')">
                                Issue selected (<span x-text="selected.length"></span>)
                            </button>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-300">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="pl-4 py-3.5 sm:pl-6 w-8">
                                        <input type="checkbox" @change="toggleAll($event)"
                                               :checked="selected.length === all.length && all.length > 0"
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    </th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Invoice</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Customer</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Created</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Total</th>
                                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @forelse($draftInvoices as $inv)
                                    <tr>
                                        <td class="pl-4 py-4 sm:pl-6">
                                            <input type="checkbox" value="{{ $inv->id }}" x-model="selected"
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-gray-900">{{ $inv->invoice_number }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                            {{ $inv->customer?->name ?? 'Unknown' }}
                                            @if($inv->customer?->email)
                                                <span class="block text-xs text-gray-400">{{ $inv->customer->email }}</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $inv->created_at->format('d M Y') }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900">CHF {{ number_format($inv->total, 2) }}</td>
                                        <td class="whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6 space-x-3">
                                            @if($inv->customer?->email)
                                                <form method="post" action="{{ route('invoices.issue-and-send', $inv) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-indigo-600 hover:text-indigo-900" title="Issue & email to {{ $inv->customer->email }}">Issue &amp; send</button>
                                                </form>
                                            @endif
                                            <form method="post" action="{{ route('invoices.issue', $inv) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="text-gray-700 hover:text-gray-900">Issue</button>
                                            </form>
                                            <a href="{{ route('invoices.edit', $inv) }}" class="text-gray-600 hover:text-gray-900">Edit</a>
                                            <a href="{{ route('invoices.show', $inv) }}" class="text-gray-600 hover:text-gray-900">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-3 py-8 text-center text-sm text-gray-500">No draft invoices.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-3 sm:px-6"></div>
                </div>

            @elseif($activeTab === 'paid')
                    <!-- PAID Payments Table -->
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
                                @forelse($paidPayments as $payment)
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
                                        <td colspan="5" class="px-3 py-8 text-center text-sm text-gray-500">No payments recorded.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-gray-200 px-4 py-3 sm:px-6">
                        {{ $paidPayments->appends(['tab' => 'paid'])->links() }}
                    </div>

                @elseif($activeTab === 'all')
                    <!-- ALL Invoices Table -->
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
                                                $statusColor = match ($invoice->payment_status) {
                                                    'paid' => 'bg-green-50 text-green-700 ring-green-600/20',
                                                    'partial' => 'bg-yellow-50 text-yellow-800 ring-yellow-600/20',
                                                    'overdue' => 'bg-red-50 text-red-700 ring-red-600/20',
                                                    'draft' => 'bg-slate-50 text-slate-700 ring-slate-500/20',
                                                    'void' => 'bg-red-50 text-red-700 ring-red-600/20',
                                                    default => 'bg-gray-50 text-gray-600 ring-gray-500/10'
                                                };
                                            @endphp
                                            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset uppercase {{ $statusColor }}">{{ $invoice->payment_status }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $invoice->due_date ? $invoice->due_date->format('d M Y') : 'N/A' }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900">CHF {{ number_format($invoice->total, 2) }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm font-bold {{ $invoice->balance > 0 ? 'text-red-600' : 'text-green-600' }}">
                                            @if($invoice->balance > 0)
                                                CHF {{ number_format($invoice->balance, 2) }}
                                            @else
                                                CHF {{ number_format($invoice->total, 2) }}
                                            @endif
                                        </td>
                                        <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6 space-x-2">
                                            @if($invoice->isDraft())
                                                {{-- Draft: the operator hasn't sent the invoice yet. "Pay" makes
                                                     no sense — surface Issue + Edit instead. --}}
                                                <form method="post" action="{{ route('invoices.issue', $invoice) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-indigo-600 hover:text-indigo-900">Issue</button>
                                                </form>
                                                <a href="{{ route('invoices.edit', $invoice) }}" class="text-gray-600 hover:text-gray-900">Edit</a>
                                            @elseif($invoice->balance > 0 && ! $invoice->isVoid())
                                                <button type="button"
                                                    data-invoice-id="{{ $invoice->id }}"
                                                    data-invoice-balance="{{ $invoice->balance }}"
                                                    data-invoice-text="{{ $invoice->invoice_number }} - {{ $invoice->customer->name }}"
                                                    onclick="openPaymentModal(this.dataset.invoiceId, this.dataset.invoiceBalance, this.dataset.invoiceText)"
                                                    class="text-indigo-600 hover:text-indigo-900">Pay</button>
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
                        {{ $invoices->appends(['tab' => 'all'])->links() }}
                    </div>

                @else
                    <!-- ISSUED Invoices Table (Default) -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-300">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Invoice #</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Customer</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Due Date</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Total</th>
                                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @forelse($issuedInvoices as $invoice)
                                    <tr>
                                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">{{ $invoice->invoice_number }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                            <div class="font-medium text-gray-900">{{ $invoice->customer->name ?? 'Unknown Customer' }}</div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                                            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset bg-blue-50 text-blue-700 ring-blue-600/20 uppercase">{{ $invoice->status }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">{{ $invoice->due_date ? $invoice->due_date->format('d M Y') : 'N/A' }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900">CHF {{ number_format($invoice->total, 2) }}</td>
                                        <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                            <button type="button"
                                                data-invoice-id="{{ $invoice->id }}"
                                                data-invoice-balance="{{ $invoice->balance }}"
                                                data-invoice-text="{{ $invoice->invoice_number }} - {{ $invoice->customer->name ?? 'Unknown' }}"
                                                onclick="openPaymentModal(this.dataset.invoiceId, this.dataset.invoiceBalance, this.dataset.invoiceText)"
                                                class="text-indigo-600 hover:text-indigo-900 mr-2">Pay</button>
                                            <a href="{{ route('invoices.show', $invoice) }}" class="text-gray-600 hover:text-gray-900">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-3 py-8 text-center text-sm text-gray-500">No issued invoices.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-3 sm:px-6"></div>

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
                    <button type="button" onclick="closePaymentModal()" class="text-indigo-300 hover:text-indigo-500 transition-colors">
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
                                class="inline-flex items-center px-6 py-2 bg-accent-600 text-white rounded-lg text-sm font-semibold hover:bg-accent-600 hover:shadow-lg transition-all duration-200 shadow-sm">
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
                                class="inline-flex items-center px-6 py-2 bg-accent-600 text-white rounded-lg text-sm font-semibold hover:bg-accent-600 hover:shadow-lg transition-all duration-200 shadow-sm">
                                Pay <span id="pay-amount-display" class="ml-1"></span>
                            </button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>

    {{-- Bug review UX-02: payment-modal JS moved to the top of @section
         so the function declarations are parsed before any inline onclick
         handler (openPaymentModal, goToStep2, updateAmount, etc.) can be
         invoked. Previously the script sat at the END of the file, which
         worked in practice but left a theoretical race where a fast
         click before the parser reached line 555 could throw
         ReferenceError. See the old top-of-file block for the full
         implementations. --}}
@endsection