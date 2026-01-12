@extends('layouts.app')

@section('title', 'System Audit Logs')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">System Audit Logs</h1>
                <p class="mt-1 text-sm text-gray-500">Track and monitor all system activities and data changes.</p>
            </div>
            <a href="{{ route('management') }}" 
               class="group inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-500 transition-colors">
                <div class="w-6 h-6 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mr-2 group-hover:bg-indigo-100 transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7 7-7"></path>
                    </svg>
                </div>
                Back to Dashboard
            </a>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <dt class="text-sm font-medium text-gray-500 truncate">Total Activities (Today)</dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        {{ \App\Models\AuditLog::whereDate('created_at', today())->count() }}</dd>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <dt class="text-sm font-medium text-gray-500 truncate">Active Users (Today)</dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        {{ \App\Models\AuditLog::whereDate('created_at', today())->distinct('user_id')->count() }}</dd>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <dt class="text-sm font-medium text-gray-500 truncate">System Security Events</dt>
                    <dd class="mt-1 text-3xl font-semibold text-indigo-600">Secure</dd>
                </div>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="flex flex-col">
                <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                        <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            User</th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Action</th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Module / Item</th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Changes</th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Date & IP</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($logs as $log)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div
                                                        class="flex-shrink-0 h-8 w-8 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 font-bold text-xs uppercase">
                                                        {{ substr(optional($log->user)->name ?? 'System', 0, 2) }}
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            {{ optional($log->user)->name ?? 'System User' }}</div>
                                                        <div class="text-xs text-gray-500">{{ optional($log->user)->email }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    {{ $log->action === 'created' ? 'bg-green-100 text-green-800' : '' }}
                                                    {{ $log->action === 'updated' ? 'bg-blue-100 text-blue-800' : '' }}
                                                    {{ $log->action === 'deleted' ? 'bg-red-100 text-red-800' : '' }}">
                                                    {{ ucfirst($log->action) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span class="font-mono text-xs">{{ class_basename($log->model_type) }}</span>
                                                <span class="text-gray-400">#{{ $log->model_id }}</span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                @if($log->changes)
                                                    <details class="cursor-pointer group">
                                                        <summary class="text-indigo-600 text-xs hover:text-indigo-800 outline-none">
                                                            View Details</summary>
                                                        <pre
                                                            class="mt-2 text-xs bg-gray-50 p-2 rounded border overflow-x-auto max-w-xs">{{ json_encode($log->changes, JSON_PRETTY_PRINT) }}</pre>
                                                    </details>
                                                @else
                                                    <span class="text-gray-400 italic">No details</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <div>{{ $log->created_at->format('M j, Y H:i') }}</div>
                                                <div class="text-xs text-gray-400">{{ $log->ip_address }}</div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-6 py-10 text-center text-gray-500">
                                                No audit logs found. System activity will appear here.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
@endsection