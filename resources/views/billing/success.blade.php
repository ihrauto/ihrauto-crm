@extends('layouts.app')

@section('title', 'Subscription Active')

@section('content')
    <div class="max-w-xl mx-auto py-12">
        <div class="bg-white rounded-2xl shadow-sm border border-emerald-200 p-8 text-center">
            <div class="mx-auto h-12 w-12 rounded-full bg-emerald-100 flex items-center justify-center">
                <svg class="h-7 w-7 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h2 class="mt-4 text-2xl font-bold text-emerald-950">Thanks — your subscription is being set up</h2>
            <p class="mt-2 text-sm text-emerald-700/80">
                We're confirming the payment with Stripe. Your account will be activated within a few seconds.
                You'll receive a receipt by email from Stripe shortly.
            </p>
            <div class="mt-6 flex items-center justify-center gap-3">
                <a href="{{ route('dashboard') }}"
                    class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Open dashboard
                </a>
                <a href="{{ route('billing.pricing') }}"
                    class="inline-flex items-center rounded-lg border border-emerald-200 px-4 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50">
                    View plan
                </a>
            </div>
        </div>
    </div>
@endsection
