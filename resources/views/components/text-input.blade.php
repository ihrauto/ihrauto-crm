@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-brand-subtle-border focus:border-brand-primary focus:ring-brand-primary rounded-lg shadow-sm']) }}>
