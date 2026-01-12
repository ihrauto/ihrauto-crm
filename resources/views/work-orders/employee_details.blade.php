@extends('layouts.app')

@section('title', 'Employee Performance')

@section('content')
    <div class="space-y-6">
        <!-- Header & Filter -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <nav class="flex text-sm font-medium text-indigo-500 mb-2">
                    <a href="{{ route('work-orders.employee-stats') }}" class="hover:text-indigo-700">Employees</a>
                    <span class="mx-2">/</span>
                    <span class="text-indigo-900">{{ $user->name }}</span>
                </nav>
                <div class="flex items-center gap-4">
                    <div
                        class="w-12 h-12 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-xl shadow-md">
                        {{ substr($user->name, 0, 1) }}
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-indigo-900">{{ $user->name }}</h1>
                        <p class="text-sm text-indigo-500">{{ ucfirst($user->role) }} • {{ $user->email }}</p>
                    </div>
                </div>
            </div>

            <!-- Filter Form -->
            <form method="GET" action="{{ route('work-orders.employee-details', $user) }}"
                class="flex items-center gap-2 bg-white p-2 rounded-lg border border-indigo-100 shadow-sm">
                <select name="year" onchange="this.form.submit()"
                    class="border-0 bg-transparent text-sm font-bold text-gray-700 focus:ring-0 cursor-pointer hover:text-indigo-600">
                    @foreach(range(now()->year, now()->year - 2) as $y)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
                <span class="text-gray-300">|</span>
                <select name="month" onchange="this.form.submit()"
                    class="border-0 bg-transparent text-sm font-bold text-gray-700 focus:ring-0 cursor-pointer hover:text-indigo-600">
                    <option value="">Full Year</option>
                    @foreach(range(1, 12) as $m)
                        <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $m, 1)) }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-indigo-50 p-6">
                <p class="text-sm font-medium text-indigo-400 uppercase tracking-wider">Jobs Completed</p>
                <p class="text-3xl font-bold text-indigo-900 mt-2">{{ $totalJobs }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-indigo-50 p-6">
                <p class="text-sm font-medium text-indigo-400 uppercase tracking-wider">Total Hours</p>
                <p class="text-3xl font-bold text-indigo-900 mt-2">{{ $totalHours }} <span
                        class="text-sm font-normal text-gray-400">hrs</span></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-indigo-50 p-6">
                <p class="text-sm font-medium text-indigo-400 uppercase tracking-wider">Avg Time / Job</p>
                <p class="text-3xl font-bold text-indigo-900 mt-2">{{ $avgTime }} <span
                        class="text-sm font-normal text-gray-400">hrs</span></p>
            </div>
        </div>

        <!-- Work Log -->
        <div class="bg-white rounded-xl shadow-sm border border-indigo-50 overflow-hidden">
            <div class="p-6 border-b border-indigo-50">
                <h3 class="font-bold text-indigo-900">Work History</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-indigo-50">
                    <thead class="bg-indigo-50/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-indigo-500 uppercase tracking-wider">
                                Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-indigo-500 uppercase tracking-wider">
                                Work Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-indigo-500 uppercase tracking-wider">
                                Vehicle</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-indigo-500 uppercase tracking-wider">
                                Duration</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-indigo-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 bg-white">
                        @forelse($workOrders as $wo)
                            <tr class="hover:bg-indigo-50/30 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $wo->completed_at->format('M d, Y') }}
                                    <span class="block text-xs text-gray-400">{{ $wo->completed_at->format('H:i') }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-indigo-900">
                                    #{{ $wo->id }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ $wo->vehicle->make }} {{ $wo->vehicle->model }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-700">
                                    @if($wo->started_at && $wo->completed_at)
                                        {{ round($wo->started_at->diffInMinutes($wo->completed_at) / 60, 1) }} hrs
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <a href="{{ route('work-orders.show', $wo) }}"
                                        class="text-indigo-600 hover:text-indigo-900 font-medium">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-400 text-sm">No completed jobs found for
                                    this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection