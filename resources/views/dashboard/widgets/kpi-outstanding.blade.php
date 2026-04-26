{{--
    KPI #3 — Outstanding balance.

    Coral-tinted to signal "money owed" — a different visual hue
    from the brand-teal revenue card so the eye reads them as
    distinct flows. Clicking jumps to the unpaid-invoices tab.
--}}
@php
    /** @var array<int, float> $sparkSeries */
    $sparkSeries = $providerData[$widget['key']] ?? [];
    $outstanding = (float) ($stats['total_outstanding'] ?? 0);
    $overdueCount = (int) ($stats['overdue_invoices_count'] ?? 0);
    $valueLabel = "CHF " . number_format($outstanding, 0, '.', "'");
    $subtitleLabel = $overdueCount === 0
        ? 'No overdue invoices'
        : $overdueCount . ' overdue';
    $hrefUrl = route('finance.index', ['tab' => 'unpaid']);
@endphp

<x-dashboard.kpi-card
    title="Outstanding"
    :value="$valueLabel"
    :subtitle="$subtitleLabel"
    :href="$hrefUrl"
    accent="accent">
    <x-slot:spark>
        <x-dashboard.sparkline :series="$sparkSeries" color="accent" />
    </x-slot:spark>
</x-dashboard.kpi-card>
