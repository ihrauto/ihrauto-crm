@extends('layouts.app')

@section('title', 'Inventory & Services')

@section('content')
    <div class="sm:flex sm:items-center sm:justify-end">
        <div class="mt-4 flex sm:ml-4 sm:mt-0">
            @if($tab === 'parts')
                <button type="button" onclick="document.getElementById('createProductModal').showModal()"
                    class="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Add Part (Inventory)
                </button>
            @else
                <button type="button" onclick="document.getElementById('createServiceModal').showModal()"
                    class="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                    Add Service
                </button>
            @endif
        </div>
    </div>

    <!-- Tabs -->
    <div class="mt-6 border-b border-gray-200">
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