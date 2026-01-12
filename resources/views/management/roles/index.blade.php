@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('content')
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-end">
            <a href="{{ route('management') }}"
                class="group inline-flex items-center text-sm font-semibold text-indigo-600 hover:text-indigo-500 transition-colors">
                <div
                    class="w-6 h-6 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mr-2 group-hover:bg-indigo-100 transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7 7-7">
                        </path>
                    </svg>
                </div>
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
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @foreach($roles as $role)
                <div class="bg-white shadow-sm rounded-xl border border-indigo-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-600 to-indigo-800 px-6 py-4">
                        <h3 class="text-lg font-bold text-white flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                            {{ ucfirst($role->name) }}
                        </h3>
                        <p class="text-xs text-indigo-200 mt-1">{{ $role->users->count() }} users with this role</p>
                    </div>
                    
                    <form action="{{ route('management.roles.update', $role) }}" method="POST" class="p-6">
                        @csrf
                        @method('PUT')

                        <!-- Module Access -->
                        <div class="mb-6">
                            <h4 class="text-sm font-bold text-indigo-900 uppercase tracking-wider mb-3">Module Access</h4>
                            <div class="grid grid-cols-2 gap-3">
                                @foreach($permissions['access'] ?? [] as $permission)
                                    <label class="flex items-center space-x-2 text-sm text-gray-700 p-2 rounded-lg hover:bg-indigo-50 transition-colors cursor-pointer">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="{{ $permission->name }}"
                                               {{ $role->hasPermissionTo($permission->name) ? 'checked' : '' }}
                                               class="rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                                        <span>{{ ucwords(str_replace(['access ', '-'], ['', ' '], $permission->name)) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <!-- Data Scope -->
                        <div class="mb-6">
                            <h4 class="text-sm font-bold text-indigo-900 uppercase tracking-wider mb-3">Data Visibility</h4>
                            <p class="text-xs text-gray-500 mb-3">When unchecked, user sees only their own records.</p>
                            <div class="grid grid-cols-1 gap-3">
                                @foreach($permissions['view'] ?? [] as $permission)
                                    <label class="flex items-center space-x-2 text-sm text-gray-700 p-2 rounded-lg hover:bg-indigo-50 transition-colors cursor-pointer">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="{{ $permission->name }}"
                                               {{ $role->hasPermissionTo($permission->name) ? 'checked' : '' }}
                                               class="rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                                        <span>{{ ucwords(str_replace('view all ', '', $permission->name)) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="mb-6">
                            <h4 class="text-sm font-bold text-indigo-900 uppercase tracking-wider mb-3">Actions</h4>
                            <div class="grid grid-cols-2 gap-3">
                                @foreach($permissions['manage'] ?? [] as $permission)
                                    <label class="flex items-center space-x-2 text-sm text-gray-700 p-2 rounded-lg hover:bg-indigo-50 transition-colors cursor-pointer">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="{{ $permission->name }}"
                                               {{ $role->hasPermissionTo($permission->name) ? 'checked' : '' }}
                                               class="rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                                        <span>{{ ucwords(str_replace('manage ', '', $permission->name)) }}</span>
                                    </label>
                                @endforeach
                                @foreach($permissions['delete'] ?? [] as $permission)
                                    <label class="flex items-center space-x-2 text-sm text-red-700 p-2 rounded-lg hover:bg-red-50 transition-colors cursor-pointer">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="{{ $permission->name }}"
                                               {{ $role->hasPermissionTo($permission->name) ? 'checked' : '' }}
                                               class="rounded border-red-300 text-red-600 focus:ring-red-500">
                                        <span>{{ ucwords($permission->name) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="pt-4 border-t border-indigo-50">
                            <button type="submit"
                                class="w-full bg-indigo-600 text-white px-4 py-2.5 rounded-lg font-bold text-sm uppercase tracking-wide hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-300 transition-all">
                                Save Permissions
                            </button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endsection
