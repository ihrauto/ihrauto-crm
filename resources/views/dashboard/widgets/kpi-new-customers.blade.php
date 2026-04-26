{{--
    KPI #4 — New customers.

    Counts new customer registrations over the last 30 days. Uses
    the same growth comparison logic the legacy stats already
    compute (current month vs previous month).
--}}
@php
    /** @var array<int, float> $sparkSeries */
    $sparkSeries = $providerData[$widget['key']] ?? [];
    // The series is a daily count for the last 30 days — sum it for the headline.
    $newThisMonth = (int) array_sum($sparkSeries);
    $growth = isset($stats['customer_growth']) ? (float) $stats['customer_growth'] : null;
    $hrefUrl = route('customers.index');
@endphp

<x-dashboard.kpi-card
    title="New customers"
    :value="$newThisMonth"
    subtitle="Last 30 days"
    :delta="$growth"
    delta-suffix="vs prior 30d"
    :href="$hrefUrl"
    accent="brand">
    <x-slot:spark>
        <x-dashboard.sparkline :series="$sparkSeries" color="brand" />
    </x-slot:spark>
</x-dashboard.kpi-card>
