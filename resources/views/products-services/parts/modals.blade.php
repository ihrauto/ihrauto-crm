<!-- Create Product Modal -->
<dialog id="createProductModal"
    class="modal m-auto p-0 rounded-xl shadow-xl backdrop:bg-indigo-950/50 w-full max-w-4xl border border-gray-100">
    <div class="bg-white px-8 pb-8 pt-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-black text-gray-900 tracking-tight">Add part manually</h3>
            <button type="button" onclick="document.getElementById('createProductModal').close()"
                class="text-gray-400 hover:text-gray-500">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form action="{{ route('products.store') }}" method="POST">
            @csrf
            <div class="grid grid-cols-4 gap-x-6 gap-y-6">
                <!-- Part Name -->
                <div class="col-span-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Part name *</label>
                    <input type="text" name="name" required
                        class="block w-full rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                </div>

                <!-- Quantity -->
                <div class="col-span-1">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Quantity *</label>
                    <input type="number" name="stock_quantity" value="1" required
                        class="block w-full rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                </div>

                <!-- Units -->
                <div class="col-span-1" x-data="{ open: false, selected: 'Units' }">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Units</label>
                    <div class="relative">
                        <input type="hidden" name="unit" x-model="selected">
                        <button type="button" @click="open = !open" @click.away="open = false"
                            class="flex w-full items-center justify-between rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6 text-left">
                            <span x-text="selected"></span>
                            <span class="pointer-events-none flex items-center">
                                <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor"
                                    aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                        clip-rule="evenodd" />
                                </svg>
                            </span>
                        </button>

                        <div x-show="open" x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                            class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-inset ring-gray-200 focus:outline-none sm:text-sm"
                            style="display: none;">
                            <template x-for="option in ['Units', 'Pieces', 'Liters', 'Sets']">
                                <div @click="selected = option; open = false"
                                    class="relative cursor-default select-none py-2 pl-3 pr-9 text-gray-900 hover:bg-indigo-600 hover:text-white cursor-pointer">
                                    <span class="block truncate"
                                        :class="{ 'font-semibold': selected === option, 'font-normal': selected !== option }"
                                        x-text="option"></span>
                                    <span x-show="selected === option"
                                        class="absolute inset-y-0 right-0 flex items-center pr-4 text-indigo-600 hover:text-white">
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd"
                                                d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Purchase Price -->
                <div class="col-span-1">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Purchase price per unit</label>
                    <input type="number" step="0.01" name="purchase_price"
                        class="block w-full rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                </div>

                <!-- Sales Price -->
                <div class="col-span-1">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Sales price per unit</label>
                    <input type="number" step="0.01" name="price" required
                        class="block w-full rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                </div>

                <!-- Part number (SKU) -->
                <div class="col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Part number</label>
                    <input type="text" name="sku"
                        class="block w-full rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                </div>

                <!-- Order Number -->
                <div class="col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Order number</label>
                    <input type="text" name="order_number"
                        class="block w-full rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                </div>

                <!-- Supplier -->
                <div class="col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Supplier</label>
                    <input type="text" name="supplier"
                        class="block w-full rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                </div>

                <!-- Status -->
                <div class="col-span-1" x-data="{ 
                    open: false, 
                    selected: 'in_stock',
                    options: [
                        { label: 'In stock', value: 'in_stock' },
                        { label: 'Out of stock', value: 'out_of_stock' },
                        { label: 'Ordered', value: 'ordered' }
                    ],
                    get label() { return this.options.find(o => o.value === this.selected)?.label }
                }">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Status</label>
                    <div class="relative">
                        <input type="hidden" name="status" x-model="selected">
                        <button type="button" @click="open = !open" @click.away="open = false"
                            class="flex w-full items-center justify-between rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6 text-left">
                            <span x-text="label"></span>
                            <span class="pointer-events-none flex items-center">
                                <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor"
                                    aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                        clip-rule="evenodd" />
                                </svg>
                            </span>
                        </button>

                        <div x-show="open" x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                            class="absolute z-10 bottom-full mb-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-inset ring-gray-200 focus:outline-none sm:text-sm"
                            style="display: none;">
                            <template x-for="option in options" :key="option.value">
                                <div @click="selected = option.value; open = false"
                                    class="relative cursor-default select-none py-2 pl-3 pr-9 text-gray-900 hover:bg-indigo-600 hover:text-white cursor-pointer">
                                    <span class="block truncate"
                                        :class="{ 'font-semibold': selected === option.value, 'font-normal': selected !== option.value }"
                                        x-text="option.label"></span>
                                    <span x-show="selected === option.value"
                                        class="absolute inset-y-0 right-0 flex items-center pr-4 text-indigo-600 hover:text-white">
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd"
                                                d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Stock Alert -->
                <div class="col-span-1">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Stock Alert</label>
                    <input type="number" name="min_stock_quantity" value="10" required
                        class="block w-full rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('createProductModal').close()"
                    class="rounded-lg px-5 py-2.5 text-sm font-semibold text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                <button type="submit"
                    class="rounded-lg bg-indigo-600 px-8 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-500 transition-colors">Save</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Edit Product Modal -->
