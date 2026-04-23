{{--
  Secondary button — outlined variant for less-important actions.

  Use for: Cancel, Back, Edit (when next to a primary Save), tertiary CTAs.
  Do NOT use for: form submission of destructive or primary actions.

  Usage:
    <x-secondary-button type="button" @click="open = false">
        {{ __('crm.common.cancel') }}
    </x-secondary-button>
--}}
<button {{ $attributes->merge([
    'type' => 'button',
    'class' => 'inline-flex items-center px-4 py-2 bg-white border border-[#809AED] rounded-lg font-semibold text-xs text-indigo-900 uppercase tracking-widest shadow-sm hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-[#6A88E8] focus:ring-offset-2 focus-visible:ring-2 disabled:opacity-25 transition ease-in-out duration-150 min-h-[40px]',
]) }}>
    {{ $slot }}
</button>
