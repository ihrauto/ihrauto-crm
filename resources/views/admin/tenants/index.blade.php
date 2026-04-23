@extends('layouts.app')

@section('title', 'Tenant Management')

@section('content')
    @php
        $planCatalog = \App\Models\Tenant::planCatalog();
    @endphp

    <div class="space-y-8">
        <div class="flex flex-col gap-2 border-b border-slate-300 pb-5">
            <h1 class="text-2xl font-semibold text-slate-900">Tenants</h1>
            <p class="text-sm text-slate-600">Search and review tenant accounts before taking billing or access actions.</p>
        </div>

        <section class="rounded-md border border-slate-300 bg-white shadow-sm">
            <div class="border-b border-slate-300 bg-slate-50 px-4 py-3">
                <h2 class="text-base font-semibold text-slate-900">Filters</h2>
            </div>
            <form method="GET" action="{{ route('admin.tenants.index') }}" class="grid gap-4 p-4 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <label for="tenant-search" class="mb-1 block text-sm font-medium text-slate-700">Search</label>
                    <input id="tenant-search" type="text" name="q" value="{{ $filters['search'] }}"
                        placeholder="Name, email, domain"
                        class="w-full rounded-md border border-slate-400 px-3 py-2 text-sm text-slate-900 focus:border-slate-600 focus:outline-none focus:ring-0">
                </div>

                <div>
                    <label for="tenant-status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                    <select id="tenant-status" name="status"
                        class="w-full rounded-md border border-slate-400 px-3 py-2 text-sm text-slate-900 focus:border-slate-600 focus:outline-none focus:ring-0">
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="tenant-plan" class="mb-1 block text-sm font-medium text-slate-700">Plan</label>
                    <select id="tenant-plan" name="plan"
                        class="w-full rounded-md border border-slate-400 px-3 py-2 text-sm text-slate-900 focus:border-slate-600 focus:outline-none focus:ring-0">
                        <option value="all" @selected($filters['plan'] === 'all')>All plans</option>
                        @foreach ($planCatalog as $planKey => $plan)
                            <option value="{{ $planKey }}" @selected($filters['plan'] === $planKey)>{{ $plan['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="tenant-sort" class="mb-1 block text-sm font-medium text-slate-700">Sort</label>
                    <select id="tenant-sort" name="sort"
                        class="w-full rounded-md border border-slate-400 px-3 py-2 text-sm text-slate-900 focus:border-slate-600 focus:outline-none focus:ring-0">
                        @foreach ($sortOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-2 lg:col-span-5">
                    <button type="submit"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Apply
                    </button>
                    <a href="{{ route('admin.tenants.index') }}"
                        class="rounded-md border border-slate-400 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-md border border-slate-300 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">All tenants</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($summary['total']) }}</p>
            </div>
            <div class="rounded-md border border-slate-300 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Active</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($summary['active']) }}</p>
            </div>
            <div class="rounded-md border border-slate-300 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Suspended</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($summary['suspended']) }}</p>
            </div>
            <div class="rounded-md border border-slate-300 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Trial</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($summary['trial']) }}</p>
            </div>
            <div class="rounded-md border border-slate-300 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Expiring in 7 days</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($summary['expiring_soon']) }}</p>
            </div>
        </section>

        <section class="rounded-md border border-slate-300 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-300 bg-slate-50 px-4 py-3">
                <h2 class="text-base font-semibold text-slate-900">Tenant list</h2>
                <p class="text-sm text-slate-500">{{ number_format($tenants->total()) }} result{{ $tenants->total() === 1 ? '' : 's' }}</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-300">
                    <thead class="bg-slate-100">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-4 py-3">Tenant</th>
                            <th class="px-4 py-3">Plan</th>
                            <th class="px-4 py-3">Users</th>
                            <th class="px-4 py-3">Customers</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Renewal</th>
                            <th class="px-4 py-3">Last seen</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-300">
                        @forelse ($tenants as $tenant)
                            @php
                                $renewalDate = $tenant->is_trial ? $tenant->trial_ends_at : $tenant->subscription_ends_at;
                            @endphp
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-slate-900">{{ $tenant->name }}</div>
                                    <div class="text-sm text-slate-500">{{ $tenant->email }}</div>
                                    <div class="mt-1 text-xs text-slate-400">
                                        #{{ $tenant->id }}
                                        @if ($tenant->subdomain)
                                            · {{ $tenant->subdomain }}
                                        @endif
                                        @if ($tenant->domain)
                                            · {{ $tenant->domain }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">
                                    {{ ucfirst($tenant->plan) }}
                                    @if ($tenant->is_trial)
                                        <div class="text-xs text-slate-500">Trial</div>
                                    @else
                                        <div class="text-xs text-slate-500">{{ $planCatalog[$tenant->plan]['price_label'] ?? 'Custom' }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">
                                    {{ $tenant->users_count ?? 0 }}
                                    <span class="text-slate-400">/ {{ $tenant->max_users ?? '∞' }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">
                                    {{ $tenant->customers_count ?? 0 }}
                                    <span class="text-slate-400">/ {{ $tenant->max_customers ?? '∞' }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if (! $tenant->is_active)
                                        <span class="rounded bg-rose-100 px-2 py-1 text-xs font-medium text-rose-700">Suspended</span>
                                    @elseif ($tenant->is_expired)
                                        <span class="rounded bg-amber-100 px-2 py-1 text-xs font-medium text-amber-700">Expired</span>
                                    @else
                                        <span class="rounded bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">Active</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">
                                    {{ $renewalDate?->format('M d, Y') ?? 'Not scheduled' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-500">
                                    {{ $tenant->last_seen_at?->diffForHumans() ?? 'Never' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.tenants.show', $tenant) }}"
                                        class="rounded-md border border-slate-400 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                        Open
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">No tenants match the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($tenants->hasPages())
                <div class="border-t border-slate-300 px-4 py-3">
                    {{ $tenants->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
