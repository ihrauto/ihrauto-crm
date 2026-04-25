{{-- ENG-009: dashboard widget grid.

    Used both inside dashboard.blade.php (full page render) and by the
    DashboardController::widgetsFragment endpoint, which the Studio panel
    calls after a save to swap the grid in place.

    Layout is "elastic": auto-fit grid columns. When a widget is removed,
    the remaining ones expand to fill the row instead of leaving an empty
    cell.

    Drag-reorder: each widget is wrapped in a `data-widget-key` element.
    SortableJS is initialized once on the dashboard page (see scripts at
    bottom). On drop, the new key order is POSTed; the change persists
    via `users.dashboard_widgets.order`.
--}}
@php
    $smallWidgets = collect($enabledWidgets ?? [])->where('size', 'small')->values();
    $halfWidgets = collect($enabledWidgets ?? [])->where('size', 'half')->values();
    $fullWidgets = collect($enabledWidgets ?? [])->where('size', 'full')->values();
@endphp

@if (($enabledWidgets ?? []) === [])
    <div class="rounded-xl border-2 border-dashed border-indigo-200 bg-white p-10 text-center">
        <svg class="mx-auto h-12 w-12 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M12 17.25h8.25" />
        </svg>
        <h3 class="mt-3 text-base font-semibold text-indigo-950">Your dashboard is empty</h3>
        <p class="mt-1 text-sm text-indigo-700/70">
            Open <span class="font-semibold">Customize</span> in the top header to pick which widgets you want to see.
        </p>
    </div>
@else
    <div class="space-y-6 lg:space-y-8">
        @if ($smallWidgets->isNotEmpty())
            {{-- Flex-wrap with flex:1 — last-row items stretch to fill the
                empty space (so 2 widgets in the second row become wider
                than 4 widgets in the first row, instead of leaving blank
                grid cells). When the user adds a new widget, the
                others shrink back automatically. --}}
            <div
                class="dashboard-sortable flex flex-wrap gap-3 sm:gap-5"
                data-sortable-group="small"
            >
                @foreach ($smallWidgets as $widget)
                    <div class="dashboard-widget cursor-grab active:cursor-grabbing flex-1 basis-[200px] min-w-[200px]" data-widget-key="{{ $widget['key'] }}">
                        @include($widget['partial'])
                    </div>
                @endforeach
            </div>
        @endif

        @if ($halfWidgets->isNotEmpty())
            <div
                class="dashboard-sortable flex flex-wrap gap-4"
                data-sortable-group="half"
            >
                @foreach ($halfWidgets as $widget)
                    <div class="dashboard-widget cursor-grab active:cursor-grabbing flex-1 basis-[280px] min-w-[280px]" data-widget-key="{{ $widget['key'] }}">
                        @include($widget['partial'])
                    </div>
                @endforeach
            </div>
        @endif

        @if ($fullWidgets->isNotEmpty())
            <div class="dashboard-sortable space-y-6 lg:space-y-8" data-sortable-group="full">
                @foreach ($fullWidgets as $widget)
                    <div class="dashboard-widget cursor-grab active:cursor-grabbing" data-widget-key="{{ $widget['key'] }}">
                        @include($widget['partial'])
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
