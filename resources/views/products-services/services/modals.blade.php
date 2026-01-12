<!-- Create Service Modal -->
<dialog id="createServiceModal" class="modal p-0 rounded-lg shadow-xl backdrop:bg-gray-500/50 w-full max-w-md">
    <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
        <h3 class="text-lg font-semibold leading-6 text-gray-900 mb-4">Add New Service</h3>
        <form action="{{ route('services.store') }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Service Name</label>
                    <input type="text" name="name" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Code (Optional)</label>
                    <input type="text" name="code"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Selling Price (CHF)</label>
                    <input type="number" step="0.01" name="price" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" rows="2"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('createServiceModal').close()"
                    class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                <button type="submit"
                    class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save
                    Service</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Edit Service Modal -->
<dialog id="editServiceModal" class="modal p-0 rounded-lg shadow-xl backdrop:bg-gray-500/50 w-full max-w-md">
    <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
        <h3 class="text-lg font-semibold leading-6 text-gray-900 mb-4">Edit Service</h3>
        <form id="editServiceForm" method="POST">
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Service Name</label>
                    <input type="text" name="name" id="edit_service_name" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Code</label>
                    <input type="text" name="code" id="edit_service_code"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Selling Price</label>
                    <input type="number" step="0.01" name="price" id="edit_service_price" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <div class="relative flex items-start">
                        <div class="flex h-6 items-center">
                            <input id="edit_service_active" aria-describedby="active-description" name="is_active"
                                value="1" type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                        </div>
                        <div class="ml-3 text-sm leading-6">
                            <label for="edit_service_active" class="font-medium text-gray-900">Active</label>
                            <span id="active-description" class="text-gray-500 block text-xs">If unchecked, this service
                                won't appear in selection lists.</span>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" id="edit_service_description" rows="2"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('editServiceModal').close()"
                    class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                <button type="submit"
                    class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Update
                    Service</button>
            </div>
        </form>
    </div>
</dialog>

<script>
    function openEditServiceModal(service) {
        document.getElementById('editServiceForm').action = `/services/${service.id}`;
        document.getElementById('edit_service_name').value = service.name;
        document.getElementById('edit_service_code').value = service.code || '';
        document.getElementById('edit_service_price').value = service.price;
        document.getElementById('edit_service_description').value = service.description || '';
        document.getElementById('edit_service_active').checked = service.is_active;
        document.getElementById('editServiceModal').showModal();
    }
</script>