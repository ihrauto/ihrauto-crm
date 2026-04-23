{{-- Photo upload component for vehicle condition photos --}}
@props([
    'name' => 'photos[]',
    'counterId' => 'photo-count',
    'label' => 'Vehicle Condition Photos (Before Service)',
])

<div {{ $attributes->merge(['class' => 'mt-6']) }}>
    <label class="block text-sm font-medium text-indigo-900 mb-2">{{ $label }}</label>
    <label
        class="flex flex-col items-center justify-center w-full h-32 border-2 border-indigo-200 border-dashed rounded-xl cursor-pointer bg-white hover:bg-indigo-50 hover:border-indigo-400 transition-colors group">
        <div class="flex flex-col items-center justify-center pt-5 pb-6">
            <svg class="w-8 h-8 mb-2 text-indigo-300 group-hover:text-indigo-500 transition-colors"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z">
                </path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 13a3 3 0 11-6 0 3 3 0 016 0z">
                </path>
            </svg>
            <p class="mb-1 text-sm text-gray-500 group-hover:text-indigo-700">Click to upload photos</p>
            <p class="text-xs text-gray-400" id="{{ $counterId }}">Max 5MB each</p>
        </div>
        <input type="file" name="{{ $name }}" multiple accept="image/*" class="hidden"
            onchange="document.getElementById('{{ $counterId }}').textContent = this.files.length + ' photo(s) selected'">
    </label>
</div>
