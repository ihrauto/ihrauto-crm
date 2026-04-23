@extends('layouts.app')

@section('title', 'Tenant Control')

@section('content')
    @php
        $renewalDate = $tenantProfile['renewal_date'];
        $statusLabel = ! $tenant->is_active ? 'Suspended' : ($tenant->is_expired ? 'Expired' : 'Active');
    @endphp

    <div class="space-y-8">
        @if ($errors->any())
            <div class="rounded-md border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <p class="font-medium">Some tenant actions could not be completed.</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-col gap-3 border-b border-slate-300 pb-5 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="mb-2">
                    <a href="{{ route('admin.tenants.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                        Back to tenants
                    </a>
                </div>
                <h1 class="text-2xl font-semibold text-slate-900">{{ $tenant->name }}</h1>
                <p class="mt-1 text-sm text-slate-600">Tenant #{{ $tenant->id }} · {{ $tenant->email }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">
                    {{ ucfirst($tenant->plan) }}{{ $tenant->is_trial ? ' trial' : '' }}
                </span>
                @if (! $tenant->is_active)
                    <span class="rounded bg-rose-100 px-2 py-1 text-xs font-medium text-rose-700">{{ $statusLabel }}</span>
                @elseif ($tenant->is_expired)
                    <span class="rounded bg-amber-100 px-2 py-1 text-xs font-medium text-amber-700">{{ $statusLabel }}</span>
                @else
                    <span class="rounded bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-700">{{ $statusLabel }}</span>
                @endif
            </div>
        </div>

        <section class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-md border border-slate-300 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Renewal</p>
                <p class="mt-2 text-lg font-semibold text-slate-900">{{ $renewalDate?->format('M d, Y') ?? 'Not scheduled' }}</p>
                <p class="mt-1 text-sm text-slate-500">
                    @if (! is_null($tenant->days_remaining))
                        {{ $tenant->days_remaining }} day{{ $tenant->days_remaining === 1 ? '' : 's' }} remaining
                    @else
                        No end date
                    @endif
                </p>
            </div>
            <div class="rounded-md border border-slate-300 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last seen</p>
                <p class="mt-2 text-lg font-semibold text-slate-900">{{ $tenantProfile['last_seen_at']?->diffForHumans() ?? 'Never' }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $tenantProfile['last_seen_at']?->format('M d, Y H:i') ?? 'No activity recorded' }}</p>
            </div>
            <div class="rounded-md border border-slate-300 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Users</p>
                <p class="mt-2 text-lg font-semibold text-slate-900">{{ $metrics['active_users_count'] }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $metrics['pending_invites_count'] }} pending invites</p>
            </div>
            <div class="rounded-md border border-slate-300 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Revenue captured</p>
                <p class="mt-2 text-lg font-semibold text-slate-900">EUR {{ number_format($metrics['total_paid'] ?? 0, 2) }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $metrics['paid_invoices_count'] }} paid invoices</p>
            </div>
        </section>

        <section class="grid gap-8 xl:grid-cols-[1.6fr,1fr]">
            <div class="space-y-6">
                <div class="rounded-md border border-slate-300 bg-white shadow-sm">
                    <div class="border-b border-slate-300 bg-slate-50 px-4 py-3">
                        <h2 class="text-base font-semibold text-slate-900">Overview</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-300">
                            <tbody class="divide-y divide-slate-300 text-sm">
                                <tr>
                                    <th class="w-56 bg-slate-100 px-4 py-3 text-left font-medium text-slate-600">Tenant URL</th>
                                    <td class="px-4 py-3 text-slate-800">
                                        {{ $tenant->domain ?: ($tenant->subdomain ? $tenant->subdomain.'.'.config('app.domain', 'yourapp.com') : 'Not configured') }}
                                    </td>
                                </tr>
                                <tr>
                                    <th class="bg-slate-100 px-4 py-3 text-left font-medium text-slate-600">Plan</th>
                                    <td class="px-4 py-3 text-slate-800">{{ $tenantProfile['plan_definition']['name'] }}</td>
                                </tr>
                                <tr>
                                    <th class="bg-slate-100 px-4 py-3 text-left font-medium text-slate-600">Location</th>
                                    <td class="px-4 py-3 text-slate-800">{{ $tenant->city ?: 'Unknown city' }}, {{ $tenant->country ?: 'Unknown country' }}</td>
                                </tr>
                                <tr>
                                    <th class="bg-slate-100 px-4 py-3 text-left font-medium text-slate-600">Timezone</th>
                                    <td class="px-4 py-3 text-slate-800">{{ $tenant->timezone ?: 'UTC' }}</td>
                                </tr>
                                <tr>
                                    <th class="bg-slate-100 px-4 py-3 text-left font-medium text-slate-600">API access</th>
                                    <td class="px-4 py-3 text-slate-800">
                                        {{ $tenant->hasApiAccess() ? 'Enabled' : 'Not available on current plan' }}
                                        · {{ $metrics['active_api_tokens_count'] }} active token{{ $metrics['active_api_tokens_count'] === 1 ? '' : 's' }}
                                    </td>
                                </tr>
                                <tr>
                                    <th class="bg-slate-100 px-4 py-3 text-left font-medium text-slate-600">Created</th>
                                    <td class="px-4 py-3 text-slate-800">{{ $tenant->created_at->format('M d, Y H:i') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-md border border-slate-300 bg-white shadow-sm">
                    <div class="border-b border-slate-300 bg-slate-50 px-4 py-3">
                        <h2 class="text-base font-semibold text-slate-900">Usage and limits</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-300">
                            <thead class="bg-slate-100">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="px-4 py-3">Metric</th>
                                    <th class="px-4 py-3">Current</th>
                                    <th class="px-4 py-3">Limit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-300 text-sm">
                                <tr>
                                    <td class="px-4 py-3 text-slate-700">Users</td>
                                    <td class="px-4 py-3 text-slate-900">{{ $metrics['users_count'] }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $usageLimits['users'] ?? 'Unlimited' }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-slate-700">Customers</td>
                                    <td class="px-4 py-3 text-slate-900">{{ $metrics['customers_count'] }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $usageLimits['customers'] ?? 'Unlimited' }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-slate-700">Vehicles</td>
                                    <td class="px-4 py-3 text-slate-900">{{ $metrics['vehicles_count'] }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $usageLimits['vehicles'] ?? 'Unlimited' }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-slate-700">Work orders this month</td>
                                    <td class="px-4 py-3 text-slate-900">{{ $metrics['current_month_workorders_count'] }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $usageLimits['work_orders'] ?? 'Unlimited' }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-slate-700">Open work orders</td>
                                    <td class="px-4 py-3 text-slate-900">{{ $metrics['open_workorders_count'] }}</td>
                                    <td class="px-4 py-3 text-slate-700">n/a</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-slate-700">Check-ins</td>
                                    <td class="px-4 py-3 text-slate-900">{{ $metrics['checkins_count'] }}</td>
                                    <td class="px-4 py-3 text-slate-700">n/a</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 text-slate-700">Invoices</td>
                                    <td class="px-4 py-3 text-slate-900">{{ $metrics['invoices_count'] }}</td>
                                    <td class="px-4 py-3 text-slate-700">n/a</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-md border border-slate-300 bg-white shadow-sm">
                    <div class="border-b border-slate-300 bg-slate-50 px-4 py-3">
                        <h2 class="text-base font-semibold text-slate-900">Audit log</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-300">
                            <thead class="bg-slate-100">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Action</th>
                                    <th class="px-4 py-3">Reason</th>
                                    <th class="px-4 py-3">By</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-300 text-sm">
                                @forelse ($actionLogs as $log)
                                    <tr>
                                        <td class="px-4 py-3 text-slate-600">{{ $log->created_at->format('M d, Y H:i') }}</td>
                                        <td class="px-4 py-3 font-medium text-slate-900">{{ strtoupper(str_replace('_', ' ', $log->action)) }}</td>
                                        <td class="px-4 py-3 text-slate-700">{{ $log->changes['reason'] ?? 'No reason recorded.' }}</td>
                                        <td class="px-4 py-3 text-slate-600">{{ $log->user->name ?? 'System' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-6 text-center text-slate-500">No owner actions recorded yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-md border border-slate-300 bg-white shadow-sm">
                    <div class="border-b border-slate-300 bg-slate-50 px-4 py-3">
                        <h2 class="text-base font-semibold text-slate-900">Internal notes</h2>
                    </div>
                    <div class="p-4">
                        <form action="{{ route('admin.tenants.note', $tenant) }}" method="POST" class="mb-4 flex flex-col gap-3 sm:flex-row">
                            @csrf
                            <input type="text" name="note" required placeholder="Add private note"
                                class="flex-1 rounded-md border border-slate-400 px-3 py-2 text-sm text-slate-900 focus:border-slate-600 focus:outline-none focus:ring-0">
                            <button type="submit"
                                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                                Save note
                            </button>
                        </form>

                        <div class="space-y-3">
                            @forelse ($notes as $note)
                                <div class="rounded-md border border-slate-300 p-3" id="note-{{ $note->id }}">
                                    <div id="note-view-{{ $note->id }}">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 flex-1">
                                                <p class="whitespace-pre-wrap break-words text-sm text-slate-800">{{ $note->changes['content'] ?? '' }}</p>
                                                <p class="mt-2 text-xs text-slate-500">
                                                    {{ $note->created_at->format('M d, Y H:i') }} · {{ $note->user->name ?? 'Unknown' }}
                                                </p>
                                            </div>
                                            <div class="flex items-center gap-3 text-xs">
                                                <button type="button" onclick="editNote({{ $note->id }})" class="font-medium text-slate-600 hover:text-slate-900">
                                                    Edit
                                                </button>
                                                <form action="{{ route('admin.tenants.note.delete', [$tenant, $note]) }}" method="POST"
                                                    onsubmit="return confirm('Delete this note?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="font-medium text-rose-600 hover:text-rose-800">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <form action="{{ route('admin.tenants.note.update', [$tenant, $note]) }}" method="POST"
                                        id="note-edit-{{ $note->id }}" class="hidden space-y-3">
                                        @csrf
                                        @method('PUT')
                                        <textarea name="note" rows="3"
                                            class="w-full rounded-md border border-slate-400 px-3 py-2 text-sm text-slate-900 focus:border-slate-600 focus:outline-none focus:ring-0">{{ $note->changes['content'] ?? '' }}</textarea>
                                        <div class="flex items-center gap-3">
                                            <button type="submit"
                                                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                                                Save
                                            </button>
                                            <button type="button" onclick="cancelEdit({{ $note->id }})" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            @empty
                            <div class="rounded-md border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-500">
                                No private notes yet.
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <aside class="space-y-6">
                <div class="rounded-md border border-slate-300 bg-white shadow-sm">
                    <div class="border-b border-slate-300 bg-slate-50 px-4 py-3">
                        <h2 class="text-base font-semibold text-slate-900">Billing and access</h2>
                    </div>
                    <div class="space-y-6 p-4">
                        <section>
                            <h3 class="text-sm font-medium text-slate-900">Set manual billing</h3>
                            <p class="mt-1 text-sm text-slate-500">Update plan and renewal date.</p>
                            <form action="{{ route('admin.tenants.billing', $tenant) }}" method="POST" class="mt-3 space-y-3">
                                @csrf
                                <select name="plan" required
                                    class="w-full rounded-md border border-slate-400 px-3 py-2 text-sm text-slate-900 focus:border-slate-600 focus:outline-none focus:ring-0">
                                    @foreach ($planOptions as $planKey => $plan)
                                        <option value="{{ $planKey }}" @selected(old('plan', $tenant->plan) === $planKey)>
                                            {{ $plan['name'] }} · {{ $plan['price_label'] }} {{ $plan['billing_label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="date" name="renewal_date"
                                    value="{{ old('renewal_date', optional($tenant->subscription_ends_at)->format('Y-m-d')) }}"
                                    class="w-full rounded-md border border-slate-400 px-3 py-2 text-sm text-slate-900 focus:border-slate-600 focus:outline-none focus:ring-0">
                                <input type="text" name="reason" required value="{{ old('reason') }}"
                                    placeholder="Reason"
                                    class="w-full rounded-md border border-slate-400 px-3 py-2 text-sm text-slate-900 focus:border-slate-600 focus:outline-none focus:ring-0">
                                <button type="submit"
                                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                                    onclick="return confirm('Apply billing changes for this tenant?')">
                                    Save billing
                                </button>
                            </form>
                        </section>

                        <section class="border-t border-slate-300 pt-6">
                            <h3 class="text-sm font-medium text-slate-900">Grant bonus days</h3>
                            <p class="mt-1 text-sm text-slate-500">Extend the current trial or subscription.</p>
                            <form action="{{ route('admin.tenants.bonus', $tenant) }}" method="POST" class="mt-3 space-y-3">
                                @csrf
                                <input type="number" name="days" min="1" max="365" required placeholder="Days"
                                    class="w-full rounded-md border border-slate-400 px-3 py-2 text-sm text-slate-900 focus:border-slate-600 focus:outline-none focus:ring-0">
                                <input type="text" name="reason" required placeholder="Reason"
                                    class="w-full rounded-md border border-slate-400 px-3 py-2 text-sm text-slate-900 focus:border-slate-600 focus:outline-none focus:ring-0">
                                <button type="submit"
                                    class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                    onclick="return confirm('Grant bonus days to this tenant?')">
                                    Grant extension
                                </button>
                            </form>
                        </section>

                        <section class="border-t border-slate-300 pt-6">
                            <h3 class="text-sm font-medium text-slate-900">Access intervention</h3>
                            <p class="mt-1 text-sm text-slate-500">Suspend or restore access with a reason.</p>

                            @if ($tenant->is_active)
                                <form action="{{ route('admin.tenants.suspend', $tenant) }}" method="POST" class="mt-3 space-y-3">
                                    @csrf
                                    <input type="text" name="reason" required placeholder="Reason for suspension"
                                        class="w-full rounded-md border border-slate-400 px-3 py-2 text-sm text-slate-900 focus:border-slate-600 focus:outline-none focus:ring-0">
                                    <input type="text" id="confirmSuspend" placeholder="Type SUSPEND to confirm"
                                        class="w-full rounded-md border border-slate-400 px-3 py-2 text-sm text-slate-900 focus:border-slate-600 focus:outline-none focus:ring-0">
                                    <button type="submit"
                                        class="rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700"
                                        onclick="return document.getElementById('confirmSuspend').value === 'SUSPEND' || (alert('Type SUSPEND to confirm'), false)">
                                        Suspend access
                                    </button>
                                </form>
                            @else
                                <form action="{{ route('admin.tenants.activate', $tenant) }}" method="POST" class="mt-3 space-y-3">
                                    @csrf
                                    <input type="text" name="reason" required placeholder="Reason for reactivation"
                                        class="w-full rounded-md border border-slate-400 px-3 py-2 text-sm text-slate-900 focus:border-slate-600 focus:outline-none focus:ring-0">
                                    <button type="submit"
                                        class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                                        onclick="return confirm('Restore access for this tenant?')">
                                        Restore access
                                    </button>
                                </form>
                            @endif
                        </section>
                    </div>
                </div>

                <div class="rounded-md border border-rose-300 bg-white shadow-sm">
                    <div class="border-b border-rose-300 bg-rose-50 px-4 py-3">
                        <h2 class="text-base font-semibold text-rose-700">Archive tenant</h2>
                    </div>
                    <div class="p-4">
                        <p class="mb-3 text-sm text-slate-600">
                            This archives the tenant and deactivates access.
                        </p>
                        <form action="{{ route('admin.tenants.destroy', $tenant) }}" method="POST" class="space-y-3">
                            @csrf
                            @method('DELETE')
                            <input type="text" name="confirmation" required placeholder="Type DELETE to confirm"
                                class="w-full rounded-md border border-slate-400 px-3 py-2 text-sm text-slate-900 focus:border-slate-600 focus:outline-none focus:ring-0">
                            <button type="submit"
                                class="rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700"
                                onclick="return confirm('Archive this tenant and deactivate all access?')">
                                Archive tenant
                            </button>
                        </form>
                    </div>
                </div>
            </aside>
        </section>
    </div>

    <script>
        function editNote(id) {
            document.getElementById('note-view-' + id).classList.add('hidden');
            document.getElementById('note-edit-' + id).classList.remove('hidden');
        }

        function cancelEdit(id) {
            document.getElementById('note-view-' + id).classList.remove('hidden');
            document.getElementById('note-edit-' + id).classList.add('hidden');
        }
    </script>
@endsection
