{{--
    Inline ApexCharts sparkline for the KPI cards.

    Renders a 48px-tall area chart with no axes, no grid, no legend —
    just a smooth line over a faint area fill. Hover shows a tiny
    tooltip with the value at that day.

    The chart is initialised by an x-init that reads the JSON-encoded
    series from `data-series` on the wrapper div and feeds it to
    ApexCharts.

    @props:
        series   array<int, float|int>   the series data (one value per day)
        color    string                  'brand' | 'accent' | 'neutral' (defaults brand)
        height   int                     pixel height (default 48)
--}}
@props([
    'series' => [],
    'color' => 'brand',
    'height' => 48,
])

<div
    x-data
    x-init="(() => {
        if (typeof window.ApexCharts === 'undefined') return;
        const el = $el;
        const series = JSON.parse(el.dataset.series || '[]');
        const colorKey = el.dataset.color || 'brand';
        const theme = window.dashboardChartTheme;
        const palette = theme[colorKey] || theme.brand;
        const stroke = palette[500];

        // Empty / all-zero series — show a flat baseline so the
        // card doesn't render a 'no data' empty state ugly.
        const hasData = series.some((v) => v > 0);
        if (!hasData) {
            el.innerHTML = '<div class=&quot;text-xs text-neutral-300 italic h-full flex items-end&quot;>no recent activity</div>';
            return;
        }

        new window.ApexCharts(el, {
            chart: {
                type: 'area',
                height: parseInt(el.dataset.height || '48', 10),
                sparkline: { enabled: true },
                animations: { enabled: false },
                fontFamily: theme.fontFamily,
            },
            stroke: { curve: 'smooth', width: 2 },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.0,
                    stops: [0, 95],
                },
            },
            colors: [stroke],
            series: [{ data: series }],
            tooltip: {
                fixed: { enabled: false },
                x: { show: false },
                y: {
                    title: { formatter: () => '' },
                    formatter: (v) => Number(v).toLocaleString('de-CH', {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 2,
                    }),
                },
                marker: { show: false },
            },
            grid: { padding: { top: 0, right: 0, bottom: 0, left: 0 } },
        }).render();
    })()"
    data-series="{{ json_encode($series) }}"
    data-color="{{ $color }}"
    data-height="{{ $height }}"
    style="min-height: {{ $height }}px;"
    aria-hidden="true"></div>
