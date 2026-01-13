@extends('layouts.app')

@section('title', 'Tenant Management')

@section('content')
    <div class="max-w-7xl mx-auto space-y-6">

        {{-- Header --}}
        <div class="flex items-center justify-between border-b border-gray-200 pb-4">
            <div>
                <h1 class="text-xl font-bold text-gray-900 tracking-tight">Tenants</h1>
                <p class="text-xs text-gray-500 mt-1">Platform overview and management</p>
            </div>
            <div>
                {{-- Add Tenant button could go here if needed --}}
            </div>
        </div>

        {{-- Operational Tenant Table --}}
        <div class="bg-white border border-gray-200 rounded-md shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th
                                class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-12">
                                ID</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Tenant / Email</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Plan</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Usage / Limit</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Next Renewal</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($tenants as $tenant)
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                {{-- ID --}}
                                <td class="px-4 py-2.5 text-xs text-gray-400 font-mono">
                                    #{{ $tenant->id }}
                                </td>

                                {{-- Tenant Name & Email --}}
                                <td class="px-4 py-2.5">
                                    <div class="flex flex-col">
                                        <a href="{{ route('admin.tenants.show', $tenant) }}"
                                            class="text-sm font-bold text-gray-900 leading-tight hover:text-indigo-600 hover:underline">{{ $tenant->name }}</a>
                                        <span class="text-xs text-gray-400 font-mono mt-0.5">{{ $tenant->email }}</span>
                                    </div>
                                </td>

                                {{-- Plan --}}
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <span class="text-sm font-medium text-gray-700">
                                        {{ ucfirst($tenant->plan ?? 'basic') }}
                                    </span>
                                    @if($tenant->is_trial)
                                        <span class="text-xs text-amber-600 font-bold ml-1">(Trial)</span>
                                    @endif
                                    <div class="text-[10px] text-gray-400">
                                        @if($tenant->plan === 'basic') €49/mo
                                        @elseif($tenant->plan === 'standard') €149/mo
                                        @else Custom
                                        @endif
                                    </div>
                                </td>

                                {{-- Usage Limits --}}
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    <div class="flex flex-col space-y-0.5">
                                        <div class="text-xs text-gray-600">
                                            <span class="font-medium text-gray-900">{{ $tenant->users_count ?? 0 }}</span>
                                            <span class="text-gray-400">/{{ $tenant->max_users }} users</span>
                                        </div>
                                        <div class="text-xs text-gray-600">
                                            <span class="font-medium text-gray-900">{{ $tenant->customers_count ?? 0 }}</span>
                                            <span class="text-gray-400">/{{ $tenant->max_customers }} clients</span>
                                        </div>
                                    </div>
                                </td>

                                {{-- Status --}}
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    @if (!$tenant->is_active)
                                        <span class="text-xs font-bold text-red-600 flex items-center">
                                            <div class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5"></div>
                                            Suspended
                                        </span>
                                    @elseif ($tenant->is_expired)
                                        <span class="text-xs font-bold text-amber-600 flex items-center">
                                            <div class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5"></div>
                                            Expired
                                        </span>
                                    @else
                                        <span class="text-xs font-bold text-green-700 flex items-center">
                                            <div class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1.5"></div>
                                            Active
                                        </span>
                                    @endif
                                </td>

                                {{-- Renewal Date --}}
                                <td class="px-4 py-2.5 whitespace-nowrap text-sm text-gray-600">
                                    @if ($tenant->is_trial && $tenant->trial_ends_at)
                                        {{ $tenant->trial_ends_at->format('M d, Y') }}
                                    @elseif ($tenant->subscription_ends_at)
                                        {{ $tenant->subscription_ends_at->format('M d, Y') }}
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="px-4 py-2.5 whitespace-nowrap text-right text-xs font-medium">
                                    <form action="{{ route('admin.tenants.toggle', $tenant) }}" method="POST" class="inline">
                                        @csrf
                                        @if ($tenant->is_active)
                                            <button type="submit"
                                                class="text-red-600 hover:text-red-900 underline decoration-red-200 underline-offset-2"
                                                onclick="return confirm('Suspend {{ $tenant->name }}?')">
                                                Suspend
                                            </button>
                                        @else
                                            <button type="submit"
                                                class="text-green-600 hover:text-green-900 underline decoration-green-200 underline-offset-2">
                                                Activate
                                            </button>
                                        @endif
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">
                                    No tenants found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($tenants->hasPages())
                <div class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                    {{ $tenants->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection