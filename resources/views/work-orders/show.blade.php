@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <nav class="flex text-sm font-medium text-indigo-500 mb-2">
                    <a href="{{ route('dashboard') }}" class="hover:text-indigo-700">Dashboard</a>
                    <span class="mx-2">/</span>
                    <a href="{{ route('work-orders.index') }}" class="hover:text-indigo-700">Work Orders</a>
                    <span class="mx-2">/</span>
                    <span class="text-indigo-900">WO #{{ $workOrder->id }}</span>
                </nav>
                <h1 class="text-2xl font-bold text-indigo-900">Work Order #{{ $workOrder->id }}</h1>
                <p class="text-sm text-indigo-500">Created {{ $workOrder->created_at->format('M d, Y H:i') }} • Assigned to
                    {{ $workOrder->technician ? $workOrder->technician->name : 'Unassigned' }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <span class="px-4 py-2 rounded-full text-sm font-bold {{ $workOrder->status_badge_color }}">
                    {{ $workOrder->status_label }}
                </span>

                <!-- Status Actions -->
                <form action="{{ route('work-orders.update', $workOrder) }}" method="POST">
                    @csrf
                    @method('PUT')

                    @if($workOrder->status === 'created')
                        <input type="hidden" name="status" value="in_progress">
                        <button type="submit"
                            class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium shadow-sm transition-colors">
                            Start Job
                        </button>
                    @elseif($workOrder->status === 'in_progress')
                        {{-- Mark Completed button triggers the main form submission via JS --}}
                        <button type="button" onclick="submitCompletion()"
                            class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium shadow-sm transition-colors">
                            Mark Completed
                        </button>
                    @endif
                </form>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-50 text-green-700 p-4 rounded-lg border border-green-200">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 text-red-700 p-4 rounded-lg border border-red-200">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- LEFT COLUMN: Scope & Context -->
            <div class="lg:col-span-1 flex flex-col h-full">

                <!-- Customer Card -->
                <div class="bg-white rounded-xl shadow-sm border border-indigo-50 p-6 flex-1 h-full flex flex-col">
                    <h3 class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-4">Customer & Vehicle</h3>

                    <!-- Customer Name -->
                    <div class="flex items-start gap-4 mb-5 flex-shrink-0">
                        <div
                            class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-lg flex-shrink-0">
                            {{ substr($workOrder->customer->name ?? '?', 0, 1) }}
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-indigo-900 leading-tight">
                                {{ $workOrder->customer->name ?? 'Unknown Customer' }}</h4>
                        </div>
                    </div>

                    <!-- Details List -->
                    <div class="flex-1 text-sm divide-y divide-gray-100">
                        <!-- Phone -->
                        <div class="flex py-2.5">
                            <span class="text-gray-400 w-28 flex-shrink-0">Phone:</span>
                            <span class="text-gray-700 font-medium">{{ $workOrder->customer->phone ?? '-' }}</span>
                        </div>

                        <!-- Mileage -->
                        <div class="flex py-2.5">
                            <span class="text-gray-400 w-28 flex-shrink-0">Mileage:</span>
                            <span
                                class="text-gray-700 font-medium">{{ $workOrder->vehicle->mileage ? number_format($workOrder->vehicle->mileage) . ' km' : '-' }}</span>
                        </div>

                        <!-- Color -->
                        <div class="flex items-center py-2.5">
                            <span class="text-gray-400 w-28 flex-shrink-0">Color:</span>
                            @if($workOrder->vehicle->color)
                                <span class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full border border-gray-200"
                                        style="background-color: {{ $workOrder->vehicle->color }}"></span>
                                    <span class="text-gray-700 font-medium">{{ ucfirst($workOrder->vehicle->color) }}</span>
                                </span>
                            @else
                                <span class="text-gray-700 font-medium">-</span>
                            @endif
                        </div>

                        <!-- Vehicle -->
                        <div class="flex py-2.5">
                            <span class="text-gray-400 w-28 flex-shrink-0">Vehicle:</span>
                            <span class="text-gray-700 font-medium">{{ $workOrder->vehicle->make ?? '' }}
                                {{ $workOrder->vehicle->model ?? '-' }}</span>
                        </div>

                        <!-- License Plate -->
                        <div class="flex items-center py-2.5">
                            <span class="text-gray-400 w-28 flex-shrink-0">License Plate:</span>
                            <span
                                class="px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded font-mono font-bold text-xs">{{ $workOrder->vehicle->license_plate ?? '-' }}</span>
                        </div>

                        <!-- VIN -->
                        <div class="flex py-2.5">
                            <span class="text-gray-400 w-28 flex-shrink-0">VIN:</span>
                            <span class="text-gray-700 font-medium font-mono">{{ $workOrder->vehicle->vin ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Scope Checklist moved to Job Sheet -->

                <!-- Billing Status (only for users with finance access) -->
                @can('access finance')
                    @if($workOrder->invoice)
                        <div class="bg-white rounded-xl shadow-sm border border-indigo-50 p-6 mt-6">
                            <h3 class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-4">Billing</h3>

                            <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg border border-green-100 mb-3">
                                <div>
                                    <p class="text-xs text-green-600 font-bold uppercase">Invoiced</p>
                                    <p class="text-sm font-bold text-green-900">{{ $workOrder->invoice->invoice_number }}</p>
                                </div>
                                <span
                                    class="px-2 py-1 bg-green-200 text-green-800 text-xs rounded-full font-bold uppercase">{{ $workOrder->invoice->status }}</span>
                            </div>
                            <div class="flex gap-2">
                                <a href="{{ route('finance.index', ['tab' => 'invoices']) }}"
                                    class="flex-1 text-center px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 shadow-sm">
                                    View List
                                </a>
                            </div>
                        </div>
                    @endif
                @endcan

            </div>

            <!-- RIGHT COLUMN: Execution -->
            <div class="lg:col-span-2 flex flex-col">
                <form action="{{ route('work-orders.update', $workOrder) }}" method="POST" id="execution-form"
                    class="flex-1 flex flex-col">
                    @csrf
                    @method('PUT')

                    <div class="bg-white rounded-xl shadow-sm border border-indigo-50 overflow-hidden flex-1 flex flex-col">
                        <div class="border-b border-indigo-50 px-6 py-4 flex items-center justify-between flex-shrink-0">
                            <h2 class="font-bold text-indigo-900">Technician Notes & Observations</h2>
                            <button type="submit"
                                class="text-sm bg-indigo-50 text-indigo-700 px-4 py-2 rounded-lg font-medium hover:bg-indigo-100 transition-colors">
                                Save Changes
                            </button>
                        </div>

                        <div class="p-6 space-y-8 flex-1">

                            <!-- Technician Notes -->
                            <div class="h-full flex flex-col">

                                <textarea name="technician_notes" rows="4"
                                    class="w-full rounded-lg border border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm flex-1 p-3"
                                    placeholder="Record measurements, observations, and work details here...">{{ $workOrder->technician_notes }}</textarea>
                            </div>



                        </div>
                    </div>
                </form>

                <!-- Photo Documentation Section -->
                <div class="bg-white rounded-xl shadow-sm border border-indigo-50 overflow-hidden mt-6">
                    <div class="border-b border-indigo-50 px-6 py-4 flex items-center justify-between">
                        <h2 class="font-bold text-indigo-900 flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                </path>
                            </svg>
                            Photo Documentation
                        </h2>
                        <button type="button" onclick="document.getElementById('upload-modal').classList.remove('hidden')"
                            class="text-sm bg-indigo-50 text-indigo-700 px-4 py-2 rounded-lg font-medium hover:bg-indigo-100 transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4">
                                </path>
                            </svg>
                            Upload Photo
                        </button>
                    </div>

                    <div class="p-6">
                        @php
                            $beforePhotos = $workOrder->photos->where('type', 'before');
                            $afterPhotos = $workOrder->photos->where('type', 'after');
                        @endphp

                        @if($workOrder->photos->count() > 0)
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Before Photos -->
                                <div>
                                    <h3
                                        class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <span class="w-2 h-2 bg-amber-400 rounded-full"></span>
                                        Before ({{ $beforePhotos->count() }})
                                    </h3>
                                    @if($beforePhotos->count() > 0)
                                        <div class="grid grid-cols-2 gap-3">
                                            @foreach($beforePhotos as $photo)
                                                <div class="relative group rounded-lg overflow-hidden border border-gray-200 shadow-sm">
                                                    <img src="{{ $photo->url }}" alt="{{ $photo->caption ?? 'Before photo' }}"
                                                        class="w-full h-32 object-cover">
                                                    <div
                                                        class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                                        <a href="{{ $photo->url }}" target="_blank"
                                                            class="p-2 bg-white rounded-full text-gray-700 hover:bg-gray-100">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                                </path>
                                                            </svg>
                                                        </a>
                                                        @if($photo->user_id === auth()->id() || auth()->user()->hasRole(['admin', 'owner']))
                                                            <form action="{{ route('work-orders.photos.destroy', [$workOrder, $photo]) }}"
                                                                method="POST" class="inline">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit"
                                                                    class="p-2 bg-red-500 rounded-full text-white hover:bg-red-600"
                                                                    onclick="return confirm('Delete this photo?')">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                        viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                                            stroke-width="2"
                                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                                        </path>
                                                                    </svg>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                    @if($photo->caption)
                                                        <div
                                                            class="absolute bottom-0 left-0 right-0 bg-black/60 text-white text-xs p-2 truncate">
                                                            {{ $photo->caption }}</div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-sm text-gray-400 italic">No before photos yet.</p>
                                    @endif
                                </div>

                                <!-- After Photos -->
                                <div>
                                    <h3
                                        class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <span class="w-2 h-2 bg-green-400 rounded-full"></span>
                                        After ({{ $afterPhotos->count() }})
                                    </h3>
                                    @if($afterPhotos->count() > 0)
                                        <div class="grid grid-cols-2 gap-3">
                                            @foreach($afterPhotos as $photo)
                                                <div class="relative group rounded-lg overflow-hidden border border-gray-200 shadow-sm">
                                                    <img src="{{ $photo->url }}" alt="{{ $photo->caption ?? 'After photo' }}"
                                                        class="w-full h-32 object-cover">
                                                    <div
                                                        class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                                        <a href="{{ $photo->url }}" target="_blank"
                                                            class="p-2 bg-white rounded-full text-gray-700 hover:bg-gray-100">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                                </path>
                                                            </svg>
                                                        </a>
                                                        @if($photo->user_id === auth()->id() || auth()->user()->hasRole(['admin', 'owner']))
                                                            <form action="{{ route('work-orders.photos.destroy', [$workOrder, $photo]) }}"
                                                                method="POST" class="inline">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit"
                                                                    class="p-2 bg-red-500 rounded-full text-white hover:bg-red-600"
                                                                    onclick="return confirm('Delete this photo?')">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                        viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                                            stroke-width="2"
                                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                                        </path>
                                                                    </svg>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                    @if($photo->caption)
                                                        <div
                                                            class="absolute bottom-0 left-0 right-0 bg-black/60 text-white text-xs p-2 truncate">
                                                            {{ $photo->caption }}</div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-sm text-gray-400 italic">No after photos yet.</p>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="text-center py-8">
                                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                    </path>
                                </svg>
                                <p class="text-gray-400 text-sm">No photos uploaded yet.</p>
                                <p class="text-gray-400 text-xs mt-1">Click "Upload Photo" to add before/after documentation.
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Upload Modal -->
                <div id="upload-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
                    <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
                        <div class="p-6 border-b border-gray-100">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-bold text-gray-900">Upload Photo</h3>
                                <button type="button"
                                    onclick="document.getElementById('upload-modal').classList.add('hidden')"
                                    class="text-gray-400 hover:text-gray-600">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <form action="{{ route('work-orders.photos.store', $workOrder) }}" method="POST"
                            enctype="multipart/form-data" class="p-6 space-y-5">
                            @csrf

                            <!-- Photo Type Selection -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Photo Type</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="cursor-pointer relative">
                                        <input type="radio" name="type" value="before" checked class="sr-only" onchange="updatePhotoType(this)">
                                        <div id="type-before-ui" class="text-center py-3 px-3 rounded-xl border border-indigo-500 bg-indigo-50 text-indigo-700 transition-all shadow-sm">
                                            <div class="font-bold text-sm">Before</div>
                                        </div>
                                    </label>
                                    <label class="cursor-pointer relative">
                                        <input type="radio" name="type" value="after" class="sr-only" onchange="updatePhotoType(this)">
                                        <div id="type-after-ui" class="text-center py-3 px-3 rounded-xl border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-all shadow-sm">
                                            <div class="font-bold text-sm">After</div>
                                        </div>
                                    </label>
                                </div>
                                <script>
                                    function updatePhotoType(input) {
                                        const beforeUi = document.getElementById('type-before-ui');
                                        const afterUi = document.getElementById('type-after-ui');
                                        
                                        // "Light Purple" Active State Classes
                                        const activeClass = "text-center py-3 px-3 rounded-xl border border-indigo-500 bg-indigo-50 text-indigo-700 transition-all shadow-sm";
                                        // Inactive State Classes
                                        const inactiveClass = "text-center py-3 px-3 rounded-xl border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-all shadow-sm";
                                        
                                        if (input.value === 'before') {
                                            beforeUi.className = activeClass;
                                            afterUi.className = inactiveClass;
                                        } else {
                                            beforeUi.className = inactiveClass;
                                            afterUi.className = activeClass;
                                        }
                                    }
                                </script>
                            </div>

                            <!-- Styled File Input -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Select Photo</label>
                                <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-xl cursor-pointer bg-white hover:bg-indigo-50 hover:border-indigo-300 transition-colors group">
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <svg class="w-8 h-8 mb-2 text-indigo-300 group-hover:text-indigo-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                        </svg>
                                        <p class="mb-1 text-sm text-gray-500 group-hover:text-indigo-600"><span class="font-semibold">Click to upload</span></p>
                                        <p class="text-xs text-gray-400">JPG, PNG, WebP (MAX. 5MB)</p>
                                    </div>
                                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required class="hidden" onchange="document.getElementById('file-name-display').textContent = this.files[0] ? this.files[0].name : ''">
                                </label>
                                <div id="file-name-display" class="text-xs text-center text-indigo-600 mt-2 font-medium h-4"></div>
                            </div>

                            <!-- Caption Input -->
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Caption</label>
                                <input type="text" name="caption" placeholder="Describe the photo..." maxlength="255"
                                    class="block w-full rounded-lg border border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm placeholder:text-gray-400 py-2.5 px-3">
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-3 pt-2">
                                <button type="button"
                                    onclick="document.getElementById('upload-modal').classList.add('hidden')"
                                    class="flex-1 px-4 py-2.5 border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 hover:text-gray-800 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit"
                                    class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 shadow-sm transition-colors shadow-indigo-200">
                                    Upload Photo
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Metadata Footer -->
                <div
                    class="mt-6 grid grid-cols-2 {{ $workOrder->checkin ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }} gap-4 flex-shrink-0">
                    <!-- Checkin Time (only for check-in jobs) -->
                    @if($workOrder->checkin)
                        <div class="bg-white p-4 rounded-lg shadow-sm border border-indigo-50">
                            <span class="block text-xs text-gray-400 uppercase">Check-in</span>
                            <span
                                class="block text-sm font-bold text-gray-700">{{ $workOrder->checkin->checkin_time->format('H:i') }}</span>
                            <span
                                class="block text-xs text-gray-400">{{ $workOrder->checkin->checkin_time->format('M d') }}</span>
                        </div>
                    @endif

                    <!-- Started Time -->
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-indigo-50">
                        <span class="block text-xs text-gray-400 uppercase">Started</span>
                        <span
                            class="block text-sm font-bold text-indigo-700">{{ $workOrder->started_at ? $workOrder->started_at->format('H:i') : '--:--' }}</span>
                        <span
                            class="block text-xs text-gray-400">{{ $workOrder->started_at ? $workOrder->started_at->format('M d') : '-' }}</span>
                    </div>

                    <!-- Completed Time -->
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-indigo-50">
                        <span class="block text-xs text-gray-400 uppercase">Completed</span>
                        <span
                            class="block text-sm font-bold text-green-700">{{ $workOrder->completed_at ? $workOrder->completed_at->format('H:i') : '--:--' }}</span>
                        <span
                            class="block text-xs text-gray-400">{{ $workOrder->completed_at ? $workOrder->completed_at->format('M d') : '-' }}</span>
                    </div>

                    <!-- Duration -->
                    <div class="bg-white p-4 rounded-lg shadow-sm border border-indigo-50">
                        <span class="block text-xs text-gray-400 uppercase">Total Duration</span>
                        <span class="block text-sm font-bold text-gray-900">
                            @if($workOrder->started_at && $workOrder->completed_at)
                                {{ $workOrder->started_at->diff($workOrder->completed_at)->format('%Hh %Im') }}
                            @else
                                -
                            @endif
                        </span>
                    </div>
                </div>

            </div>
        </div>

        <!-- View Details Button (Below Grid to allow Left Column to match Right Column height) -->
        @if($workOrder->status === 'completed')
            <div class="mt-6 flex justify-start">
                <a href="{{ route('work-orders.details', $workOrder) }}"
                    class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-6 rounded-lg text-sm font-bold shadow-sm transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                    View Details
                </a>
            </div>
        @endif
    </div>

    <script>
        function submitCompletion() {
            const form = document.getElementById('execution-form');
            if (!form) return;

            // Add hidden status input
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'status';
            input.value = 'completed';
            form.appendChild(input);

            form.submit();
        }

        function addServiceRow() {
            const tbody = document.getElementById('services-table-body');
            const noMsg = document.getElementById('no-services-msg');
            if (noMsg) noMsg.style.display = 'none';

            const index = Date.now();
            const row = `
                                                         <tr>
                                                            <td class="p-2">
                                                                <input type="text" name="service_tasks[${index}][name]"
                                                                    class="w-full border-0 bg-transparent focus:ring-0 p-2 text-sm font-medium"
                                                                    placeholder="Service Name" required>
                                                            </td>
                                                            <td class="p-2">
                                                                <input type="number" step="0.01" name="service_tasks[${index}][price]"
                                                                    class="w-full border-0 bg-transparent focus:ring-0 p-2 text-sm"
                                                                    placeholder="0.00">
                                                            </td>
                                                            <td class="p-2 text-center"><button type="button"
                                                                    onclick="this.closest('tr').remove()"
                                                                    class="text-red-400 hover:text-red-600">×</button></td>
                                                        </tr>
                                                    `;
            tbody.insertAdjacentHTML('beforeend', row);
        }

        function addPartRow(data = null) {
            const tbody = document.getElementById('parts-table-body');
            const noPartsMsg = document.getElementById('no-parts-msg');
            if (noPartsMsg) noPartsMsg.style.display = 'none';

            const index = Date.now();
            const name = data ? data.name : '';
            const productId = data ? data.id : '';

            const row = `
                                                                            <tr class="bg-indigo-50/30">
                                                                                <td class="p-2">
                                                                                     <input type="hidden" name="parts_used[${index}][product_id]" value="${productId}">
                                                                                    <input type="text" name="parts_used[${index}][name]" value="${name}" class="w-full border-0 bg-transparent focus:ring-0 p-2 text-sm" placeholder="Part Name" required>
                                                                                </td>
                                                                                <td class="p-2">
                                                                                    <input type="number" name="parts_used[${index}][qty]" value="1" class="w-full border-0 bg-transparent focus:ring-0 p-2 text-sm" placeholder="1">
                                                                                </td>
                                                                                <td class="p-2">
                                                                                    <input type="number" step="0.01" name="parts_used[${index}][price]" value="0.00" class="w-full border-0 bg-transparent focus:ring-0 p-2 text-sm" placeholder="0.00">
                                                                                </td>
                                                                                <td class="p-2 text-center">
                                                                                    <button type="button" onclick="this.closest('tr').remove()" class="text-red-500 hover:text-red-700 text-sm">Remove</button>
                                                                                </td>
                                                                            </tr>
                                                                        `;
            tbody.insertAdjacentHTML('beforeend', row);
        }

        // WO Inventory Search Logic
        const woSearchInput = document.getElementById('wo-inventory-search');
        const woResultsDiv = document.getElementById('wo-search-results');
        let woDebounceTimer;

        woSearchInput.addEventListener('input', function () {
            clearTimeout(woDebounceTimer);
            const query = this.value;

            if (query.length < 2) {
                woResultsDiv.classList.add('hidden');
                return;
            }

            woDebounceTimer = setTimeout(() => {
                fetch(`/api/products-services/search?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        woResultsDiv.innerHTML = '';
                        // Filter for products only? Or allow services too? Assuming parts for now.
                        const parts = data.filter(item => item.type === 'product');

                        if (parts.length > 0) {
                            woResultsDiv.classList.remove('hidden');
                            parts.forEach(item => {
                                const div = document.createElement('div');
                                div.className = 'px-4 py-2 hover:bg-indigo-50 cursor-pointer text-sm border-b border-gray-50 last:border-0';
                                div.textContent = item.label;
                                div.onclick = () => {
                                    addPartRow({
                                        id: item.id,
                                        name: item.name
                                    });
                                    woSearchInput.value = '';
                                    woResultsDiv.classList.add('hidden');
                                };
                                woResultsDiv.appendChild(div);
                            });
                        } else {
                            woResultsDiv.classList.add('hidden');
                        }
                    });
            }, 300);
        });

        // Close results when clicking outside
        document.addEventListener('click', function (e) {
            if (!woSearchInput.contains(e.target) && !woResultsDiv.contains(e.target)) {
                woResultsDiv.classList.add('hidden');
            }
        });
    </script>
@endsection