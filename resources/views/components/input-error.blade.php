{{--
  Input error list component.

  Accessibility:
    - role="alert" and aria-live="polite" announce errors to screen readers
      immediately when they appear, without interrupting the user.
    - The wrapper accepts an explicit id so the paired input can reference it
      via aria-describedby. Default id is generated from the $field prop so
      callers don't have to invent one.

  Usage:
    <x-input-label for="email" :value="__('Email')" />
    <x-text-input id="email" name="email"
                  :aria-describedby="$errors->has('email') ? 'email-error' : null"
                  :aria-invalid="$errors->has('email') ? 'true' : 'false'" />
    <x-input-error :messages="$errors->get('email')" field="email" />
--}}
@props(['messages', 'field' => null])

@php
    $errorId = $field ? "{$field}-error" : null;
@endphp

@if ($messages)
    <ul
        @if($errorId) id="{{ $errorId }}" @endif
        role="alert"
        aria-live="polite"
        {{ $attributes->merge(['class' => 'text-sm text-red-600 space-y-1 mt-1']) }}
    >
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
