@props([])

<div id="edit-tire-modal"
    class="hidden fixed inset-0 bg-gray-900/60 backdrop-blur-sm overflow-y-auto h-full w-full z-50">
    <div
        class="relative top-20 mx-auto p-0 border-0 w-11/12 md:w-3/4 lg:w-1/2 shadow-2xl rounded-2xl bg-white overflow-hidden">
        <div class="bg-indigo-900 px-6 py-4 flex justify-between items-center">
            <h3 class="text-xl font-bold text-white">Edit Tire Details</h3>
            <button type="button" onclick="closeEditModal()" class="text-indigo-200 hover:text-white transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>
        <div class="p-6 md:p-8">
            <form id="edit-tire-form" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                <div id="edit-loading" class="text-center py-4">
                    <svg class="animate-spin h-8 w-8 text-indigo-600 mx-auto" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                        </circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    <p class="text-indigo-500 mt-2">Loading tire details...</p>
                </div>

                <div id="edit-fields" class="hidden space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-indigo-900 mb-1 block">Brand</label>
                            <input type="text" name="brand" id="edit-brand"
                                class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 shadow-sm ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-indigo-900 mb-1 block">Model</label>
                            <input type="text" name="model" id="edit-model"
                                class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 shadow-sm ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="text-sm font-medium text-indigo-900 mb-1 block">Size</label>
                            <input type="text" name="size" id="edit-size"
                                class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 shadow-sm ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-indigo-900 mb-1 block">Season</label>
                            <select name="season" id="edit-season"
                                class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 shadow-sm ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                                <option value="winter">Winter</option>
                                <option value="summer">Summer</option>
                                <option value="all_season">All Season</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-indigo-900 mb-1 block">Quantity</label>
                            <input type="number" name="quantity" id="edit-quantity" min="1" max="8"
                                class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 shadow-sm ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                        </div>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-indigo-900 mb-1 block">Storage Location</label>
                        <input type="text" name="storage_location" id="edit-location"
                            class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 shadow-sm ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                    </div>

                    <div>
                        <label class="text-sm font-medium text-indigo-900 mb-1 block">Status</label>
                        <select name="status" id="edit-status"
                            class="block w-full rounded-lg border-0 py-2 px-4 text-indigo-900 shadow-sm ring-1 ring-inset ring-indigo-200 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                            <option value="stored">Stored</option>
                            <option value="ready_pickup">Ready for Pickup</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="disposed">Disposed</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3 pt-6 border-t border-indigo-50">
                        <button type="button" onclick="closeEditModal()"
                            class="px-6 py-2.5 border border-indigo-200 text-indigo-600 rounded-lg text-sm font-semibold hover:bg-indigo-50 transition-colors">Cancel</button>
                        <button type="submit"
                            class="px-6 py-2.5 bg-indigo-900 text-white rounded-lg text-sm font-semibold hover:bg-indigo-800 shadow-md hover:shadow-lg transition-all">Update
                            Tire</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>