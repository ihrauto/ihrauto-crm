<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr>
                    <th scope="col"
                        class="py-3.5 pl-4 pr-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wide sm:pl-0">
                        Status</th>
                    <th scope="col"
                        class="px-3 py-3.5 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Service
                        Name <span class="block text-gray-400 font-normal normal-case">Code</span></th>
                    <th scope="col"
                        class="px-3 py-3.5 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">Price</th>
                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-0">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($services as $service)
                    <tr>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-0">
                            <form action="{{ route('services.toggle', $service) }}" method="POST">
                                @csrf
                                <button type="submit"
                                    class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium {{ $service->is_active ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20' : 'bg-gray-50 text-gray-600 ring-1 ring-inset ring-gray-500/10' }}">
                                    {{ $service->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900">
                            <div class="font-bold text-gray-900">{{ $service->name }}</div>
                            <div class="text-gray-500">{{ $service->code ?? '-' }}</div>
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm font-bold text-gray-900">
                            CHF {{ number_format($service->price, 2) }}
                        </td>
                        <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-0">
                            <div class="flex items-center justify-end gap-3 text-gray-400">
                                <button onclick="openEditServiceModal({{ $service->toJson() }})"
                                    class="hover:text-gray-600 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                                        class="w-5 h-5">
                                        <path
                                            d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z" />
                                        <path
                                            d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0010 3H4.75A2.75 2.75 0 002 5.75v9.5A2.75 2.75 0 004.75 18h9.5A2.75 2.75 0 0017 15.25V10a.75.75 0 00-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5z" />
                                    </svg>
                                </button>

                                <form action="{{ route('services.destroy', $service) }}" method="POST" class="inline-block"
                                    onsubmit="return confirm('Are you sure? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="hover:text-red-600 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                                            class="w-5 h-5">
                                            <path fill-rule="evenodd"
                                                d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-10 text-center text-sm text-gray-500">No services found. Add one to get
                            started.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>