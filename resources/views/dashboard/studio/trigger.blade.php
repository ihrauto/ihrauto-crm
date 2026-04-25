{{-- ENG-009: Dashboard Studio header trigger.

    Includes the panel as a child so all state (open, search, working) is
    contained in this Alpine component. The trigger is rendered only on
    /dashboard via a route check in layouts/app.blade.php — keeps every
    other page free of clutter.
--}}
@php
    $studioService = app(\App\Services\DashboardStudioService::class);
    $studioUser = auth()->user();
    $studioConfig = [
        'categories' => \App\Support\DashboardWidgetCatalog::categories(),
        'widgets' => $studioService->widgetsForUser($studioUser),
        'enabled' => $studioService->enabledKeysForUser($studioUser),
        'storeUrl' => route('dashboard.studio.store'),
        'resetUrl' => route('dashboard.studio.reset'),
        'fragmentUrl' => route('dashboard.widgets-fragment'),
        'csrf' => csrf_token(),
    ];
    $studioConfigJson = json_encode($studioConfig, JSON_THROW_ON_ERROR | JSON_HEX_APOS | JSON_HEX_QUOT);
@endphp

<div
    class="relative"
    x-data="dashboardStudio({{ $studioConfigJson }})"
    @keydown.escape.window="open && close()"
>
    <button
        type="button"
        @click="togglePanel()"
        :aria-expanded="open ? 'true' : 'false'"
        aria-haspopup="dialog"
        aria-label="Customize dashboard widgets"
        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold text-indigo-700 hover:text-indigo-900 hover:bg-indigo-50 transition-colors border border-indigo-100"
    >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a6.759 6.759 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <span class="hidden sm:inline">Customize</span>
        <svg class="w-4 h-4 text-indigo-400 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    @include('dashboard.studio.panel')
</div>
