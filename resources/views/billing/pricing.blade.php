@extends('layouts.app')

@section('title', 'Plans & Billing')

@section('content')
    @php
        $supportEmail = config('crm.support.email');
        $supportPhone = config('crm.support.phone');
        $tenantStatusLabel = $tenant->is_trial ? 'Trial' : 'Subscription';
        $renewalLabel = $tenant->is_trial ? 'Trial ends' : 'Next renewal';
    @endphp

    <div class="mx-auto max-w-7xl space-y-8">
        <section class="overflow-hidden rounded-3xl bg-gradient-to-r from-indigo-950 via-indigo-900 to-slate-900 px-8 py-10 text-white shadow-2xl shadow-indigo-950/20">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-bold uppercase tracking-[0.28em] text-indigo-200">Manual Billing</p>
                    <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">
                        Keep {{ $tenant->name }} active with a production billing path.
                    </h1>
                    <p class="mt-4 max-w-2xl text-sm leading-7 text-indigo-100/80 sm:text-base">
                        IHRAUTO CRM is running a manual-billing beta. Choose the plan that fits your workshop, then contact the billing team to activate or update the subscription.
                    </p>
                </div>

                <div class="grid gap-3 rounded-3xl border border-white/10 bg-white/10 p-5 text-sm shadow-lg backdrop-blur sm:min-w-[320px]">
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-indigo-100/70">{{ $tenantStatusLabel }}</span>
                        <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-white">
                            {{ $currentPlan['name'] }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-indigo-100/70">{{ $renewalLabel }}</span>
                        <span class="font-semibold text-white">
                            {{ $renewalDate ? $renewalDate->format('M d, Y') : 'Not scheduled yet' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-indigo-100/70">Status</span>
                        <span class="font-semibold text-white">
                            {{ $tenant->is_expired ? 'Action required' : ($tenant->is_trial ? 'Trial active' : 'Subscription active') }}
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-3">
            @foreach ($planCatalog as $planKey => $plan)
                @php
                    $isCurrent = $currentPlanKey === $planKey;
                    $emailSubject = rawurlencode("IHRAUTO billing request for {$tenant->name} ({$plan['name']})");
                    $emailBody = rawurlencode("Tenant: {$tenant->name}\nCurrent plan: {$currentPlan['name']}\nRequested plan: {$plan['name']}\nTenant ID: {$tenant->id}\n\nPlease contact us with the next billing steps.");
                @endphp

                <article class="rounded-3xl border {{ $isCurrent ? 'border-indigo-500 bg-indigo-950 text-white shadow-2xl shadow-indigo-950/10' : 'border-indigo-100 bg-white text-gray-900 shadow-lg shadow-indigo-100/50' }}">
                    <div class="p-8">
                        <div class="flex items-center justify-between gap-4">
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] {{ $isCurrent ? 'border border-white/15 bg-white/10 text-indigo-100' : 'border border-indigo-100 bg-indigo-50 text-indigo-700' }}">
                                {{ $isCurrent ? 'Current plan' : 'Available plan' }}
                            </span>
                            @if ($planKey === \App\Models\Tenant::PLAN_STANDARD)
                                <span class="rounded-full bg-emerald-500 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-white">
                                    Recommended
                                </span>
                            @endif
                        </div>

                        <h2 class="mt-6 text-3xl font-bold">{{ $plan['name'] }}</h2>

                        <div class="mt-4 flex items-end gap-2">
                            <span class="text-5xl font-extrabold">{{ $plan['price_label'] }}</span>
                            <span class="pb-1 text-base font-semibold {{ $isCurrent ? 'text-indigo-200' : 'text-indigo-500' }}">
                                {{ $plan['billing_label'] }}
                            </span>
                        </div>

                        <p class="mt-5 min-h-[84px] text-sm leading-7 {{ $isCurrent ? 'text-indigo-100/80' : 'text-gray-500' }}">
                            {{ $plan['description'] }}
                        </p>

                        <div class="mt-6 grid gap-2 text-left">
                            @foreach ($plan['highlights'] as $highlight)
                                <div class="rounded-2xl px-4 py-3 text-sm font-semibold {{ $isCurrent ? 'bg-white/10 text-white' : 'bg-indigo-50 text-indigo-900' }}">
                                    {{ $highlight }}
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-8 flex flex-col gap-3">
                            <a href="mailto:{{ $supportEmail }}?subject={{ $emailSubject }}&body={{ $emailBody }}"
                                class="inline-flex w-full items-center justify-center rounded-xl px-6 py-3.5 text-sm font-bold uppercase tracking-[0.14em] transition {{ $isCurrent ? 'bg-white text-indigo-950 hover:bg-indigo-50' : 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-md shadow-indigo-200' }}">
                                {{ $isCurrent ? 'Email Billing Team' : 'Request This Plan' }}
                            </a>

                            @if ($supportPhone)
                                <a href="tel:{{ preg_replace('/\s+/', '', $supportPhone) }}"
                                    class="inline-flex w-full items-center justify-center rounded-xl border px-6 py-3 text-sm font-bold uppercase tracking-[0.14em] transition {{ $isCurrent ? 'border-white/15 text-white hover:bg-white/10' : 'border-indigo-200 text-indigo-700 hover:bg-indigo-50' }}">
                                    Call {{ $supportPhone }}
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="grid gap-6 lg:grid-cols-[1.4fr,0.9fr]">
            <div class="rounded-3xl border border-indigo-100 bg-white p-8 shadow-lg shadow-indigo-100/40">
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-indigo-500">How activation works</p>
                <ol class="mt-5 space-y-4 text-sm text-gray-600">
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-sm font-bold text-indigo-700">1</span>
                        <div>
                            <p class="font-semibold text-gray-900">Choose the plan that matches your workshop.</p>
                            <p class="mt-1 leading-6">Use the plan card above to email the billing team with the requested package.</p>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-sm font-bold text-indigo-700">2</span>
                        <div>
                            <p class="font-semibold text-gray-900">We confirm pricing, rollout scope, and billing details.</p>
                            <p class="mt-1 leading-6">This beta uses manual activation instead of a public checkout flow.</p>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-sm font-bold text-indigo-700">3</span>
                        <div>
                            <p class="font-semibold text-gray-900">Your tenant is activated or updated by the platform team.</p>
                            <p class="mt-1 leading-6">Renewal dates, limits, and features are applied directly to your tenant account.</p>
                        </div>
                    </li>
                </ol>
            </div>

            <aside class="rounded-3xl border border-amber-200 bg-amber-50 p-8 shadow-lg shadow-amber-100/40">
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-amber-600">Support</p>
                <h2 class="mt-3 text-2xl font-bold text-gray-900">Need help fast?</h2>
                <p class="mt-4 text-sm leading-7 text-gray-600">
                    Billing questions, trial expiry, and plan changes are handled by the same support path during the beta.
                </p>

                <div class="mt-6 space-y-3 text-sm">
                    <a href="mailto:{{ $supportEmail }}" class="flex items-center justify-between rounded-2xl border border-amber-200 bg-white px-4 py-3 font-semibold text-gray-900">
                        <span>Email</span>
                        <span class="text-amber-600">{{ $supportEmail }}</span>
                    </a>

                    @if ($supportPhone)
                        <a href="tel:{{ preg_replace('/\s+/', '', $supportPhone) }}" class="flex items-center justify-between rounded-2xl border border-amber-200 bg-white px-4 py-3 font-semibold text-gray-900">
                            <span>Phone</span>
                            <span class="text-amber-600">{{ $supportPhone }}</span>
                        </a>
                    @endif
                </div>

                @if (! $tenant->is_expired)
                    <a href="{{ route('dashboard') }}"
                        class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-gray-900 px-5 py-3 text-sm font-bold uppercase tracking-[0.14em] text-white transition hover:bg-gray-800">
                        Return to Dashboard
                    </a>
                @endif
            </aside>
        </section>
    </div>
@endsection
