@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('content')
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-end">
            <a href="{{ route('management') }}"
                class="inline-flex items-center px-4 py-2 border border-indigo-200 text-indigo-600 rounded-lg text-sm font-medium hover:bg-indigo-50 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Management
            </a>
        </div>

        @if(session('success'))
            <div class="rounded-md bg-green-50 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Roles Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            @foreach($roles as $role)
                <div class="bg-white shadow-sm rounded-xl border border-indigo-100 overflow-hidden flex flex-col h-full">
                    <div class="bg-gradient-to-r from-indigo-600 to-indigo-800 px-4 py-3 shrink-0">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-white flex items-center uppercase tracking-wide">
                                <svg class="w-4 h-4 mr-2 opacity-75" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                {{ ucfirst($role->name) }}
                            </h3>
                            <span class="text-[10px] font-medium bg-white/20 text-white px-2 py-0.5 rounded-full">{{ $role->users->count() }} users</span>
                        </div>
                    </div>
                    
                    <form action="{{ route('management.roles.update', $role) }}" method="POST" class="p-4 flex-1 flex flex-col">
                        @csrf
                        @method('PUT')

                        <!-- Module Access -->
                        <div class="mb-4">
                            <h4 class="text-xs font-bold text-indigo-900 uppercase tracking-wider mb-2 border-b border-indigo-50 pb-1">Access</h4>
                            <div class="grid grid-cols-1 gap-1">
                                @foreach($permissions['access'] ?? [] as $permission)
                                    <label class="flex items-center space-x-2 text-xs text-gray-700 p-1.5 rounded hover:bg-indigo-50 transition-colors cursor-pointer group">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="{{ $permission->name }}"
                                               {{ $role->hasPermissionTo($permission->name) ? 'checked' : '' }}
                                               class="w-3.5 h-3.5 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="group-hover:text-indigo-700">{{ ucwords(str_replace(['access ', '-'], ['', ' '], $permission->name)) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <!-- Data Scope -->
                        <div class="mb-4">
                            <h4 class="text-xs font-bold text-indigo-900 uppercase tracking-wider mb-2 border-b border-indigo-50 pb-1">Visibility</h4>
                            <div class="grid grid-cols-1 gap-1">
                                @foreach($permissions['view'] ?? [] as $permission)
                                    <label class="flex items-center space-x-2 text-xs text-gray-700 p-1.5 rounded hover:bg-indigo-50 transition-colors cursor-pointer group">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="{{ $permission->name }}"
                                               {{ $role->hasPermissionTo($permission->name) ? 'checked' : '' }}
                                               class="w-3.5 h-3.5 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="group-hover:text-indigo-700">{{ ucwords(str_replace('view all ', '', $permission->name)) }} (All)</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="mb-4">
                            <h4 class="text-xs font-bold text-indigo-900 uppercase tracking-wider mb-2 border-b border-indigo-50 pb-1">Actions</h4>
                            <div class="grid grid-cols-1 gap-1">
                                @foreach($permissions['manage'] ?? [] as $permission)
                                    <label class="flex items-center space-x-2 text-xs text-gray-700 p-1.5 rounded hover:bg-indigo-50 transition-colors cursor-pointer group">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="{{ $permission->name }}"
                                               {{ $role->hasPermissionTo($permission->name) ? 'checked' : '' }}
                                               class="w-3.5 h-3.5 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="group-hover:text-indigo-700">{{ ucwords(str_replace('manage ', 'Manage ', $permission->name)) }}</span>
                                    </label>
                                @endforeach
                                @foreach($permissions['delete'] ?? [] as $permission)
                                    <label class="flex items-center space-x-2 text-xs text-red-700 p-1.5 rounded hover:bg-red-50 transition-colors cursor-pointer group">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="{{ $permission->name }}"
                                               {{ $role->hasPermissionTo($permission->name) ? 'checked' : '' }}
                                               class="w-3.5 h-3.5 rounded border-red-300 text-red-600 focus:ring-red-500">
                                        <span class="group-hover:text-red-800">{{ ucwords($permission->name) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-auto pt-4 border-t border-indigo-50">
                            <button type="submit"
                                class="w-full bg-indigo-600 text-white px-3 py-2 rounded-lg font-bold text-xs uppercase tracking-wide hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 transition-all shadow-sm">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endsection
