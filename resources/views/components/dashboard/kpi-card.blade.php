{{--
    Dashboard KPI card.

    The shared chrome for all small "headline number" widgets in the
    new dashboard (revenue / active jobs / outstanding / new
    customers, etc.). Layout per the 2026-04-26 redesign brief:

        ┌──────────────────────────────────┐
        │ TITLE                  +12.4% ↗  │  ← row 1: title + delta pill
        │                                  │
        │ CHF 12,345                       │  ← row 2: BIG number
        │ subtitle in muted gray           │  ← row 3: optional
        │                                  │
        │ ╱╲    ╱╲╱╲                       │  ← row 4: sparkline (full width)
        └──────────────────────────────────┘

    Surface stays bg-white, no border, no shadow (per the theme
    pass). 10px corners come from the global borderRadius override.
    Hovering tints the card brand-50 so users see it's clickable.

    @props:
        title       string                 small caps label (e.g. "Revenue this month")
        value       string                 the big number to render (already formatted)
        unit        string|null            optional unit/suffix shown small after value (e.g. "/ 6 bays")
        subtitle    string|null            optional muted line under the value
        delta       float|null             % change vs previous period; nullable
        deltaSuffix string|null            optional context for delta pill (e.g. "vs last 30d")
        href        string|null            click-target; if given the whole card becomes a link
        icon        string|null            inline SVG markup string (entire <svg>...</svg>); placed top-right
        accent      string                 'brand' | 'accent' | 'neutral'  → tints the icon container
        sparkSlot   slot                   pass an inline ApexCharts container via the `spark` slot

    Usage:
        <x-dashboard.kpi-card
            title="Revenue this month"
            :value="'CHF '.number_format($revenue, 0, '.', \"'\")"
            :delta="$growth"
            href="{{ route('finance.index') }}"
            accent="brand">
            <x-slot name="spark">
                <div class="kpi-spark" data-series='@json($sparkData)' data-color="brand"></div>
            </x-slot>
        </x-dashboard.kpi-card>
--}}
@props([
    'title',
    'value',
    'unit' => null,
    'subtitle' => null,
    'delta' => null,
    'deltaSuffix' => null,
    'href' => null,
    'accent' => 'brand', // brand | accent | neutral — currently unused, reserved for icon slot once we add per-card glyphs
])

@php
    // Resolve themed accent classes for any future icon slot. Currently
    // not rendered — KPI cards are number-first, no glyph chrome — but
    // the prop stays declared so adding `<x-slot:icon>` later doesn't
    // require a component-API change.
    $accentBg = match ($accent) {
        'accent'  => 'bg-accent-100 text-accent-600',
        'neutral' => 'bg-neutral-100 text-neutral-700',
        default   => 'bg-brand-100 text-brand-700',
    };

    $hoverClasses = $href
        ? 'hover:bg-brand-50 cursor-pointer transition-colors duration-200'
        : '';

    $cardClasses = "block bg-white rounded-xl p-5 {$hoverClasses} h-full flex flex-col justify-between min-h-[152px]";
@endphp

{{-- Render either an <a> (clickable) or a <div> (static). The contents
     are identical so we use a single nested @if to gate the wrapper —
     Blade's component compiler can't handle a stray @endif that
     straddles a wrapper tag. --}}
{{-- Two near-identical branches because Blade's component compiler
     mishandles a wrapper-tag conditional that straddles the slot
     content. A clean if/else-with-full-body avoids the issue. --}}
@if($href)
<a href="{{ $href }}" class="{{ $cardClasses }}">
    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ $title }}</p>

    <div class="mt-2">
        <div class="flex items-baseline gap-2 flex-wrap">
            <span class="text-3xl font-bold text-neutral-900 leading-none tracking-tight">{{ $value }}</span>
            @if($unit)
                <span class="text-sm font-medium text-neutral-500">{{ $unit }}</span>
            @endif
        </div>
        @if($subtitle)
            <p class="mt-1 text-xs text-neutral-500">{{ $subtitle }}</p>
        @endif
    </div>

    @if($delta !== null || $deltaSuffix)
        <div class="mt-3">
            <x-dashboard.delta-pill :delta="$delta" :suffix="$deltaSuffix" />
        </div>
    @endif

    @isset($spark)
        <div class="mt-3 -mx-1">{{ $spark }}</div>
    @endisset
</a>
@else
<div class="{{ $cardClasses }}">
    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ $title }}</p>

    <div class="mt-2">
        <div class="flex items-baseline gap-2 flex-wrap">
            <span class="text-3xl font-bold text-neutral-900 leading-none tracking-tight">{{ $value }}</span>
            @if($unit)
                <span class="text-sm font-medium text-neutral-500">{{ $unit }}</span>
            @endif
        </div>
        @if($subtitle)
            <p class="mt-1 text-xs text-neutral-500">{{ $subtitle }}</p>
        @endif
    </div>

    @if($delta !== null || $deltaSuffix)
        <div class="mt-3">
            <x-dashboard.delta-pill :delta="$delta" :suffix="$deltaSuffix" />
        </div>
    @endif

    @isset($spark)
        <div class="mt-3 -mx-1">{{ $spark }}</div>
    @endisset
</div>
@endif
