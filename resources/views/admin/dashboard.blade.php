@extends('layouts.app')

@section('title', 'Platform Control')

@section('content')
    @php
        $attentionCount = $metrics['risk']['trials_expiring_7d'] + $metrics['risk']['inactive_14d'] + $metrics['risk']['suspended_count'];
        $runtimeRows = [
            [
                'label' => 'Environment',
                'value' => strtoupper($metrics['runtime']['environment']),
                'tone' => app()->environment('production') ? 'text-emerald-700 bg-emerald-50' : 'text-amber-700 bg-amber-50',
            ],
            [
                'label' => 'Database',
                'value' => $metrics['runtime']['database'],
                'tone' => 'text-emerald-700 bg-emerald-50',
            ],
            [
                'label' => 'Cache',
                'value' => $metrics['runtime']['cache_store'],
                'tone' => 'text-emerald-700 bg-emerald-50',
            ],
            [
                'label' => 'Queue',
                'value' => $metrics['runtime']['queue_driver'],
                'tone' => 'text-emerald-700 bg-emerald-50',
            ],
            [
                'label' => 'Mailer',
                'value' => $metrics['runtime']['mailer'],
                'tone' => 'text-emerald-700 bg-emerald-50',
            ],
            [
                'label' => 'Error Tracking',
                'value' => $metrics['runtime']['sentry_configured'] ? 'Configured' : 'Not configured',
                'tone' => $metrics['runtime']['sentry_configured'] ? 'text-emerald-700 bg-emerald-50' : 'text-amber-700 bg-amber-50',
            ],
        ];
        $usageRows = [
            ['label' => 'Check-ins', 'value' => number_format($metrics['usage']['checkins_7d'])],
            ['label' => 'Work orders', 'value' => number_format($metrics['usage']['workorders_7d'])],
            ['label' => 'Invoices', 'value' => number_format($metrics['usage']['invoices_7d'])],
            ['label' => 'Tire hotel', 'value' => number_format($metrics['usage']['tirehotel_7d'])],
        ];
        $summaryCards = [
            [
                'label' => 'Total tenants',
                'value' => number_format($metrics['growth']['total_tenants']),
                'hint' => number_format($metrics['growth']['new_tenants_30d']).' added in 30 days',
                'border' => 'border-slate-300',
            ],
            [
                'label' => 'Active tenants',
                'value' => number_format($metrics['growth']['active_tenants_7d']),
                'hint' => number_format($metrics['growth']['active_tenants_24h']).' seen in last 24h',
                'border' => 'border-emerald-300',
            ],
            [
                'label' => 'Attention required',
                'value' => number_format($attentionCount),
                'hint' => number_format($metrics['risk']['suspended_count']).' suspended',
                'border' => 'border-amber-300',
            ],
            [
                'label' => 'Failed jobs',
                'value' => number_format($metrics['health']['failed_jobs_count']),
                'hint' => 'Last refresh '.$metrics['health']['refreshed_at']->format('H:i'),
                'border' => 'border-rose-300',
            ],
        ];
    @endphp

    <div class="space-y-8">
        <div class="border-b border-slate-300 pb-5">
            <h1 class="text-2xl font-semibold text-slate-900">Platform Overview</h1>
            <p class="mt-1 text-sm text-slate-600">
                Operational summary for tenant growth, platform health, and recent admin activity.
            </p>
        </div>

        <section class="grid gap-5 lg:grid-cols-4">
            @foreach ($summaryCards as $card)
                <div class="rounded-md border border-slate-300 border-t-4 {{ $card['border'] }} bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-3 text-2xl font-semibold text-slate-900">{{ $card['value'] }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $card['hint'] }}</p>
                </div>
            @endforeach
        </section>

        <section class="rounded-md border border-slate-300 bg-white shadow-sm">
            <div class="border-b border-slate-300 bg-slate-50 px-4 py-3">
                <h2 class="text-base font-semibold text-slate-900">Attention queues</h2>
            </div>
            <div class="grid gap-6 p-5 lg:grid-cols-3">
                <div>
                    <div class="mb-3 flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                        <h3 class="text-sm font-medium text-slate-900">Expiring soon</h3>
                    </div>
                    <div class="space-y-2">
                        @forelse ($metrics['attention']['expiring'] as $tenant)
                            <a href="{{ route('admin.tenants.show', $tenant) }}"
                                class="block rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                <div class="font-medium text-slate-900">{{ $tenant->name }}</div>
                                <div class="text-xs text-slate-500">
                                    Ends {{ optional($tenant->trial_ends_at ?? $tenant->subscription_ends_at)?->format('M d, Y') ?? 'n/a' }}
                                </div>
                            </a>
                        @empty
                            <div class="rounded-md border border-dashed border-slate-300 px-4 py-5 text-sm text-slate-500">
                                No tenants expiring in 7 days.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>
                        <h3 class="text-sm font-medium text-slate-900">Suspended</h3>
                    </div>
                    <div class="space-y-2">
                        @forelse ($metrics['attention']['suspended'] as $tenant)
                            <a href="{{ route('admin.tenants.show', $tenant) }}"
                                class="block rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                <div class="font-medium text-slate-900">{{ $tenant->name }}</div>
                                <div class="text-xs text-slate-500">Updated {{ $tenant->updated_at?->diffForHumans() ?? 'n/a' }}</div>
                            </a>
                        @empty
                            <div class="rounded-md border border-dashed border-slate-300 px-4 py-5 text-sm text-slate-500">
                                No suspended tenants.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div>
                    <div class="mb-3 flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-orange-500"></span>
                        <h3 class="text-sm font-medium text-slate-900">Inactive 14+ days</h3>
                    </div>
                    <div class="space-y-2">
                        @forelse ($metrics['attention']['inactive'] as $tenant)
                            <a href="{{ route('admin.tenants.show', $tenant) }}"
                                class="block rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                <div class="font-medium text-slate-900">{{ $tenant->name }}</div>
                                <div class="text-xs text-slate-500">Last seen {{ optional($tenant->last_seen_at)?->diffForHumans() ?? 'never' }}</div>
                            </a>
                        @empty
                            <div class="rounded-md border border-dashed border-slate-300 px-4 py-5 text-sm text-slate-500">
                                No inactive tenants in queue.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-md border border-slate-300 bg-white shadow-sm">
            <div class="border-b border-slate-300 bg-slate-50 px-4 py-3">
                <h2 class="text-base font-semibold text-slate-900">Recent admin actions</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-300">
                    <thead class="bg-slate-100">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">Action</th>
                            <th class="px-4 py-3">Reason</th>
                            <th class="px-4 py-3">By</th>
                            <th class="px-4 py-3">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-300">
                        @forelse ($metrics['recent_actions'] as $log)
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ strtoupper(str_replace('_', ' ', $log->action)) }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ $log->changes['reason'] ?? 'No reason recorded.' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ $log->user->name ?? 'System' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-500">{{ $log->created_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No admin actions recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-5 lg:grid-cols-3">
            <div class="rounded-md border border-slate-300 bg-white shadow-sm">
                <div class="border-b border-slate-300 bg-slate-50 px-4 py-3">
                    <h2 class="text-base font-semibold text-slate-900">Runtime</h2>
                </div>
                <div class="divide-y divide-slate-300">
                    @foreach ($runtimeRows as $row)
                        <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                            <span class="text-slate-600">{{ $row['label'] }}</span>
                            <span class="rounded px-2 py-1 text-xs font-medium {{ $row['tone'] }}">
                                {{ $row['value'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-md border border-slate-300 bg-white shadow-sm">
                <div class="border-b border-slate-300 bg-slate-50 px-4 py-3">
                    <h2 class="text-base font-semibold text-slate-900">Usage in 7 days</h2>
                </div>
                <div class="divide-y divide-slate-300">
                    @foreach ($usageRows as $row)
                        <div class="flex items-center justify-between px-4 py-3 text-sm">
                            <span class="text-slate-600">{{ $row['label'] }}</span>
                            <span class="font-medium text-slate-900">{{ $row['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-md border border-slate-300 bg-white shadow-sm">
                <div class="border-b border-slate-300 bg-slate-50 px-4 py-3">
                    <h2 class="text-base font-semibold text-slate-900">Plan mix</h2>
                </div>
                <div class="divide-y divide-slate-300">
                    @foreach ($metrics['plan_mix'] as $plan)
                        <div class="flex items-center justify-between px-4 py-3 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full {{ $loop->first ? 'bg-emerald-500' : ($loop->last ? 'bg-rose-500' : 'bg-amber-500') }}"></span>
                                <span class="text-slate-600">{{ $plan['label'] }}</span>
                            </div>
                            <span class="font-medium text-slate-900">{{ number_format($plan['count']) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
@endsection
