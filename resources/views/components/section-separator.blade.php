{{-- Section separator with centered label --}}
@props(['label'])

<div class="py-8">
    <div class="flex items-center justify-center">
        <div class="flex-grow border-t border-indigo-100"></div>
        <div class="mx-6">
            <h2 class="text-xs font-bold text-indigo-300 uppercase tracking-widest">{{ $label }}</h2>
        </div>
        <div class="flex-grow border-t border-indigo-100"></div>
    </div>
</div>
