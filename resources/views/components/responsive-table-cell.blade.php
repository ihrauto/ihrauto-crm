@props(['label' => ''])

<td
    data-label="{{ $label }}"
    {{ $attributes->merge(['class' => 'px-4 py-3 text-sm text-gray-700']) }}
>
    {{ $slot }}
</td>
