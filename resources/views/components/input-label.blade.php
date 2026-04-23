{{--
  Form label with optional required marker.

  Pass `required` prop (or rely on `aria-required` on the paired input) to
  render a red asterisk and a screen-reader-friendly "required" suffix.

  Usage:
    <x-input-label for="email" :value="__('crm.customer.email')" :required="true" />

  Why an explicit prop: we can't auto-detect whether the paired input is
  required from within the label — HTML associations are one-directional.
--}}
@props(['value' => null, 'required' => false])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-indigo-900']) }}>
    {{ $value ?? $slot }}
    @if($required)
        <span class="text-red-600 ml-0.5" aria-hidden="true">*</span>
        <span class="sr-only">({{ __('crm.common.required') }})</span>
    @endif
</label>