<dialog id="editProductModal"
    class="modal m-auto p-0 rounded-xl shadow-xl backdrop:bg-indigo-950/50 w-full max-w-4xl border border-gray-100">
    <div class="bg-white px-8 pb-8 pt-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-black text-gray-900 tracking-tight">Edit part</h3>
            <button type="button" onclick="document.getElementById('editProductModal').close()"
                class="text-gray-400 hover:text-gray-500">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form id="editProductForm" method="POST">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-4 gap-x-6 gap-y-6">
                <!-- Part Name -->
                <div class="col-span-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Part name *</label>
                    <input type="text" name="name" id="edit_product_name" required
                        class="block w-full rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                </div>

                <!-- Quantity -->
                <div class="col-span-1">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Quantity *</label>
                    <input type="number" name="stock_quantity" id="edit_product_stock" required
                        class="block w-full rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                </div>

                <!-- Units -->
                <div class="col-span-1">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Units</label>
                    <select name="unit" id="edit_product_unit"
                        class="block w-full rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                        <option value="Units">Units</option>
                        <option value="Pieces">Pieces</option>
                        <option value="Liters">Liters</option>
                        <option value="Sets">Sets</option>
                    </select>
                </div>

                <!-- Purchase Price -->
                <div class="col-span-1">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Purchase price per unit</label>
                    <input type="number" step="0.01" name="purchase_price" id="edit_product_purchase_price"
                        class="block w-full rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                </div>

                <!-- Sales Price -->
                <div class="col-span-1">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Sales price per unit *</label>
                    <input type="number" step="0.01" name="price" id="edit_product_price" required
                        class="block w-full rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                </div>

                <!-- Part number (SKU) -->
                <div class="col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Part number</label>
                    <input type="text" name="sku" id="edit_product_sku"
                        class="block w-full rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                </div>

                <!-- Order Number -->
                <div class="col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Order number</label>
                    <input type="text" name="order_number" id="edit_product_order_number"
                        class="block w-full rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                </div>

                <!-- Supplier -->
                <div class="col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Supplier</label>
                    <input type="text" name="supplier" id="edit_product_supplier"
                        class="block w-full rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                </div>

                <!-- Status -->
                <div class="col-span-1">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Status</label>
                    <select name="status" id="edit_product_status"
                        class="block w-full rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                        <option value="in_stock">In stock</option>
                        <option value="out_of_stock">Out of stock</option>
                        <option value="ordered">Ordered</option>
                    </select>
                </div>

                <!-- Stock Alert -->
                <div class="col-span-1">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Stock Alert</label>
                    <input type="number" name="min_stock_quantity" id="edit_product_min_stock" required
                        class="block w-full rounded-md border-0 py-2.5 px-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('editProductModal').close()"
                    class="rounded-lg px-5 py-2.5 text-sm font-semibold text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                <button type="submit"
                    class="rounded-lg bg-indigo-600 px-8 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-500 transition-colors">Save</button>
            </div>
        </form>
    </div>
</dialog>

<script>
    function openEditProductModal(product) {
        document.getElementById('editProductForm').action = `/products/${product.id}`;
        document.getElementById('edit_product_name').value = product.name;
        document.getElementById('edit_product_sku').value = product.sku || '';
        document.getElementById('edit_product_price').value = product.price;
        document.getElementById('edit_product_stock').value = product.stock_quantity;
        document.getElementById('edit_product_min_stock').value = product.min_stock_quantity;
        document.getElementById('edit_product_unit').value = product.unit || 'Units';
        document.getElementById('edit_product_purchase_price').value = product.purchase_price || '';
        document.getElementById('edit_product_order_number').value = product.order_number || '';
        document.getElementById('edit_product_supplier').value = product.supplier || '';
        document.getElementById('edit_product_status').value = product.status || 'in_stock';
        document.getElementById('editProductModal').showModal();
    }
