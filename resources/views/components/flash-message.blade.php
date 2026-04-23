{{-- Flash message component: success and error messages --}}
@props(['type' => 'success'])

@php
    $styles = [
        'success' => [
            'bg' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
            'icon' => 'text-emerald-500',
            'path' => 'M5 13l4 4L19 7',
        ],
        'error' => [
            'bg' => 'bg-red-50 border-red-200 text-red-800',
            'icon' => 'text-red-500',
            'path' => 'M6 18L18 6M6 6l12 12',
        ],
        'info' => [
            'bg' => 'bg-blue-50 border-blue-200 text-blue-800',
            'icon' => 'text-blue-500',
            'path' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
    ];
    $style = $styles[$type] ?? $styles['success'];
@endphp

@if(session($type))
    <div class="{{ $style['bg'] }} border px-4 py-3 rounded-lg flex items-center shadow-sm">
        <svg class="w-5 h-5 mr-3 {{ $style['icon'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $style['path'] }}"></path>
        </svg>
        {{ session($type) }}
    </div>
@endif
