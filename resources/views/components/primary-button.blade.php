{{--
  Primary submit button with built-in loading state.

  On submit, Alpine disables the button and shows a spinner so users can't
  double-submit forms. Works without any per-form JavaScript: each button
  owns its own state.

  Usage:
    <x-primary-button>{{ __('Save') }}</x-primary-button>

  Opt out by passing :loading="false":
    <x-primary-button :loading="false">Custom</x-primary-button>
--}}
@props(['loading' => true])

<button
    @if($loading)
        x-data="{ submitting: false }"
        x-on:click="if ($el.form && $el.form.checkValidity()) { submitting = true }"
        :disabled="submitting"
        :aria-busy="submitting ? 'true' : 'false'"
    @endif
    {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-[#1A53F2] border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-[#5274E3] focus:bg-[#5274E3] active:bg-[#1A53F2] focus:outline-none focus:ring-2 focus:ring-[#6A88E8] focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-60 disabled:cursor-not-allowed']) }}
>
    @if($loading)
        <svg x-show="submitting" x-cloak class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
    @endif
    {{ $slot }}
</button>
