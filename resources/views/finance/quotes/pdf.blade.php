<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('crm.quote.title') }} {{ $quote->quote_number }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* Matches finance.invoices.pdf styling so the two documents feel
           like one product. Print-first; browser "Save as PDF" does the
           export. */
        @page { size: A4; margin: 16mm 14mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #111; line-height: 1.4; font-size: 11pt; margin: 0; }
        .doc { max-width: 780px; margin: 0 auto; padding: 12px 0; }
        .row { display: flex; justify-content: space-between; align-items: flex-start; }
        .stack > * + * { margin-top: 4px; }
        h1 { font-size: 22pt; margin: 0 0 8px; letter-spacing: 0.02em; }
        h2 { font-size: 11pt; color: #555; text-transform: uppercase; letter-spacing: 0.05em; margin: 18px 0 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 10.5pt; }
        thead th { border-bottom: 1.5px solid #111; padding: 6px 4px; text-align: left; }
        tbody td { border-bottom: 1px solid #e5e7eb; padding: 6px 4px; }
        .tr { text-align: right; }
        .totals td { padding: 4px 4px; }
        .totals .lbl { text-align: right; color: #555; }
        .totals .val { text-align: right; font-variant-numeric: tabular-nums; }
        .grand { border-top: 1.5px solid #111; font-weight: 700; }
        .muted { color: #6b7280; font-size: 9pt; }
        .actions { margin-top: 16px; }
        .actions a, .actions button { font: inherit; padding: 6px 12px; border: 1px solid #111; background: #fff; color: #111; text-decoration: none; cursor: pointer; border-radius: 4px; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
<div class="doc">
    <div class="row">
        <div class="stack">
            <h1>{{ $quote->tenant?->name ?? config('app.name') }}</h1>
            <div class="muted">
                {{ $quote->tenant?->address }}<br>
                {{ $quote->tenant?->postal_code }} {{ $quote->tenant?->city }}<br>
                @if ($quote->tenant?->vat_number) VAT: {{ $quote->tenant->vat_number }}<br>@endif
            </div>
        </div>
        <div class="stack" style="text-align:right">
            <h1 style="letter-spacing:0.08em">{{ strtoupper(__('crm.quote.title')) }}</h1>
            <div class="muted">{{ __('crm.quote.number') }}: <strong>{{ $quote->quote_number }}</strong></div>
            <div class="muted">{{ __('crm.quote.issued') }} {{ $quote->issue_date?->format('d.m.Y') }}</div>
            @if ($quote->expiry_date)
                <div class="muted">{{ __('crm.quote.expires') }} {{ $quote->expiry_date->format('d.m.Y') }}</div>
            @endif
            <div class="muted" style="text-transform:uppercase">{{ __('crm.quote.status_'.$quote->status) }}</div>
        </div>
    </div>

    <h2>{{ __('crm.finance.bill_to') }}</h2>
    <div class="stack">
        <strong>{{ $quote->customer?->name ?? '—' }}</strong>
        @if ($quote->customer?->address)
            <div class="muted">{{ $quote->customer->address }}</div>
        @endif
        @if ($quote->vehicle)
            <div class="muted">{{ __('crm.work_order.technician') === 'Technician' ? 'Vehicle' : ($app->getLocale() === 'de' ? 'Fahrzeug' : 'Véhicule') }}:
                {{ $quote->vehicle->make }} {{ $quote->vehicle->model }} — {{ $quote->vehicle->license_plate }}</div>
        @endif
    </div>

    <h2>{{ __('crm.quote.line_items') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('crm.quote.description') }}</th>
                <th class="tr" style="width: 60px">{{ __('crm.quote.quantity') }}</th>
                <th class="tr" style="width: 90px">{{ __('crm.quote.unit_price') }}</th>
                <th class="tr" style="width: 60px">{{ __('crm.quote.vat_rate') }}</th>
                <th class="tr" style="width: 100px">{{ __('crm.quote.line_total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quote->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="tr">{{ $item->quantity }}</td>
                    <td class="tr">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="tr">{{ number_format((float) $item->tax_rate, 1) }}</td>
                    <td class="tr">{{ number_format((float) $item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="margin-top:8px">
        <tr><td class="lbl">{{ __('crm.finance.subtotal') }}</td>
            <td class="val" style="width:120px">{{ number_format((float) $quote->subtotal, 2) }}</td></tr>
        <tr><td class="lbl">{{ __('crm.finance.tax') }}</td>
            <td class="val">{{ number_format((float) $quote->tax_total, 2) }}</td></tr>
        @if ((float) $quote->discount_total > 0)
            <tr><td class="lbl">{{ __('crm.finance.discount') }}</td>
                <td class="val">-{{ number_format((float) $quote->discount_total, 2) }}</td></tr>
        @endif
        <tr class="grand"><td class="lbl">{{ __('crm.finance.total') }} ({{ config('crm.currency.code', 'CHF') }})</td>
            <td class="val">{{ number_format((float) $quote->total, 2) }}</td></tr>
    </table>

    @if ($quote->notes)
        <h2>{{ __('crm.common.no_results') === 'No results found.' ? 'Notes' : ($app->getLocale() === 'de' ? 'Anmerkungen' : 'Remarques') }}</h2>
        <div class="muted" style="white-space:pre-line">{{ $quote->notes }}</div>
    @endif

    <div class="actions">
        <button type="button" onclick="window.print()">{{ __('crm.finance.print_save_pdf') }}</button>
        <a href="{{ route('quotes.show', $quote) }}">&larr;</a>
    </div>
</div>
</body>
</html>
