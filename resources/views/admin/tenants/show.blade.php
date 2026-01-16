@extends('layouts.app')

@section('title', 'Tenant Control')

@section('content')
    <div class="max-w-5xl mx-auto space-y-6 pb-12">

        {{-- Header --}}
        <div class="flex items-center justify-between border-b border-gray-200 pb-4">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.tenants.index') }}"
                    class="group flex items-center justify-center w-8 h-8 bg-gray-900 hover:bg-gray-600 rounded transition-colors shadow-sm">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 tracking-tight">{{ $tenant->name }}</h1>
                    <p class="text-xs text-gray-500 font-mono">#{{ $tenant->id }} · {{ $tenant->email }}</p>
                </div>
            </div>
            <div>
                @if(!$tenant->is_active)
                    <span class="text-xs font-bold text-red-600 flex items-center"><span
                            class="w-2 h-2 rounded-full bg-red-500 mr-1.5"></span>Suspended</span>
                @elseif($tenant->is_expired)
                    <span class="text-xs font-bold text-amber-600 flex items-center"><span
                            class="w-2 h-2 rounded-full bg-amber-500 mr-1.5"></span>Expired</span>
                @else
                    <span class="text-xs font-bold text-green-700 flex items-center"><span
                            class="w-2 h-2 rounded-full bg-green-500 mr-1.5"></span>Active</span>
                @endif
            </div>
        </div>

        {{-- SECTION A: Identity & Context --}}
        <div class="border-b border-gray-100 pb-6">
            <h2 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-3">Identity & Context</h2>
            <dl class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-3 text-sm">
                <div>
                    <dt class="text-gray-400 text-xs">Plan</dt>
                    <dd class="font-semibold text-gray-900">{{ ucfirst($tenant->plan) }}@if($tenant->is_trial) <span
                    class="text-amber-600 text-xs">(Trial)</span>@endif</dd>
                </div>
                <div>
                    <dt class="text-gray-400 text-xs">Member Since</dt>
                    <dd class="font-medium text-gray-900">{{ $tenant->created_at->format('M d, Y') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-400 text-xs">{{ $tenant->is_trial ? 'Trial Ends' : 'Next Renewal' }}</dt>
                    <dd class="font-medium text-gray-900">
                        {{ $tenant->is_trial ? ($tenant->trial_ends_at ? $tenant->trial_ends_at->format('M d, Y') : '—') : ($tenant->subscription_ends_at ? $tenant->subscription_ends_at->format('M d, Y') : '—') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-400 text-xs">Locale</dt>
                    <dd class="font-medium text-gray-900">{{ $tenant->country ?? 'Unknown' }} ·
                        {{ $tenant->timezone ?? 'UTC' }}
                    </dd>
                </div>
            </dl>
        </div>

        {{-- SECTION B: Usage & Value --}}
        <div class="border-b border-gray-100 pb-6">
            <h2 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-3">Usage & Value (Lifetime)</h2>
            <div class="flex flex-wrap gap-x-8 gap-y-2">
                <div><span class="text-lg font-bold text-gray-900">{{ $metrics['users_count'] }}</span> <span
                        class="text-xs text-gray-500">users</span></div>
                <div><span class="text-lg font-bold text-gray-900">{{ $metrics['customers_count'] }}</span> <span
                        class="text-xs text-gray-500">customers</span></div>
                <div><span class="text-lg font-bold text-gray-900">{{ $metrics['vehicles_count'] }}</span> <span
                        class="text-xs text-gray-500">vehicles</span></div>
                <div class="border-l border-gray-200 pl-6"><span
                        class="text-lg font-bold text-gray-900">{{ $metrics['checkins_count'] }}</span> <span
                        class="text-xs text-gray-500">check-ins</span></div>
                <div><span class="text-lg font-bold text-gray-900">{{ $metrics['workorders_count'] }}</span> <span
                        class="text-xs text-gray-500">work orders</span></div>
                <div><span class="text-lg font-bold text-gray-900">{{ $metrics['invoices_count'] }}</span> <span
                        class="text-xs text-gray-500">invoices</span></div>
            </div>
        </div>

        {{-- SECTION C: Billing Audit --}}
        <div class="border-b border-gray-100 pb-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Billing Audit</h2>
                <span class="text-[10px] text-gray-400 italic">Read-only</span>
            </div>
            <dl class="flex flex-wrap gap-x-8 gap-y-2 text-sm">
                <div>
                    <dt class="text-gray-400 text-xs">Rate</dt>
                    <dd class="font-semibold text-gray-900">@if($tenant->plan === 'basic')€49/mo
                    @elseif($tenant->plan === 'standard')€149/mo @else Custom @endif</dd>
                </div>
                <div>
                    <dt class="text-gray-400 text-xs">Total Revenue</dt>
                    <dd class="font-semibold text-green-700">€{{ number_format($metrics['total_paid'] ?? 0, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-400 text-xs">Status</dt>
                    <dd class="font-medium {{ $tenant->is_active ? 'text-green-700' : 'text-red-600' }}">
                        {{ $tenant->is_active ? 'Good Standing' : 'Attention Needed' }}
                    </dd>
                </div>
            </dl>
        </div>

        {{-- SECTION D: Restricted Owner Actions --}}
        <div class="bg-gray-50 border border-gray-200 rounded p-6">
            <h2 class="text-[10px] font-bold text-red-800 uppercase tracking-wider mb-1">Restricted Owner Actions</h2>
            <p class="text-[10px] text-gray-500 mb-6">Actions here affect billing and access immediately. All actions are
                logged.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                {{-- Bonus Days --}}
                <div>
                    <h3 class="text-xs font-bold text-gray-700 mb-1">Grant Bonus Days</h3>
                    <p class="text-[10px] text-gray-500 mb-3">Extends trial or subscription end date.</p>
                    <form action="{{ route('admin.tenants.bonus', $tenant) }}" method="POST" class="space-y-2">
                        @csrf
                        <input type="number" name="days" min="1" max="365" required placeholder="Days"
                            class="w-full text-xs border border-gray-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-gray-400 focus:border-gray-400 bg-white">
                        <input type="text" name="reason" required placeholder="Reason (required)"
                            class="w-full text-xs border border-gray-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-gray-400 focus:border-gray-400 bg-white">
                        <button type="submit"
                            class="w-full text-xs font-medium py-1.5 px-3 border border-gray-300 rounded bg-white text-gray-700 hover:bg-gray-100"
                            onclick="return confirm('This action immediately affects billing. Continue?')">
                            Grant Extension
                        </button>
                    </form>
                </div>

                {{-- Suspend / Activate --}}
                <div class="md:border-l md:border-gray-200 md:pl-8">
                    <h3 class="text-xs font-bold text-gray-700 mb-1">Intervention</h3>
                    <p class="text-[10px] text-gray-500 mb-3">Suspend or restore access immediately.</p>

                    @if($tenant->is_active)
                        <form action="{{ route('admin.tenants.suspend', $tenant) }}" method="POST" class="space-y-2"
                            id="suspendForm">
                            @csrf
                            <input type="text" name="reason" required placeholder="Reason for suspension (required)"
                                class="w-full text-xs border border-gray-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-red-400 focus:border-red-400 bg-white">
                            <input type="text" id="confirmSuspend" placeholder="Type SUSPEND to confirm"
                                class="w-full text-xs border border-gray-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-red-400 focus:border-red-400 bg-white">
                            <button type="submit"
                                class="w-full text-xs font-medium py-1.5 px-3 border border-red-300 rounded bg-white text-red-700 hover:bg-red-50"
                                onclick="return document.getElementById('confirmSuspend').value === 'SUSPEND' || (alert('Type SUSPEND to confirm'), false)">
                                Suspend Access
                            </button>
                        </form>
                    @else
                        <form action="{{ route('admin.tenants.activate', $tenant) }}" method="POST" class="space-y-2">
                            @csrf
                            <input type="text" name="reason" required placeholder="Reason for reactivation"
                                class="w-full text-xs border border-gray-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-green-400 focus:border-green-400 bg-white">
                            <button type="submit"
                                class="w-full text-xs font-medium py-1.5 px-3 border border-green-300 rounded bg-white text-green-700 hover:bg-green-50"
                                onclick="return confirm('Restore access for this tenant?')">
                                Restore Access
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Delete Tenant (Danger Zone) --}}
            <div class="mt-6 pt-6 border-t border-red-200">
                <h3 class="text-xs font-bold text-red-700 mb-1">⚠️ Danger Zone - Delete Tenant</h3>
                <p class="text-[10px] text-gray-500 mb-3">Permanently delete this tenant and ALL associated data. This
                    action cannot be undone.</p>
                <form action="{{ route('admin.tenants.destroy', $tenant) }}" method="POST" class="space-y-2">
                    @csrf
                    @method('DELETE')
                    <input type="text" name="confirmation" required placeholder="Type DELETE to confirm"
                        class="w-full text-xs border border-red-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-red-500 focus:border-red-500 bg-red-50">
                    <button type="submit"
                        class="w-full text-xs font-bold py-1.5 px-3 border border-red-500 rounded bg-red-600 text-white hover:bg-red-700"
                        onclick="return confirm('⚠️ FINAL WARNING: This will permanently delete {{ $tenant->name }} and all users, customers, invoices, and data. This CANNOT be undone. Are you absolutely sure?')">
                        🗑️ Delete Tenant Permanently
                    </button>
                </form>
            </div>
        </div>

        {{-- Owner Actions Log --}}
        <div class="mt-6">
            <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-3">Owner Actions Log</h3>
            <div class="border border-gray-200 rounded overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">Action</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">Reason</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($actionLogs as $log)
                            <tr>
                                <td class="px-3 py-2 text-gray-500">{{ $log->created_at->format('M d, H:i') }}</td>
                                <td class="px-3 py-2 font-bold text-gray-700">{{ strtoupper($log->action) }}</td>
                                <td class="px-3 py-2 text-gray-900">{{ $log->changes['reason'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $log->user->name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-center text-gray-400 italic text-[10px]">No actions
                                    recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Internal Notes (Separate Section) --}}
        <div class="mt-8 border-t border-gray-200 pt-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Internal Notes</h3>
                <span class="text-[10px] text-gray-400 italic">Private memory. Not visible to tenant.</span>
            </div>

            {{-- Add Note Form --}}
            <form action="{{ route('admin.tenants.note', $tenant) }}" method="POST" class="flex gap-2 mb-4">
                @csrf
                <input type="text" name="note" required placeholder="Add a note... (e.g. Called client, promised extension)"
                    class="flex-1 text-xs border border-gray-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-gray-400 focus:border-gray-400 bg-white">
                <button type="submit"
                    class="text-xs font-medium py-1.5 px-4 border border-gray-300 rounded bg-white text-gray-700 hover:bg-gray-100">Add</button>
            </form>

            {{-- Notes List --}}
            <div class="space-y-2">
                @forelse($notes as $note)
                    <div class="bg-white border border-gray-200 rounded p-3 group" id="note-{{ $note->id }}">
                        <div class="flex justify-between items-start gap-4">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900 break-words whitespace-pre-wrap"
                                    id="note-content-{{ $note->id }}">{{ $note->changes['content'] ?? '' }}</p>
                                <p class="text-[10px] text-gray-400 mt-1">{{ $note->created_at->format('M d, Y H:i') }} ·
                                    {{ $note->user->name ?? 'Unknown' }}
                                </p>
                            </div>
                            <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                                <button type="button" onclick="editNote({{ $note->id }})"
                                    class="text-[10px] text-gray-500 hover:text-gray-700 underline">Edit</button>
                                <form action="{{ route('admin.tenants.note.delete', [$tenant, $note]) }}" method="POST"
                                    class="inline" onsubmit="return confirm('Delete this note?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="text-[10px] text-red-500 hover:text-red-700 underline">Delete</button>
                                </form>
                            </div>
                        </div>
                        {{-- Edit Form (Hidden by default) --}}
                        <form action="{{ route('admin.tenants.note.update', [$tenant, $note]) }}" method="POST"
                            class="hidden mt-2" id="edit-form-{{ $note->id }}">
                            @csrf
                            @method('PUT')
                            <textarea name="note" rows="2"
                                class="w-full text-xs border border-gray-300 rounded px-2 py-1.5 focus:ring-1 focus:ring-gray-400 focus:border-gray-400 bg-white">{{ $note->changes['content'] ?? '' }}</textarea>
                            <div class="flex gap-2 mt-2">
                                <button type="submit"
                                    class="text-xs font-medium py-1 px-3 border border-gray-300 rounded bg-white text-gray-700 hover:bg-gray-100">Save</button>
                                <button type="button" onclick="cancelEdit({{ $note->id }})"
                                    class="text-xs text-gray-500 hover:text-gray-700">Cancel</button>
                            </div>
                        </form>
                    </div>
                @empty
                    <p class="text-xs text-gray-400 italic py-4 text-center">No notes yet. Add notes to remember important
                        context about this tenant.</p>
                @endforelse
            </div>
        </div>

    </div>

    <script>
        function editNote(id) {
            document.getElementById('note-content-' + id).parentElement.classList.add('hidden');
            document.getElementById('edit-form-' + id).classList.remove('hidden');
        }
        function cancelEdit(id) {
            document.getElementById('note-content-' + id).parentElement.classList.remove('hidden');
            document.getElementById('edit-form-' + id).classList.add('hidden');
        }
    </script>
@endsection