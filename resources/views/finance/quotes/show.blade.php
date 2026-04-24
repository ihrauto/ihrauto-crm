@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <x-flash-message />

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">{{ __('crm.quote.title') }} {{ $quote->quote_number }}</h1>
            <p class="text-sm text-gray-500">{{ $quote->customer?->name }} &middot; {{ $quote->issue_date?->format('d.m.Y') }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('quotes.pdf', $quote) }}" target="_blank"
               class="px-3 py-2 rounded bg-gray-100 text-sm hover:bg-gray-200">
                {{ __('crm.finance.print_save_pdf') }}
            </a>
            @if (in_array($quote->status, ['draft','sent']))
                <a href="{{ route('quotes.edit', $quote) }}"
                   class="px-3 py-2 rounded bg-gray-100 text-sm hover:bg-gray-200">{{ __('crm.common.edit') }}</a>
            @endif
            @if (in_array($quote->status, ['draft','sent','accepted']) && ! $quote->invoice)
                <form method="post" action="{{ route('quotes.convert-to-invoice', $quote) }}">
                    @csrf
                    <button type="submit" class="px-3 py-2 rounded bg-indigo-600 text-white text-sm hover:bg-indigo-700">
                        {{ __('crm.quote.convert_to_invoice') }}
                    </button>
                </form>
            @endif
            @if ($quote->invoice)
                <a href="{{ route('invoices.show', $quote->invoice) }}"
                   class="px-3 py-2 rounded bg-green-600 text-white text-sm hover:bg-green-700">
                    {{ __('crm.quote.view_invoice', ['number' => $quote->invoice->invoice_number]) }}
                </a>
            @endif
        </div>
    </div>

    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
            <div><span class="text-gray-500">Status:</span> <span class="capitalize">{{ $quote->status }}</span></div>
            <div><span class="text-gray-500">Expires:</span> {{ $quote->expiry_date?->format('d.m.Y') ?? '—' }}</div>
            <div><span class="text-gray-500">Vehicle:</span> {{ $quote->vehicle?->display_name ?? '—' }}</div>
            <div><span class="text-gray-500">Total:</span>
                {{ config('crm.currency.code','CHF') }} {{ number_format((float) $quote->total, 2) }}
            </div>
        </div>

        <table class="min-w-full text-sm border-t border-gray-200">
            <thead class="text-xs uppercase text-gray-500">
                <tr>
                    <th class="py-2 text-left">Description</th>
                    <th class="py-2 text-right">Qty</th>
                    <th class="py-2 text-right">Unit</th>
                    <th class="py-2 text-right">VAT %</th>
                    <th class="py-2 text-right">Line total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($quote->items as $item)
                    <tr>
                        <td class="py-2">{{ $item->description }}</td>
                        <td class="py-2 text-right">{{ $item->quantity }}</td>
                        <td class="py-2 text-right">{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="py-2 text-right">{{ number_format((float) $item->tax_rate, 1) }}</td>
                        <td class="py-2 text-right">{{ number_format((float) $item->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t border-gray-200 text-sm">
                <tr><td colspan="4" class="py-2 text-right text-gray-500">Subtotal</td>
                    <td class="py-2 text-right">{{ number_format((float) $quote->subtotal, 2) }}</td></tr>
                <tr><td colspan="4" class="py-2 text-right text-gray-500">Tax</td>
                    <td class="py-2 text-right">{{ number_format((float) $quote->tax_total, 2) }}</td></tr>
                <tr><td colspan="4" class="py-2 text-right font-semibold">Total</td>
                    <td class="py-2 text-right font-semibold">{{ number_format((float) $quote->total, 2) }}</td></tr>
            </tfoot>
        </table>

        @if ($quote->notes)
            <p class="mt-4 text-sm text-gray-700 whitespace-pre-line">{{ $quote->notes }}</p>
        @endif
    </div>

    <a href="{{ route('quotes.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; All quotes</a>
</div>
@endsection
