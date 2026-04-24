<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* Print-first styling. Kept dependency-free so the same page works
           in the browser and via Chrome headless `print-to-pdf`. */
        @page { size: A4; margin: 16mm 14mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #111; line-height: 1.4; font-size: 11pt; margin: 0; }
        .invoice { max-width: 780px; margin: 0 auto; padding: 12px 0; }
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
<div class="invoice">
    <div class="row">
        <div class="stack">
            <h1>{{ $invoice->tenant?->name ?? config('app.name') }}</h1>
            <div class="muted">
                {{ $invoice->tenant?->address }}<br>
                {{ $invoice->tenant?->postal_code }} {{ $invoice->tenant?->city }}<br>
                @if ($invoice->tenant?->vat_number) VAT: {{ $invoice->tenant->vat_number }}<br>@endif
            </div>
        </div>
        <div class="stack" style="text-align:right">
            <h1 style="letter-spacing:0.08em">INVOICE</h1>
            <div class="muted">No. <strong>{{ $invoice->invoice_number }}</strong></div>
            <div class="muted">Issued {{ $invoice->issue_date?->format('d.m.Y') }}</div>
            @if ($invoice->due_date)
                <div class="muted">Due {{ $invoice->due_date->format('d.m.Y') }}</div>
            @endif
            <div class="muted" style="text-transform:uppercase">{{ $invoice->status }}</div>
        </div>
    </div>

    <h2>Bill to</h2>
    <div class="stack">
        <strong>{{ $invoice->customer?->name ?? '—' }}</strong>
        @if ($invoice->customer?->address)
            <div class="muted">{{ $invoice->customer->address }}</div>
        @endif
        @if ($invoice->customer?->email)
            <div class="muted">{{ $invoice->customer->email }}</div>
        @endif
        @if ($invoice->vehicle)
            <div class="muted">Vehicle: {{ $invoice->vehicle->make }} {{ $invoice->vehicle->model }} — {{ $invoice->vehicle->license_plate }}</div>
        @endif
    </div>

    <h2>Items</h2>
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="tr" style="width: 60px">Qty</th>
                <th class="tr" style="width: 90px">Unit</th>
                <th class="tr" style="width: 60px">VAT %</th>
                <th class="tr" style="width: 100px">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
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
        <tr><td class="lbl">Subtotal</td><td class="val" style="width:120px">{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
        <tr><td class="lbl">VAT</td><td class="val">{{ number_format((float) $invoice->tax_total, 2) }}</td></tr>
        @if ((float) $invoice->discount_total > 0)
            <tr><td class="lbl">Discount</td><td class="val">-{{ number_format((float) $invoice->discount_total, 2) }}</td></tr>
        @endif
        <tr class="grand"><td class="lbl">TOTAL ({{ config('crm.currency.code', 'CHF') }})</td><td class="val">{{ number_format((float) $invoice->total, 2) }}</td></tr>
        @if ((float) $invoice->paid_amount > 0)
            <tr><td class="lbl">Paid</td><td class="val">-{{ number_format((float) $invoice->paid_amount, 2) }}</td></tr>
            <tr class="grand"><td class="lbl">Balance due</td><td class="val">{{ number_format((float) ($invoice->total - $invoice->paid_amount), 2) }}</td></tr>
        @endif
    </table>

    @if ($invoice->notes)
        <h2>Notes</h2>
        <div class="muted" style="white-space:pre-line">{{ $invoice->notes }}</div>
    @endif

    @if ($invoice->tenant?->iban)
        <h2>Payment details</h2>
        <div class="muted">
            {{ $invoice->tenant->bank_name }}<br>
            IBAN: {{ $invoice->tenant->iban }}<br>
            Account holder: {{ $invoice->tenant->account_holder }}
        </div>
    @endif

    <div class="actions">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
        <a href="{{ route('invoices.show', $invoice) }}">Back to invoice</a>
    </div>
</div>
</body>
</html>
