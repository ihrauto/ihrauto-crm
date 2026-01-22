@extends('layouts.app')

@section('title', 'Inventory & Services')

@section('content')
    <div class="sm:flex sm:items-center sm:justify-end">
        <div class="mt-4 flex sm:ml-4 sm:mt-0">
            @if($tab === 'parts')
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.away="open = false" type="button"
                        class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Add Parts
                        <svg class="-mr-0.5 h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd"
                                d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div x-show="open" x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="transform opacity-0 scale-95"
                        x-transition:enter-end="transform opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="transform opacity-100 scale-100"
                        x-transition:leave-end="transform opacity-0 scale-95"
                        class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-gray-200 focus:outline-none overflow-hidden"
                        style="display: none;">
                        <a href="#" @click.prevent="open = false; document.getElementById('createProductModal').showModal()"
                            class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 border-b border-gray-100">Manually</a>
                        <a href="#" @click.prevent="open = false; document.getElementById('supplierPartsModal').showModal()"
                            class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 border-b border-gray-100">Supplier</a>
                        <a href="#" @click.prevent="open = false; document.getElementById('importPartsModal').showModal()"
                            class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">Via Excel</a>
                    </div>
                </div>
            @else
                <button type="button" onclick="document.getElementById('createServiceModal').showModal()"
                    class="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Add Service
                </button>
            @endif
        </div>
    </div>

    <!-- Tabs with Search -->
    <div class="mt-6 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <a href="{{ route('products-services.index', ['tab' => 'parts']) }}"
                    class="{{ $tab === 'parts' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }} whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium">
                    Parts (Inventory)
                </a>
                <a href="{{ route('products-services.index', ['tab' => 'services']) }}"
                    class="{{ $tab === 'services' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }} whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium">
                    Services (Price List)
                </a>
            </nav>

            @if($tab === 'parts')
                <div class="flex items-center gap-3">
                    <form action="{{ route('products-services.index') }}" method="GET" class="relative">
                        <input type="hidden" name="tab" value="parts">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search parts..."
                            class="block w-64 rounded-lg border-0 py-2.5 pl-10 pr-16 text-indigo-900 bg-white shadow-sm ring-1 ring-inset ring-indigo-300 placeholder:text-indigo-400 focus:ring-2 focus:ring-inset focus:ring-indigo-500 sm:text-sm sm:leading-6">
                        @if(request('search'))
                            <a href="{{ route('products-services.index', ['tab' => 'parts']) }}"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-indigo-600 hover:text-indigo-700 font-medium">
                                Clear
                            </a>
                        @endif
                    </form>
            @endif
            </div>
        </div>

        <!-- Tab Content -->
        <div class="mt-8">
            @if($tab === 'parts')
                @include('products-services.parts.table')
                @include('products-services.parts.modals')
            @else
                @include('products-services.services.table')
                @include('products-services.services.modals')
            @endif
        </div>

@endsection