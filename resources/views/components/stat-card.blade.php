{{-- Stat card component for dashboard-style metrics --}}
@props([
    'label',
    'value',
    'borderColor' => 'border-indigo-500',
])

<x-card class="border-l-4 {{ $borderColor }} shadow-sm ring-1 ring-indigo-50">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-medium text-indigo-500">{{ $label }}</p>
            <p class="text-2xl font-bold text-indigo-900">{{ $value }}</p>
        </div>
        @if(isset($icon))
            {{ $icon }}
        @endif
    </div>
</x-card>
