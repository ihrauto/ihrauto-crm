{{--
    Dashboard chart card — wrapper for any larger analytic widget
    (line chart, donut, heatmap, bar, etc.).

    Provides shared chrome:
        ┌──────────────────────────────────────────┐
        │  TITLE                       [filter ▼]  │
        │  optional subtitle / context              │
        ├──────────────────────────────────────────┤
        │                                          │
        │            <chart slot>                  │
        │                                          │
        └──────────────────────────────────────────┘

    Surface stays bg-white, no border, no shadow (theme 2026-04-26).
    10px corners via the global borderRadius override.

    @props:
        title       string         widget heading (e.g. "Monthly revenue")
        subtitle    string|null    one-line context under the title
        filterSlot  slot           optional filter slot (e.g. a "<select>")
        actionSlot  slot           optional top-right action menu (...)
        chart       slot           required: the chart container element

    Usage:
        <x-dashboard.chart-card title="Monthly revenue" subtitle="Last 12 months">
            <x-slot name="filter">
                <select class="...">…</select>
            </x-slot>
            <x-slot name="chart">
                <div id="revenue-chart" data-series='@json($series)'></div>
            </x-slot>
        </x-dashboard.chart-card>
--}}
@props([
    'title',
    'subtitle' => null,
])

<div class="bg-white rounded-xl p-5 h-full flex flex-col">
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div class="min-w-0">
            <h3 class="text-base font-bold text-neutral-900 tracking-tight">{{ $title }}</h3>
            @if($subtitle)
                <p class="mt-0.5 text-xs text-neutral-500">{{ $subtitle }}</p>
            @endif
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
            @isset($filter)
                <div>{{ $filter }}</div>
            @endisset
            @isset($action)
                <div>{{ $action }}</div>
            @endisset
        </div>
    </div>

    <div class="mt-4 flex-1 min-h-0">
        {{ $chart ?? $slot }}
    </div>
</div>
