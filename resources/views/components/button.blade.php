@props([
    'variant' => 'primary', // primary, secondary, inverted-blue, inverted-green
    'type' => 'button',
    'size' => 'md', // sm, md, lg
    'disabled' => false,
    'href' => null,
    'target' => null,
])

@php
    $baseClasses = 'inline-flex items-center justify-center font-semibold uppercase transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-[#6A88E8] focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer';
    
    $variantClasses = match($variant) {
        'primary' => 'bg-[#1A53F2] text-white hover:bg-[#5274E3]',
        'secondary' => 'bg-gray-100 text-gray-700 hover:bg-gray-200 focus:ring-gray-300',
        'inverted-blue' => 'bg-[#1A53F2] text-[#F1FF30] hover:bg-[#5274E3]',
        'inverted-green' => 'bg-[#F1FF30] text-[#1A53F2] hover:bg-[#F1FF30]/90',
        default => 'bg-[#1A53F2] text-white hover:bg-[#5274E3]',
    };
    
    $sizeClasses = match($size) {
        'sm' => 'px-3 py-1.5 text-sm rounded-md',
        'md' => 'px-5 py-1.25 text-sm rounded-lg',
        'lg' => 'px-6 py-3 text-base rounded-lg',
        default => 'px-5 py-1.25 text-sm rounded-lg',
    };
    
    $classes = trim("{$baseClasses} {$variantClasses} {$sizeClasses}");
@endphp

@if($href)
    <a href="{{ $href }}" {{ $target ? "target={$target}" : '' }} {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $disabled ? 'disabled' : '' }} {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif 