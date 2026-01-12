@props([
    'title' => null,
    'subtitle' => null,
    'padding' => 'p-6',
    'border' => 'border border-[#809AED]',
    'rounded' => 'rounded-xl',
    'background' => 'bg-white',
])

@php
    $classes = trim("{$background} {$rounded} {$border} {$padding}");
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    @if($title || $subtitle)
        <div class="mb-4">
            @if($title)
                <h3 class="text-lg font-normal text-[#1A53F2] mb-1">{{ $title }}</h3>
            @endif
            @if($subtitle)
                <p class="text-sm text-black">{{ $subtitle }}</p>
            @endif
        </div>
    @endif
    
    <div class="text-base text-black leading-relaxed">
        {{ $slot }}
    </div>
</div> 