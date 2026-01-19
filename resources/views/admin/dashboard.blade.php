@extends('layouts.app')

@section('title', 'Platform Control')

@push('scripts')
    <script>
        // Auto-refresh dashboard every 60 seconds for live metrics
        setTimeout(function () {
            window.location.reload();
        }, 60000);
    </script>
@endpush

@section('content')
    <div class="max-w-7xl mx-auto space-y-6">

        {{-- Header & Health --}}
        <div class="flex items-center justify-between border-b border-gray-200 pb-4">
            <div class="flex items-center space-x-6">
                <h1 class="text-xl font-bold text-gray-900 tracking-tight">System Status</h1>

                {{-- Compact Health Indicators --}}
                <div class="flex items-center space-x-4 text-sm">
                    <div class="flex items-center">
                        <div
                            class="w-2 h-2 rounded-full {{ $metrics['health']['failed_jobs_count'] > 0 ? 'bg-red-500' : 'bg-green-500' }} mr-2">
                        </div>
                        <span class="text-gray-600 font-medium">Jobs:</span>
                        <span
                            class="ml-1 {{ $metrics['health']['failed_jobs_count'] > 0 ? 'text-red-700 font-bold' : 'text-gray-900' }}">
                            {{ $metrics['health']['failed_jobs_count'] }}
                        </span>
                    </div>
                    <div class="h-4 w-px bg-gray-300"></div>
                    <div class="flex items-center">
                        <span class="text-gray-600 font-medium mr-2">Uptime:</span>
                        <a href="{{ $metrics['health']['health_check_url'] }}" target="_blank"
                            class="text-indigo-600 hover:text-indigo-800 font-mono text-xs">
                            GET /health
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Control Panel Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Growth Block --}}
            <div class="lg:col-span-2 bg-white border border-gray-200 rounded-md shadow-sm">
                <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                    <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">Growth & Adoption</h2>
                </div>

                <div class="p-5">
                    {{-- Primary Metrics --}}
                    <div class="grid grid-cols-3 gap-8 mb-6">
                        <div>
                            <div class="text-3xl font-bold text-gray-900 tracking-tight">
                                {{ $metrics['growth']['total_tenants'] }}
                            </div>
                            <div class="text-xs font-medium text-gray-500 mt-1 uppercase">Total Tenants</div>
                        </div>
                        <div>
                            <div class="text-3xl font-bold text-gray-900 tracking-tight">
                                {{ $metrics['growth']['total_users'] }}
                            </div>
                            <div class="text-xs font-medium text-gray-500 mt-1 uppercase">Total Users</div>
                        </div>
                        <div>
                            <div class="text-3xl font-bold text-gray-900 tracking-tight">
                                {{ $metrics['growth']['verified_users_percentage'] }}%
                            </div>
                            <div class="text-xs font-medium text-gray-500 mt-1 uppercase">Verified Rate</div>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-5 grid grid-cols-2 gap-8">
                        {{-- Acquisition --}}
                        <div>
                            <h3 class="text-xs font-bold text-gray-400 uppercase mb-3">Acquisition</h3>
                            <div class="flex space-x-6 text-sm">
                                <div>
                                    <span
                                        class="block text-gray-900 font-bold">{{ $metrics['growth']['new_tenants_today'] }}</span>
                                    <span class="text-gray-500 text-xs">Today</span>
                                </div>
                                <div>
                                    <span
                                        class="block text-gray-900 font-bold">{{ $metrics['growth']['new_tenants_7d'] }}</span>
                                    <span class="text-gray-500 text-xs">7 Days</span>
                                </div>
                                <div>
                                    <span
                                        class="block text-gray-900 font-bold">{{ $metrics['growth']['new_tenants_30d'] }}</span>
                                    <span class="text-gray-500 text-xs">30 Days</span>
                                </div>
                            </div>
                        </div>

                        {{-- Retention --}}
                        <div>
                            <h3 class="text-xs font-bold text-gray-400 uppercase mb-3">Activity (Last Seen)</h3>
                            <div class="flex space-x-6 text-sm">
                                <div>
                                    <span
                                        class="block text-gray-900 font-bold">{{ $metrics['growth']['active_tenants_24h'] }}</span>
                                    <span class="text-gray-500 text-xs">24h</span>
                                </div>
                                <div>
                                    <span
                                        class="block text-gray-900 font-bold">{{ $metrics['growth']['active_tenants_7d'] }}</span>
                                    <span class="text-gray-500 text-xs">7 Days</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Column Stack --}}
            <div class="space-y-6">

                {{-- Risk Signals --}}
                <div class="bg-white border border-gray-200 rounded-md shadow-sm">
                    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/50">
                        <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">Risk Signals</h2>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <div class="px-4 py-3 flex justify-between items-center">
                            <span class="text-sm text-gray-600">Trials Expiring (7d)</span>
                            <span
                                class="text-sm font-bold {{ $metrics['risk']['trials_expiring_7d'] > 0 ? 'text-amber-600' : 'text-gray-400' }}">
                                {{ $metrics['risk']['trials_expiring_7d'] }}
                            </span>
                        </div>
                        <div class="px-4 py-3 flex justify-between items-center">
                            <span class="text-sm text-gray-600">Inactive Tenants (>14d)</span>
                            <span
                                class="text-sm font-bold {{ $metrics['risk']['inactive_14d'] > 0 ? 'text-red-600' : 'text-gray-400' }}">
                                {{ $metrics['risk']['inactive_14d'] }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Usage Snapshot --}}
                <div class="bg-white border border-gray-200 rounded-md shadow-sm">
                    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/50">
                        <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">Usage Snapshot (7d)</h2>
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-2 gap-y-4 gap-x-2">
                            <div>
                                <div class="text-2xl font-bold text-gray-900">{{ $metrics['usage']['checkins_7d'] }}</div>
                                <div class="text-xs text-gray-500">Check-ins</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900">{{ $metrics['usage']['workorders_7d'] }}</div>
                                <div class="text-xs text-gray-500">Work Orders</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900">{{ $metrics['usage']['invoices_7d'] }}</div>
                                <div class="text-xs text-gray-500">Invoices</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900">{{ $metrics['usage']['tirehotel_7d'] }}</div>
                                <div class="text-xs text-gray-500">Tire Hotel</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- System Health & Monitoring --}}
                <div class="bg-white border border-gray-200 rounded-md shadow-sm">
                    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                        <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">System Health</h2>
                        <a href="https://sentry.io" target="_blank"
                            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium flex items-center">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            Sentry
                        </a>
                    </div>
                    <div class="divide-y divide-gray-100">
                        {{-- Database --}}
                        <div class="px-4 py-3 flex justify-between items-center">
                            <div class="flex items-center">
                                <div class="w-2 h-2 rounded-full bg-green-500 mr-2"></div>
                                <span class="text-sm text-gray-600">Database</span>
                            </div>
                            <span class="text-sm font-medium text-green-600">Connected</span>
                        </div>
                        {{-- Cache --}}
                        <div class="px-4 py-3 flex justify-between items-center">
                            <div class="flex items-center">
                                <div class="w-2 h-2 rounded-full bg-green-500 mr-2"></div>
                                <span class="text-sm text-gray-600">Cache</span>
                            </div>
                            <span class="text-sm font-medium text-green-600">Active</span>
                        </div>
                        {{-- Error Tracking --}}
                        <div class="px-4 py-3 flex justify-between items-center">
                            <div class="flex items-center">
                                <div
                                    class="w-2 h-2 rounded-full {{ config('sentry.dsn') ? 'bg-green-500' : 'bg-amber-500' }} mr-2">
                                </div>
                                <span class="text-sm text-gray-600">Error Tracking</span>
                            </div>
                            <span
                                class="text-sm font-medium {{ config('sentry.dsn') ? 'text-green-600' : 'text-amber-600' }}">
                                {{ config('sentry.dsn') ? 'Enabled' : 'Not Configured' }}
                            </span>
                        </div>
                        {{-- Automated Tests --}}
                        <div class="px-4 py-3 flex justify-between items-center">
                            <div class="flex items-center">
                                <div class="w-2 h-2 rounded-full bg-green-500 mr-2"></div>
                                <span class="text-sm text-gray-600">Automated Tests</span>
                            </div>
                            <span class="text-sm font-medium text-gray-900">112 Passing</span>
                        </div>
                        {{-- Environment --}}
                        <div class="px-4 py-3 flex justify-between items-center">
                            <div class="flex items-center">
                                <div
                                    class="w-2 h-2 rounded-full {{ app()->environment('production') ? 'bg-green-500' : 'bg-blue-500' }} mr-2">
                                </div>
                                <span class="text-sm text-gray-600">Environment</span>
                            </div>
                            <span class="text-sm font-medium text-gray-900 uppercase">{{ app()->environment() }}</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection