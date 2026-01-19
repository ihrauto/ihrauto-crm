@extends('layouts.app')

@section('title', 'Invoice ' . $invoice->invoice_number)

@section('content')
    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Action Bar -->
        <div class="flex items-center justify-between no-print">
            <div class="flex items-center gap-4">
                <a href="{{ route('finance.index', ['tab' => 'invoices']) }}"
                    class="inline-flex items-center px-4 py-2 border border-indigo-200 text-indigo-600 rounded-lg text-sm font-medium hover:bg-indigo-50 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to Invoices
                </a>
            </div>
            <div class="flex items-center gap-3">
                @if(!$invoice->locked_at)
                    <a href="{{ route('invoices.edit', $invoice) }}"
                        class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                        Edit
                    </a>
                @endif
                <button onclick="window.print()"
                    class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Print / PDF
                </button>
            </div>
        </div>

        <!-- Invoice Paper -->
        <div
            class="bg-white shadow-lg rounded-lg overflow-hidden border border-gray-100 print:shadow-none print:border-none">

            <!-- Header -->
            <div class="px-8 py-10 border-b border-gray-100">
                <div class="flex justify-between items-start">
                    <div>
                        <!-- Logo / Company Name -->
                        <div class="flex items-center gap-2">
                            <div
                                class="h-10 w-10 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold text-xl">
                                {{ substr(auth()->user()->tenant->name ?? 'I', 0, 1) }}
                            </div>
                            <span
                                class="text-2xl font-bold text-indigo-900 tracking-tight">{{ auth()->user()->tenant->name ?? 'IHRAUTO' }}</span>
                        </div>
                        <div class="mt-4 text-sm text-gray-500">
                            <p>{{ auth()->user()->tenant->address ?? '123 Garage Lane' }}</p>
                            <p>{{ auth()->user()->tenant->postal_code ?? '' }}
                                {{ auth()->user()->tenant->city ?? 'Zurich' }}
                            </p>
                            <p>{{ auth()->user()->tenant->country ?? 'Switzerland' }}</p>
                            @if(auth()->user()->tenant->uid_number)
                                <p class="mt-1">UID: {{ auth()->user()->tenant->uid_number }}</p>
                            @endif
                            @if(auth()->user()->tenant->vat_registered && auth()->user()->tenant->vat_number)
                                <p>VAT: {{ auth()->user()->tenant->vat_number }}</p>
                            @endif
                            <p class="mt-2">{{ auth()->user()->tenant->invoice_email ?? 'info@ihrauto.ch' }}</p>
                            @if(auth()->user()->tenant->invoice_phone)
                                <p>{{ auth()->user()->tenant->invoice_phone }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <h1 class="text-3xl font-bold text-gray-900 uppercase tracking-widest">Invoice</h1>
                        <p class="text-indigo-600 font-medium text-lg mt-1">{{ $invoice->invoice_number }}</p>

                        <div class="mt-4 space-y-1 text-sm text-gray-600">
                            <div class="flex justify-end gap-4">
                                <span>Issue Date:</span>
                                <span class="font-medium text-gray-900">{{ $invoice->issue_date->format('d M Y') }}</span>
                            </div>
                            <div class="flex justify-end gap-4">
                                <span>Due Date:</span>
                                <span
                                    class="font-medium text-gray-900">{{ $invoice->due_date ? $invoice->due_date->format('d M Y') : 'Due on Receipt' }}</span>
                            </div>
                            @if($invoice->work_order)
                                <div class="flex justify-end gap-4">
                                    <span>Work Order:</span>
                                    <span class="font-medium text-gray-900">#{{ $invoice->work_order->id }}</span>
                                </div>
                            @endif
                        </div>

                        <!-- Status Badge -->
                        <div class="mt-4 inline-block">
                            @php
                                $statusColor = match ($invoice->payment_status) {
                                    'paid' => 'bg-green-100 text-green-700 border-green-200',
                                    'partial' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                    'overdue' => 'bg-red-100 text-red-700 border-red-200',
                                    default => 'bg-gray-100 text-gray-700 border-gray-200'
                                };
                            @endphp
                            <span
                                class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide border {{ $statusColor }}">
                                {{ $invoice->payment_status }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer & Vehicle Info -->
            <div class="px-8 py-6 bg-gray-50/50 flex flex-col md:flex-row gap-12">
                <div class="flex-1">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Bill To</h3>
                    <div class="text-sm font-medium text-gray-900">
                        <p class="text-base">{{ $invoice->customer->name ?? 'Unknown Customer' }}</p>
                        <p class="text-gray-500 font-normal mt-1">{{ $invoice->customer->email ?? '' }}</p>
                        <p class="text-gray-500 font-normal">{{ $invoice->customer->phone ?? '' }}</p>
                        @if($invoice->customer && $invoice->customer->address)
                            <p class="text-gray-500 font-normal mt-1 max-w-xs">{{ $invoice->customer->address }}</p>
                        @endif
                    </div>
                </div>
                @if($invoice->vehicle)
                    <div class="flex-1">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Vehicle Details</h3>
                        <div class="text-sm font-medium text-gray-900">
                            <p class="text-base">{{ $invoice->vehicle->make }} {{ $invoice->vehicle->model }}</p>
                            <p class="text-gray-500 font-normal mt-1">{{ $invoice->vehicle->year }} •
                                {{ $invoice->vehicle->plate_number }}
                            </p>
                            <p class="text-gray-500 font-normal">VIN: {{ $invoice->vehicle->vin ?? 'N/A' }}</p>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Line Items -->
            <div class="px-8 py-6">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider w-12 text-center">#
                            </th>
                            <th class="py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Description</th>
                            <th class="py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider text-right w-24">
                                Qty</th>
                            <th class="py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider text-right w-32">
                                Price</th>
                            <th class="py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider text-right w-32">
                                Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($invoice->items as $index => $item)
                            <tr>
                                <td class="py-4 text-sm text-center text-gray-500">{{ $index + 1 }}</td>
                                <td class="py-4 text-sm font-medium text-gray-900">
                                    {{ $item->description }}
                                </td>
                                <td class="py-4 text-sm text-right text-gray-500">
                                    {{ preg_replace('/\.00$/', '', $item->quantity) }}
                                </td>
                                <td class="py-4 text-sm text-right text-gray-500">{{ number_format($item->unit_price, 2) }}</td>
                                <td class="py-4 text-sm text-right font-medium text-gray-900">
                                    {{ number_format($item->total, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="px-8 py-6 bg-gray-50/50 border-t border-gray-100">
                <div class="flex justify-end">
                    <div class="w-full md:w-1/3 space-y-3">
                        <div class="flex justify-between text-sm text-gray-500">
                            <span>Subtotal</span>
                            <span class="font-medium text-gray-900">CHF {{ number_format($invoice->subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm text-gray-500">
                            <span>Tax
                                ({{ number_format($invoice->subtotal > 0 ? ($invoice->tax_total / $invoice->subtotal) * 100 : 0, 1) }}%)</span>
                            <span class="font-medium text-gray-900">CHF {{ number_format($invoice->tax_total, 2) }}</span>
                        </div>
                        <div class="pt-3 border-t border-gray-200 flex justify-between items-center">
                            <span class="text-base font-bold text-gray-900">Total</span>
                            <span class="text-xl font-bold text-indigo-600">CHF
                                {{ number_format($invoice->total, 2) }}</span>
                        </div>
                        @if($invoice->total - $invoice->balance > 0)
                            <div class="flex justify-between text-sm text-green-600 pt-2">
                                <span>Paid</span>
                                <span class="font-medium">- CHF
                                    {{ number_format($invoice->total - $invoice->balance, 2) }}</span>
                            </div>
                        @endif
                        @if($invoice->balance > 0)
                            <div class="flex justify-between text-sm text-red-600 font-medium pt-1">
                                <span>Balance Due</span>
                                <span>CHF {{ number_format($invoice->balance, 2) }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Notes / Footer -->
            @if($invoice->notes)
                <div class="px-8 py-6 border-t border-gray-100">
                    <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Notes</h4>
                    <p class="text-sm text-gray-600 italic whitespace-pre-wrap">{{ $invoice->notes }}</p>
                </div>
            @endif

            <div class="px-8 py-6 bg-gray-900 text-gray-400 text-xs text-center">
                <p>Thank you for your business!</p>
                <div class="mt-1 flex justify-center gap-4 flex-wrap">
                    @if(auth()->user()->tenant->bank_name)
                        <span>Bank: {{ auth()->user()->tenant->bank_name }}</span>
                    @endif
                    @if(auth()->user()->tenant->iban)
                        <span>IBAN: {{ auth()->user()->tenant->iban }}</span>
                    @endif
                    @if(auth()->user()->tenant->account_holder)
                        <span>Holder: {{ auth()->user()->tenant->account_holder }}</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Print Styles -->
        <style>
            @media print {
                @page {
                    margin: 0;
                }

                body {
                    margin: 1.6cm;
                    background: white;
                    -webkit-print-color-adjust: exact;
                    zoom: 0.9;
                }

                .no-print {
                    display: none !important;
                }

                nav,
                header,
                aside {
                    display: none !important;
                }

                main {
                    margin: 0;
                    padding: 0;
                }

                .bg-white {
                    box-shadow: none !important;
                    border: none !important;
                }

                /* Compact Layout for Print */
                .px-8 {
                    padding-left: 0 !important;
                    padding-right: 0 !important;
                }

                .py-10 {
                    padding-top: 1rem !important;
                    padding-bottom: 1rem !important;
                }

                .py-6 {
                    padding-top: 0.75rem !important;
                    padding-bottom: 0.75rem !important;
                }

                .gap-12 {
                    gap: 2rem !important;
                }

                .text-3xl {
                    font-size: 1.5rem !important;
                }

                .text-2xl {
                    font-size: 1.25rem !important;
                }

                .text-xl {
                    font-size: 1.125rem !important;
                }

                .h-10 {
                    height: 2rem !important;
                    width: 2rem !important;
                }
            }
        </style>
    </div>
@endsection