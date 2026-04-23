{{--
  Responsive table wrapper.

  Renders as a traditional table on tablet/desktop and switches to a vertical
  card stack on mobile (where column headers become labels for each row).

  USAGE:
    <x-responsive-table>
        <x-slot name="headers">
            <x-responsive-table-header>Name</x-responsive-table-header>
            <x-responsive-table-header>Email</x-responsive-table-header>
            <x-responsive-table-header>Status</x-responsive-table-header>
        </x-slot>

        @foreach($customers as $customer)
            <x-responsive-table-row>
                <x-responsive-table-cell label="Name">{{ $customer->name }}</x-responsive-table-cell>
                <x-responsive-table-cell label="Email">{{ $customer->email }}</x-responsive-table-cell>
                <x-responsive-table-cell label="Status">{{ $customer->status }}</x-responsive-table-cell>
            </x-responsive-table-row>
        @endforeach
    </x-responsive-table>

  DESIGN NOTES:
    - Uses CSS Grid on mobile for consistent spacing
    - `data-label` attribute comes from the cell label prop and is rendered
      by the sibling CSS rule (see below)
    - Horizontal scroll as a fallback for tables with many columns on tablet
--}}
@props([
    'caption' => null,
])

<div class="overflow-x-auto">
    <table {{ $attributes->merge(['class' => 'w-full responsive-table']) }}>
        @isset($caption)
            <caption class="sr-only">{{ $caption }}</caption>
        @endisset

        <thead class="hidden md:table-header-group bg-gray-50">
            <tr>{{ $headers }}</tr>
        </thead>

        <tbody class="divide-y divide-gray-200 md:divide-gray-100">
            {{ $slot }}
        </tbody>
    </table>
</div>

@once
    @push('styles')
        <style>
            @media (max-width: 767px) {
                .responsive-table,
                .responsive-table tbody,
                .responsive-table tr,
                .responsive-table td {
                    display: block;
                    width: 100%;
                }
                .responsive-table tr {
                    margin-bottom: 1rem;
                    border-radius: 0.75rem;
                    background: #fff;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                    padding: 1rem;
                    border: 1px solid #f3f4f6;
                }
                .responsive-table td {
                    padding: 0.5rem 0;
                    display: grid;
                    grid-template-columns: 40% 60%;
                    gap: 0.5rem;
                    align-items: center;
                    border: none;
                }
                .responsive-table td::before {
                    content: attr(data-label);
                    font-weight: 600;
                    font-size: 0.75rem;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    color: #6b7280;
                }
            }
        </style>
    @endpush
@endonce
