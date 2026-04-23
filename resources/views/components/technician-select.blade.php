{{-- Technician select dropdown with busy status indicators --}}
@props([
    'name' => 'technician_id',
    'required' => true,
    'users' => [],
    'busyIds' => [],
])

<div>
    <label class="text-sm font-medium text-indigo-900 mb-1 block">Assign Technician {{ $required ? '*' : '' }}</label>
    <select name="{{ $name }}" {{ $required ? 'required' : '' }}
        class="block w-full rounded-lg border-0 py-2 pl-4 pr-10 text-indigo-900 ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
        <option value="">Select Technician</option>
        @foreach($users as $user)
            @php $isBusy = in_array($user->id, $busyIds); @endphp
            <option value="{{ $user->id }}" {{ $isBusy ? 'disabled' : '' }}
                class="{{ $isBusy ? 'text-gray-400' : '' }}">
                {{ $user->name }}{{ $isBusy ? ' (Busy)' : '' }}
            </option>
        @endforeach
    </select>
</div>
