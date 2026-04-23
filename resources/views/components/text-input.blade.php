@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-[#809AED] focus:border-[#1A53F2] focus:ring-[#1A53F2] rounded-lg shadow-sm']) }}>
