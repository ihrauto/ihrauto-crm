<!-- Create Service Modal -->
<dialog id="createServiceModal"
    class="modal p-0 rounded-2xl shadow-xl backdrop:bg-indigo-950/50 w-full max-w-md border border-indigo-100">
    <div class="bg-indigo-50/30 px-6 pb-6 pt-6">
        <h3 class="text-xl font-bold text-indigo-950 mb-6">Add New Service</h3>
        <form action="{{ route('services.store') }}" method="POST">
            @csrf
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-indigo-900 mb-2">Service Name</label>
                    <input type="text" name="name" required
                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-indigo-900 mb-2">Code (Optional)</label>
                    <input type="text" name="code"
                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-indigo-900 mb-2">Selling Price (CHF)</label>
                    <input type="number" step="0.01" name="price" required
                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-indigo-900 mb-2">Description</label>
                    <textarea name="description" rows="2"
                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white"></textarea>
                </div>
            </div>
            <div class="mt-8 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('createServiceModal').close()"
                    class="rounded-lg border border-indigo-200 px-5 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 transition-colors">Cancel</button>
                <button type="submit"
                    class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-colors">Save
                    Service</button>
            </div>
        </form>
    </div>
</dialog>


<!-- Edit Service Modal -->
<dialog id="editServiceModal"
    class="modal p-0 rounded-2xl shadow-xl backdrop:bg-indigo-950/50 w-full max-w-md border border-indigo-100">
    <div class="bg-indigo-50/30 px-6 pb-6 pt-6">
        <h3 class="text-xl font-bold text-indigo-950 mb-6">Edit Service</h3>
        <form id="editServiceForm" method="POST">
            @csrf
            @method('PUT')
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-indigo-900 mb-2">Service Name</label>
                    <input type="text" name="name" id="edit_service_name" required
                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-indigo-900 mb-2">Code</label>
                    <input type="text" name="code" id="edit_service_code"
                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-indigo-900 mb-2">Selling Price</label>
                    <input type="number" step="0.01" name="price" id="edit_service_price" required
                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white">
                </div>
                <div>
                    <div class="relative flex items-start">
                        <div class="flex h-6 items-center">
                            <input id="edit_service_active" aria-describedby="active-description" name="is_active"
                                value="1" type="checkbox"
                                class="h-4 w-4 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-600">
                        </div>
                        <div class="ml-3 text-sm leading-6">
                            <label for="edit_service_active" class="font-medium text-indigo-900">Active</label>
                            <span id="active-description" class="text-indigo-500 block text-xs">If unchecked, this service
                                won't appear in selection lists.</span>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-indigo-900 mb-2">Description</label>
                    <textarea name="description" id="edit_service_description" rows="2"
                        class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 ring-1 ring-inset ring-indigo-200 placeholder:text-indigo-400 focus:ring-2 focus:ring-indigo-600 sm:text-sm bg-white"></textarea>
                </div>
            </div>
            <div class="mt-8 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('editServiceModal').close()"
                    class="rounded-lg border border-indigo-200 px-5 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 transition-colors">Cancel</button>
                <button type="submit"
                    class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-colors">Update
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