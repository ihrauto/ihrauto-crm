@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'placeholder' => null,
    'value' => null,
    'required' => false,
    'disabled' => false,
    'error' => null,
    'id' => null,
])

@php
    $id = $id ?? $name ?? 'input-' . str()->random(8);
    $inputClasses = 'w-full p-3 border border-[#809AED] rounded-lg focus:ring-2 focus:ring-[#1A53F2] focus:border-[#1A53F2] transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed';
    
    if ($error) {
        $inputClasses .= ' border-red-500 focus:ring-red-500 focus:border-red-500';
    }
@endphp

<div class="mb-4">
    @if($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-black mb-1">
            {{ $label }}
            @if($required)
                <span class="text-red-500 ml-1">*</span>
            @endif
        </label>
    @endif
    
    <input 
        type="{{ $type }}" 
        id="{{ $id }}" 
        name="{{ $name }}" 
        value="{{ is_array(old($name, $value)) ? '' : old($name, $value) }}"
        placeholder="{{ $placeholder }}"
        {{ $required ? 'required' : '' }}
        {{ $disabled ? 'disabled' : '' }}
        {{ $attributes->merge(['class' => $inputClasses]) }}
    >
    
    @if($error)
        <p class="text-red-600 text-xs mt-1">{{ $error }}</p>
    @elseif($errors->has($name))
        <p class="text-red-600 text-xs mt-1">{{ $errors->first($name) }}</p>
    @endif
</div> 