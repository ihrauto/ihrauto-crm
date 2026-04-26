{{--
    Dashboard delta pill — "+12.4%" / "-3.1%" indicator on KPI cards.

    Renders teal (positive) or coral (negative) per the 2026-04-26
    theme. Zero / null collapses to a neutral em-dash so the slot keeps
    the same height across the row.

    Usage:
        <x-dashboard.delta-pill :delta="12.4" />
        <x-dashboard.delta-pill :delta="-3.1" suffix="vs last 30d" />
        <x-dashboard.delta-pill :delta="null" />

    Props:
        delta   float|int|null   percentage change. Sign drives colour.
        suffix  string|null      optional secondary label e.g. "vs last 30d"
--}}
@props([
    'delta' => null,
    'suffix' => null,
])

@php
    $isPositive = is_numeric($delta) && (float) $delta > 0;
    $isNegative = is_numeric($delta) && (float) $delta < 0;
    $isNeutral  = ! is_numeric($delta) || (float) $delta === 0.0;

    $tone = $isPositive ? 'bg-brand-50 text-brand-700' :
            ($isNegative ? 'bg-accent-50 text-accent-600' :
            'bg-neutral-50 text-neutral-500');

    $arrow = $isPositive ? '▲' : ($isNegative ? '▼' : '·');

    $label = $isNeutral
        ? '—'
        : ($isPositive ? '+' : '').number_format((float) $delta, 1).'%';
@endphp

<span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold {{ $tone }} rounded-full whitespace-nowrap">
    <span aria-hidden="true">{{ $arrow }}</span>
    <span>{{ $label }}</span>
    @if($suffix)
        <span class="text-neutral-500 font-medium">{{ $suffix }}</span>
    @endif
</span>
