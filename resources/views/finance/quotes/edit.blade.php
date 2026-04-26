@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8" x-data='quoteForm(@json($quote->items->map(fn($i) => [
    "description" => $i->description,
    "quantity" => (int) $i->quantity,
    "unit_price" => (float) $i->unit_price,
    "tax_rate" => (float) $i->tax_rate,
])->values()))'>
    <x-flash-message />

    <h1 class="text-2xl font-semibold text-gray-900 mb-6">Edit quote {{ $quote->quote_number }}</h1>

    <form method="post" action="{{ route('quotes.update', $quote) }}" class="space-y-6">
        @csrf
        @method('PUT')
        <div class="bg-white shadow rounded-lg p-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                    <select name="customer_id" class="w-full rounded border-gray-300 text-sm">
                        @foreach ($customers as $c)
                            <option value="{{ $c->id }}" @selected($quote->customer_id == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Issue date</label>
                    <input type="date" name="issue_date" value="{{ old('issue_date', $quote->issue_date?->toDateString()) }}"
                           class="w-full rounded border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full rounded border-gray-300 text-sm">
                        @foreach (['draft','sent','accepted','rejected'] as $s)
                            <option value="{{ $s }}" @selected($quote->status === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="2" class="w-full rounded border-gray-300 text-sm">{{ old('notes', $quote->notes) }}</textarea>
            </div>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-medium">Line items</h2>
                <button type="button" @click="addItem" class="text-sm text-indigo-600 hover:underline">+ Add item</button>
            </div>

            <table class="min-w-full text-sm">
                <thead class="text-xs uppercase text-gray-500">
                    <tr>
                        <th class="py-2 text-left">Description</th>
                        <th class="py-2 text-right w-20">Qty</th>
                        <th class="py-2 text-right w-28">Unit</th>
                        <th class="py-2 text-right w-20">VAT %</th>
                        <th class="w-8"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(item, idx) in items" :key="idx">
                        <tr class="border-t border-gray-100">
                            <td><input type="text" :name="`items[${idx}][description]`" x-model="item.description"
                                       class="w-full rounded border-gray-300 text-sm"></td>
                            <td><input type="number" min="1" :name="`items[${idx}][quantity]`" x-model.number="item.quantity"
                                       class="w-full rounded border-gray-300 text-sm text-right"></td>
                            <td><input type="number" step="0.01" min="0" :name="`items[${idx}][unit_price]`" x-model.number="item.unit_price"
                                       class="w-full rounded border-gray-300 text-sm text-right"></td>
                            <td><input type="number" step="0.1" min="0" max="100" :name="`items[${idx}][tax_rate]`" x-model.number="item.tax_rate"
                                       class="w-full rounded border-gray-300 text-sm text-right"></td>
                            <td class="text-right">
                                <button type="button" @click="removeItem(idx)" class="text-red-500 hover:underline text-sm">&times;</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('quotes.show', $quote) }}" class="px-3 py-2 rounded bg-gray-100 text-sm">Cancel</a>
            <button type="submit" class="px-4 py-2 rounded bg-accent-500 text-white text-sm hover:bg-accent-600">Save changes</button>
        </div>
    </form>
</div>

<script>
function quoteForm(initial) {
    return {
        items: initial.length ? initial : [
            { description: '', quantity: 1, unit_price: 0, tax_rate: {{ (float) config('crm.tax_rate', 8.1) }} }
        ],
        addItem() {
            this.items.push({ description: '', quantity: 1, unit_price: 0, tax_rate: {{ (float) config('crm.tax_rate', 8.1) }} });
        },
        removeItem(idx) {
            if (this.items.length > 1) this.items.splice(idx, 1);
        }
    };
}
</script>
@endsection
