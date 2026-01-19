<div class="bg-white rounded-xl shadow-sm border border-indigo-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-indigo-50 bg-indigo-50/10">
        <div class="grid grid-cols-12 gap-4 text-xs font-bold text-gray-500 uppercase tracking-wide">
            <div class="col-span-5 md:col-span-4 pl-2">Part Details</div>
            <div class="col-span-3 md:col-span-3">Price</div>
            <div class="col-span-3 md:col-span-3">Stock Status</div>
            <div class="col-span-1 md:col-span-2 text-right pr-2">Actions</div>
        </div>
    </div>

    <div class="divide-y divide-gray-100">
        @forelse($products as $product)
            <div class="grid grid-cols-12 gap-4 px-6 py-4 items-center hover:bg-gray-50 transition-colors">
                <!-- Part Details -->
                <div class="col-span-5 md:col-span-4 pl-2">
                    <div class="font-bold text-indigo-950 text-sm">{{ $product->name }}</div>
                    <div class="text-indigo-500 text-xs mt-0.5 font-mono">SKU: {{ $product->sku ?? '-' }}</div>
                </div>

                <!-- Price -->
                <div class="col-span-3 md:col-span-3">
                    <div class="font-bold text-gray-900 text-sm">CHF {{ number_format($product->price, 2) }}</div>
                </div>

                <!-- Stock -->
                <div class="col-span-3 md:col-span-3">
                    <div class="flex items-center">
                        <span class="font-medium text-gray-700 text-sm mr-2">{{ $product->stock_quantity }} units</span>
                        @if($product->stock_quantity <= $product->min_stock_quantity)
                            <span
                                class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10">Low
                                Stock</span>
                        @else
                            <span
                                class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">In
                                Stock</span>
                        @endif
                    </div>
                </div>

                <!-- Actions -->
                <div class="col-span-1 md:col-span-2 flex justify-end pr-2">
                    <div class="flex items-center gap-2">
                        <button onclick="openEditProductModal({{ $product->toJson() }})"
                            class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                <path
                                    d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z" />
                                <path
                                    d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0010 3H4.75A2.75 2.75 0 002 5.75v9.5A2.75 2.75 0 004.75 18h9.5A2.75 2.75 0 0017 15.25V10a.75.75 0 00-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5z" />
                            </svg>
                        </button>
                        <form action="{{ route('products.destroy', $product) }}" method="POST" class="inline-block"
                            onsubmit="return confirm('Are you sure?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                                    class="w-5 h-5">
                                    <path fill-rule="evenodd"
                                        d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="p-12 text-center">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-indigo-50 mb-4">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <h3 class="text-sm font-medium text-gray-900">No parts found</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by adding a new part to your inventory.</p>
                <div class="mt-6">
                    <button type="button" onclick="document.getElementById('createProductModal').showModal()"
                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                clip-rule="evenodd" />
                        </svg>
                        Add Part
                    </button>
                </div>
            </div>
        @endforelse
    </div>
</div>