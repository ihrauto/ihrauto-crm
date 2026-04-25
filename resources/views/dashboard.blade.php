@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <style>
        .hover-purple-custom:hover {
            background-color: #F3E8FF !important;
            /* purple-100 */
        }

        .group:hover .hover-icon-purple {
            background-color: #D8B4FE !important;
            /* purple-300 */
        }

        .schedule-scroll {
            max-height: 420px;
            overflow-y: auto;
        }

        /* ENG-009 drag-reorder visual feedback. */
        .dashboard-widget-ghost {
            opacity: 0.4;
        }
        .dashboard-widget-chosen > * {
            outline: 2px dashed rgb(99 102 241);
            outline-offset: 4px;
            border-radius: 0.75rem;
        }
        .dashboard-widget-drag {
            opacity: 0.95;
        }
    </style>

    <div class="space-y-6 lg:space-y-8">
        <!-- Welcome Section (always shown — anchor for the page) -->
        <div class="md:flex md:items-center md:justify-between">
            <div class="min-w-0 flex-1">
                <h2 class="text-2xl font-bold leading-7 text-indigo-950 sm:truncate sm:text-3xl sm:tracking-tight">Welcome
                    back, {{ auth()->user()->name ?? 'User' }}</h2>
                <p class="mt-1 text-sm text-indigo-700/70">Here's an overview of your operations today.</p>
            </div>
            <div class="mt-4 flex md:ml-4 md:mt-0">
                <span
                    class="inline-flex items-center rounded-lg bg-white px-3 py-2 text-sm font-semibold text-indigo-900 shadow-sm border border-indigo-200 hover:bg-indigo-50">
                    <svg class="-ml-0.5 mr-1.5 h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0h18" />
                    </svg>
                    {{ date('M j, Y') }}
                </span>
            </div>
        </div>

        {{-- Widget grid container. Targeted by the Studio panel via this id
             after every toggle, so the panel can swap content in place
             without a full page reload (keeps the panel open). The
             data-reorder-url is consumed by the SortableJS bootstrap in
             resources/js/app.js to persist drag-reorder. --}}
        <div
            id="dashboard-widgets-root"
            data-reorder-url="{{ route('dashboard.studio.reorder') }}"
        >
            @include('dashboard._widgets', ['enabledWidgets' => $enabledWidgets])
        </div>
    </div>

    <!-- Interactive Tour -->
    <x-dashboard-tour />
@endsection