</script>


<!-- Import Parts Modal -->
<dialog id="importPartsModal"
    class="modal m-auto p-0 rounded-xl shadow-xl backdrop:bg-indigo-950/50 w-full max-w-lg border border-gray-100">
    <div class="bg-white px-8 pb-8 pt-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-black text-gray-900 tracking-tight">Import Parts via Excel</h3>
            <button type="button" onclick="document.getElementById('importPartsModal').close()"
                class="text-gray-400 hover:text-gray-500">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form action="{{ route('products.import') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="flex items-center justify-center w-full" x-data="{ fileName: null }">
                <label for="dropzone-file"
                    class="flex flex-col items-center justify-center w-full h-64 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100"
                    :class="{ 'border-green-400 bg-green-50': fileName }">

                    <!-- Default State -->
                    <div class="flex flex-col items-center justify-center pt-5 pb-6" x-show="!fileName">
                        <svg class="w-8 h-8 mb-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 20 16">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2" />
                        </svg>
                        <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">Click to upload</span> or drag
                            and drop</p>
                        <p class="text-xs text-gray-500">XLSX or CSV (MAX. 5MB)</p>
                    </div>

                    <!-- Success State -->
                    <div class="flex flex-col items-center justify-center pt-5 pb-6" x-show="fileName"
                        style="display: none;">
                        <svg class="w-12 h-12 mb-4 text-green-500" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="mb-2 text-sm font-semibold text-green-600">File Selected!</p>
                        <p class="text-xs text-gray-500" x-text="fileName"></p>
                    </div>

                    <input id="dropzone-file" type="file" name="file" class="hidden" accept=".xlsx,.xls,.csv"
                        @change="fileName = $event.target.files[0] ? $event.target.files[0].name : null" />
                </label>
            </div>

            <div class="mt-4 flex justify-between items-center text-sm">
                <a href="{{ route('products.import.template') }}"
                    class="text-indigo-600 hover:text-indigo-500 font-medium hover:underline">Download template</a>
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('importPartsModal').close()"
                    class="rounded-lg px-5 py-2.5 text-sm font-semibold text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                <button type="submit"
                    class="rounded-lg bg-indigo-600 px-8 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-500 transition-colors">Import
                    Parts</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Supplier Parts Modal -->
<dialog id="supplierPartsModal"
    class="modal m-auto p-0 rounded-xl shadow-xl backdrop:bg-indigo-950/50 w-full max-w-2xl border border-gray-100">
    <div class="bg-white px-8 pb-8 pt-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-black text-gray-900 tracking-tight">Add Parts from Supplier</h3>
            <button type="button" onclick="document.getElementById('supplierPartsModal').close()"
                class="text-gray-400 hover:text-gray-500">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form action="#" method="POST">
            @csrf
            <div class="space-y-6">
                <!-- Supplier Search -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Search Supplier</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input type="text" name="supplier_search" placeholder="e.g. Hostettler, Derendinger..."
                            class="block w-full rounded-md border-0 py-3 pl-10 pr-4 text-gray-900 bg-gray-50 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-sm sm:leading-6">
                    </div>
                </div>

                <!-- Manual Entry Option -->
                <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                    <h4 class="text-sm font-bold text-gray-900 mb-2">Or enter connection details</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">API Key</label>
                            <input type="text" name="api_key"
                                class="block w-full rounded-md border-0 py-2 px-3 text-gray-900 bg-white ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-xs">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Endpoint URL</label>
                            <input type="text" name="endpoint"
                                class="block w-full rounded-md border-0 py-2 px-3 text-gray-900 bg-white ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-indigo-600 sm:text-xs">
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('supplierPartsModal').close()"
                    class="rounded-lg px-5 py-2.5 text-sm font-semibold text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                <button type="submit"
                    class="rounded-lg bg-indigo-600 px-8 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-500 transition-colors">Connect
                    & Search</button>
            </div>
        </form>
    </div>
</dialog>