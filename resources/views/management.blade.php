@extends('layouts.app')

@section('title', 'Management')

@section('content')
    <div class="space-y-6">
        <!-- Header Controls -->
        <div class="flex justify-end gap-3 mb-6">
            <a href="{{ route('management.reports') }}"
                class="inline-flex items-center justify-center px-4 py-2.5 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white bg-indigo-900 hover:bg-indigo-800 transition-all hover:shadow-lg hover:-translate-y-0.5">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                    </path>
                </svg>
                Generate Reports
            </a>
            <a href="{{ route('management.settings') }}"
                class="inline-flex items-center justify-center px-4 py-2.5 border border-indigo-200 text-sm font-semibold rounded-lg shadow-sm text-indigo-700 bg-white hover:bg-indigo-50 transition-all hover:shadow-md">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                    </path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Settings
            </a>
        </div>

        <!-- Key Performance Indicators -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <x-card
                class="border-l-4 border-indigo-600 shadow-sm ring-1 ring-indigo-50 hover:shadow-lg transition-shadow duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-indigo-500">Monthly Revenue</p>
                        <p class="text-2xl font-bold text-indigo-950">
                            ${{ number_format($kpis['monthly_revenue']['current'], 0) }}</p>
                        <p
                            class="text-xs {{ $kpis['monthly_revenue']['trend'] === 'up' ? 'text-emerald-600' : 'text-red-600' }} mt-1 font-medium">
                            {{ $kpis['monthly_revenue']['trend'] === 'up' ? '+' : '' }}{{ $kpis['monthly_revenue']['growth'] }}%
                            from last month
                        </p>
                    </div>
                    <div class="bg-indigo-50 p-3 rounded-xl">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1">
                            </path>
                        </svg>
                    </div>
                </div>
            </x-card>

            <x-card
                class="border-l-4 border-indigo-500 shadow-sm ring-1 ring-indigo-50 hover:shadow-lg transition-shadow duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-indigo-500">Active Customers</p>
                        <p class="text-2xl font-bold text-indigo-950">{{ number_format($customer_analytics['active']) }}</p>
                        <p class="text-xs text-emerald-600 mt-1 font-medium">{{ $customer_analytics['new_this_month'] }} new
                            this month</p>
                    </div>
                    <div class="bg-indigo-50 p-3 rounded-xl">
                        <svg class="w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z">
                            </path>
                        </svg>
                    </div>
                </div>
            </x-card>

            <x-card
                class="border-l-4 border-sky-500 shadow-sm ring-1 ring-indigo-50 hover:shadow-lg transition-shadow duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-sky-600">Service Completion</p>
                        <p class="text-2xl font-bold text-sky-900">{{ $kpis['service_completion_rate']['rate'] }}%</p>
                        <p class="text-xs text-emerald-600 mt-1 font-medium">
                            {{ $kpis['service_completion_rate']['completed'] }} of
                            {{ $kpis['service_completion_rate']['total'] }} services
                        </p>
                    </div>
                    <div class="bg-sky-50 p-3 rounded-xl">
                        <svg class="w-6 h-6 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                </div>
            </x-card>

            <x-card
                class="border-l-4 border-purple-500 shadow-sm ring-1 ring-indigo-50 hover:shadow-lg transition-shadow duration-300">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-purple-600">Storage Utilization</p>
                        <p class="text-2xl font-bold text-purple-900">{{ $kpis['storage_utilization']['percentage'] }}%</p>
                        <p class="text-xs text-indigo-500 mt-1 font-medium">{{ $kpis['storage_utilization']['used'] }} of
                            {{ $kpis['storage_utilization']['total'] }} slots used
                        </p>
                    </div>
                    <div class="bg-purple-50 p-3 rounded-xl">
                        <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                            </path>
                        </svg>
                    </div>
                </div>
            </x-card>
        </div>

        <!-- Admin Sections -->
        <div class="grid grid-cols-1 gap-6">
            <!-- Management Tools / Quick Actions -->
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-indigo-100 p-6">
                <div class="flex justify-between items-center mb-6 border-b border-indigo-50 pb-2">
                    <h4 class="font-bold text-indigo-950">Quick Actions</h4>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <a href="{{ route('management.users.create') }}"
                        class="flex items-center justify-between w-full px-4 py-3 bg-white border border-indigo-200 text-indigo-700 rounded-lg text-sm font-semibold hover:bg-slate-50 transition-all shadow-sm">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z">
                                </path>
                            </svg>
                            Add New User
                        </span>
                        <span class="text-indigo-400">&rarr;</span>
                    </a>

                    <a href="{{ route('management.export') }}"
                        class="flex items-center justify-between w-full px-4 py-3 bg-white border border-indigo-200 text-indigo-700 rounded-lg text-sm font-semibold hover:bg-slate-50 transition-all shadow-sm">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            Export CRM Data
                        </span>
                        <span class="text-indigo-400">&rarr;</span>
                    </a>

                    <a href="{{ route('management.audit') }}"
                        class="flex items-center justify-between w-full px-4 py-3 bg-white border border-indigo-200 text-indigo-700 rounded-lg text-sm font-semibold hover:bg-slate-50 transition-all shadow-sm">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            View System Audit Logs
                        </span>
                        <span class="text-indigo-400">&rarr;</span>
                    </a>

                    <a href="{{ route('management.backup') }}"
                        class="flex items-center justify-between w-full px-4 py-3 bg-white border border-indigo-200 text-indigo-700 rounded-lg text-sm font-semibold hover:bg-slate-50 transition-all shadow-sm">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4">
                                </path>
                            </svg>
                            Download Full Backup
                        </span>
                        <span class="text-indigo-400">&rarr;</span>
                    </a>

                    <a href="{{ route('management.roles.index') }}"
                        class="flex items-center justify-between w-full px-4 py-3 bg-gradient-to-r from-purple-500 to-indigo-600 text-white rounded-lg text-sm font-semibold hover:from-purple-600 hover:to-indigo-700 transition-all shadow-sm">
                        <span class="flex items-center">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                </path>
                            </svg>
                            Roles & Permissions
                        </span>
                        <span class="text-white/70">&rarr;</span>
                    </a>


                </div>

            </div>

            <!-- User Management -->
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-indigo-100 p-6">
                <div class="flex justify-between items-center mb-6 border-b border-indigo-50 pb-2">
                    <h4 class="font-bold text-indigo-950">Staff & Users</h4>
                    <span
                        class="text-xs bg-indigo-50 text-indigo-700 px-2.5 py-1 rounded-full font-medium">{{ $users->count() }}
                        Users</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    User
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Role
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Joined
                                </th>
                                <th scope="col" class="relative px-6 py-3 text-right">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($users as $user)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div
                                                    class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-500 font-bold">
                                                    {{ substr($user->name, 0, 2) }}
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                                                <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-{{ $user->role === 'admin' ? 'purple' : ($user->role === 'manager' ? 'blue' : 'gray') }}-100 text-{{ $user->role === 'admin' ? 'purple' : ($user->role === 'manager' ? 'blue' : 'gray') }}-800">
                                            {{ ucfirst($user->role) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $user->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end space-x-3">
                                            {{-- View/Edit Button --}}
                                            <a href="{{ route('management.users.edit', $user) }}"
                                                class="text-indigo-600 hover:text-indigo-900" title="Edit User">
                                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>

                                            {{-- Delete Button --}}
                                            @if(auth()->id() !== $user->id)
                                                <form action="{{ route('management.users.destroy', $user) }}" method="POST"
                                                    onsubmit="return confirm('Are you sure you want to delete this user?');"
                                                    class="inline-block">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-400 hover:text-red-600"
                                                        title="Delete User">
                                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No
                                        users found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
```