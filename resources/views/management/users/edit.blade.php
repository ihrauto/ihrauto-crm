@extends('layouts.app')

@section('title', 'Edit Staff Member')

@section('content')
    <div class="space-y-6">
        <!-- Breadcrumbs -->
        <nav class="text-sm font-medium text-indigo-900/60">
            <a href="{{ route('management') }}" class="hover:text-indigo-600 transition-colors">Management</a>
            <span class="mx-2 text-indigo-300">/</span>
            <span class="text-indigo-600">Edit Staff Member</span>
        </nav>

        <!-- Header -->
        <div class="flex items-center space-x-4">
            <a href="{{ route('management') }}" class="p-2 rounded-lg text-indigo-600 hover:bg-indigo-50 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="text-2xl font-bold text-indigo-950 tracking-tight">Edit Staff Member</h1>
        </div>

        <!-- Form -->
        <x-card class="border border-indigo-100 shadow-lg ring-1 ring-indigo-50/50">
            <form action="{{ route('management.users.update', $user) }}" method="POST" class="space-y-8">
                @csrf
                @method('PUT')

                @if ($errors->any())
                    <div class="rounded-md bg-red-50 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">There were problems with your submission:</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <ul class="list-disc pl-5 space-y-1">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- User Information -->
                <div>
                    <h3 class="text-lg font-bold text-indigo-900 mb-6 border-b border-indigo-50 pb-2">User Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="text-sm font-semibold text-indigo-900 mb-1.5 block">Full Name <span
                                    class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name" required value="{{ old('name', $user->name) }}"
                                class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900 placeholder-indigo-300 bg-white shadow-sm"
                                placeholder="e.g. John Doe">
                        </div>

                        <div>
                            <label for="email" class="text-sm font-semibold text-indigo-900 mb-1.5 block">Email Address
                                <span class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email" required value="{{ old('email', $user->email) }}"
                                class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900 placeholder-indigo-300 bg-white shadow-sm"
                                placeholder="john@example.com">
                        </div>

                        <div>
                            <label for="password"
                                class="text-sm font-semibold text-indigo-900 mb-1.5 block">Password</label>
                            <input type="password" name="password" id="password"
                                class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900 placeholder-indigo-300 bg-white shadow-sm"
                                placeholder="Leave blank to keep current password">
                        </div>

                        <div>
                            <label for="role" class="text-sm font-semibold text-indigo-900 mb-1.5 block">Role / Position
                                <span class="text-red-500">*</span></label>
                            <select id="role" name="role"
                                class="w-full p-3 border border-indigo-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-indigo-900 bg-white shadow-sm">
                                @foreach($roles as $role)
                                    <option value="{{ $role->name }}" {{ old('role', $user->role) == $role->name ? 'selected' : '' }}>
                                        {{ ucfirst($role->name) }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-xs text-indigo-500">Roles determine what parts of the system this user can access.</p>
                        </div>

                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end space-x-3 pt-6 border-t border-indigo-50">
                    <a href="{{ route('management') }}"
                        class="px-5 py-2.5 border border-indigo-200 rounded-lg text-indigo-700 hover:bg-indigo-50 font-semibold transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                        class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg uppercase font-bold text-sm tracking-wide hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-300 transition-all shadow-md transform hover:-translate-y-0.5">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                        Update User
                    </button>
                </div>
            </form>
        </x-card>
    </div>
@endsection