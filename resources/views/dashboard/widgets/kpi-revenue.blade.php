{{--
    KPI #1 — Revenue this month.

    Hero metric: CHF current_month_revenue with growth delta and
    30-day sparkline. Click → finance index.
--}}
@php
    /** @var array<int, float> $sparkSeries */
    $sparkSeries = $providerData[$widget['key']] ?? [];
    $revenue = (float) ($stats['monthly_revenue'] ?? 0);
    $growth = isset($stats['revenue_growth']) ? (float) $stats['revenue_growth'] : null;
    $valueLabel = "CHF " . number_format($revenue, 0, '.', "'");
    $subtitleLabel = now()->translatedFormat('F Y');
    $hrefUrl = route('finance.index');
@endphp

<x-dashboard.kpi-card
    title="Revenue this month"
    :value="$valueLabel"
    :subtitle="$subtitleLabel"
    :delta="$growth"
    delta-suffix="vs last month"
    :href="$hrefUrl"
    accent="brand">
    <x-slot:spark>
        <x-dashboard.sparkline :series="$sparkSeries" color="brand" />
    </x-slot:spark>
</x-dashboard.kpi-card>
