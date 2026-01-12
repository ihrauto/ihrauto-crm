<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Admin Panel - {{ config('app.name', 'IHRAUTO CRM') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100">
        <!-- Navigation -->
        <nav class="bg-red-700 border-b border-red-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="shrink-0 flex items-center">
                            <span class="text-white font-bold text-xl">Super Admin Panel</span>
                        </div>
                        <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
                            <a href="{{ route('admin.tenants.index') }}" 
                               class="inline-flex items-center px-1 pt-1 border-b-2 border-white text-sm font-medium leading-5 text-white">
                                Tenants
                            </a>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="text-white text-sm mr-4">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-white text-sm hover:underline">Logout</button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main>
            <div class="py-12">
                <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    @if (session('success'))
                        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900">
                            <h2 class="text-2xl font-bold mb-6">All Tenants</h2>
                            
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Limits</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trial/Sub Ends</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @forelse ($tenants as $tenant)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $tenant->id }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $tenant->name }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $tenant->email }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                        {{ $tenant->plan === 'custom' ? 'bg-purple-100 text-purple-800' : '' }}
                                                        {{ $tenant->plan === 'standard' ? 'bg-blue-100 text-blue-800' : '' }}
                                                        {{ $tenant->plan === 'basic' ? 'bg-green-100 text-green-800' : '' }}">
                                                        {{ ucfirst($tenant->plan ?? 'basic') }}
                                                        @if($tenant->is_trial)
                                                            <span class="ml-1 text-orange-600">(Trial)</span>
                                                        @endif
                                                    </span>
                                                    @if($tenant->plan === 'basic')
                                                        <div class="text-xs text-gray-400 mt-1">€49/mo</div>
                                                    @elseif($tenant->plan === 'standard')
                                                        <div class="text-xs text-gray-400 mt-1">€149/mo</div>
                                                    @elseif($tenant->plan === 'custom')
                                                        <div class="text-xs text-gray-400 mt-1">Custom</div>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500">
                                                    <div>{{ $tenant->users_count ?? 0 }}/{{ $tenant->max_users }} users</div>
                                                    <div>{{ $tenant->customers_count ?? 0 }}/{{ number_format($tenant->max_customers) }} customers</div>
                                                    @if($tenant->plan === 'basic' && $tenant->max_work_orders)
                                                        <div class="text-orange-600">{{ $tenant->max_work_orders }} WO/mo</div>
                                                    @else
                                                        <div class="text-green-600">∞ WO</div>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if ($tenant->is_active)
                                                        @if ($tenant->is_expired)
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                                Expired
                                                            </span>
                                                        @else
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                                Active
                                                            </span>
                                                        @endif
                                                    @else
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                            Suspended
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    @if ($tenant->is_trial && $tenant->trial_ends_at)
                                                        {{ $tenant->trial_ends_at->format('M d, Y') }}
                                                        <span class="text-xs text-gray-400">(trial)</span>
                                                    @elseif ($tenant->subscription_ends_at)
                                                        {{ $tenant->subscription_ends_at->format('M d, Y') }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {{ $tenant->created_at->format('M d, Y') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <form action="{{ route('admin.tenants.toggle', $tenant) }}" method="POST" class="inline">
                                                        @csrf
                                                        @if ($tenant->is_active)
                                                            <button type="submit" 
                                                                    class="text-red-600 hover:text-red-900"
                                                                    onclick="return confirm('Are you sure you want to suspend this tenant?')">
                                                                Suspend
                                                            </button>
                                                        @else
                                                            <button type="submit" class="text-green-600 hover:text-green-900">
                                                                Activate
                                                            </button>
                                                        @endif
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                                                    No tenants found.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4">
                                {{ $tenants->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
