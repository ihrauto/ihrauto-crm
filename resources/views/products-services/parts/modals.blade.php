<!-- Create Product Modal -->
<dialog id="createProductModal"
    class="modal p-0 rounded-2xl shadow-xl backdrop:bg-indigo-950/50 w-full max-w-md border border-indigo-100">
    <div class="bg-indigo-50/30 px-6 pb-6 pt-6">
        <h3 class="text-xl font-bold text-indigo-950 mb-6">Add New Part</h3>
        <form action="{{ route('products.store') }}" method="POST">
            @csrf
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-indigo-900 mb-2">Name</label>
                    <input type="text" name="name" required
                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-indigo-900 mb-2">SKU (Optional)</label>
                    <input type="text" name="sku"
                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-indigo-900 mb-2">Selling Price (CHF)</label>
                    <input type="number" step="0.01" name="price" required
                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-indigo-900 mb-2">Initial Stock</label>
                        <input type="number" name="stock_quantity" value="0" required
                            class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-indigo-900 mb-2">Low Stock Alert</label>
                        <input type="number" name="min_stock_quantity" value="5" required
                            class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-indigo-900 mb-2">Description</label>
                    <textarea name="description" rows="2"
                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white"></textarea>
                </div>
            </div>
            <div class="mt-8 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('createProductModal').close()"
                    class="rounded-lg border border-indigo-200 px-5 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 transition-colors">Cancel</button>
                <button type="submit"
                    class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-colors">Save
                    Part</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Edit Product Modal -->
<dialog id="editProductModal"
    class="modal p-0 rounded-2xl shadow-xl backdrop:bg-indigo-950/50 w-full max-w-md border border-indigo-100">
    <div class="bg-indigo-50/30 px-6 pb-6 pt-6">
        <h3 class="text-xl font-bold text-indigo-950 mb-6">Edit Part</h3>
        <form id="editProductForm" method="POST">
            @csrf
            @method('PUT')
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-indigo-900 mb-2">Name</label>
                    <input type="text" name="name" id="edit_product_name" required
                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-indigo-900 mb-2">SKU</label>
                    <input type="text" name="sku" id="edit_product_sku"
                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-indigo-900 mb-2">Selling Price</label>
                    <input type="number" step="0.01" name="price" id="edit_product_price" required
                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-indigo-900 mb-2">Current Stock</label>
                        <input type="number" name="stock_quantity" id="edit_product_stock"
                            class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-indigo-900 mb-2">Low Stock Alert</label>
                        <input type="number" name="min_stock_quantity" id="edit_product_min_stock" required
                            class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-indigo-900 mb-2">Description</label>
                    <textarea name="description" id="edit_product_description" rows="2"
                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white"></textarea>
                </div>
            </div>
            <div class="mt-8 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('editProductModal').close()"
                    class="rounded-lg border border-indigo-200 px-5 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 transition-colors">Cancel</button>
                <button type="submit"
                    class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-colors">Update
                    Part</button>
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
        document.getElementById('edit_product_description').value = product.description || '';
        document.getElementById('editProductModal').showModal();
    }
</script>