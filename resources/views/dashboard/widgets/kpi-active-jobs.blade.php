{{--
    KPI #2 — Active jobs.

    Headline metric: count of in-progress work orders, with
    "/ N bays" suffix to give immediate capacity context. 30-day
    sparkline shows the daily new-WO rhythm.
--}}
@php
    /** @var array<int, float> $sparkSeries */
    $sparkSeries = $providerData[$widget['key']] ?? [];
    $activeJobs = (int) ($stats['active_jobs'] ?? 0);
    $totalBays = (int) config('crm.service_bays.count', 6);
    $unitLabel = '/ ' . $totalBays . ' bays';
    $subtitleLabel = $activeJobs === 0
        ? 'All bays free'
        : ($activeJobs >= $totalBays
            ? 'Shop at capacity'
            : ($totalBays - $activeJobs) . ' bays free');
    $hrefUrl = route('work-orders.index');
@endphp

<x-dashboard.kpi-card
    title="Active jobs"
    :value="$activeJobs"
    :unit="$unitLabel"
    :subtitle="$subtitleLabel"
    :href="$hrefUrl"
    accent="brand">
    <x-slot:spark>
        <x-dashboard.sparkline :series="$sparkSeries" color="neutral" />
    </x-slot:spark>
</x-dashboard.kpi-card>
