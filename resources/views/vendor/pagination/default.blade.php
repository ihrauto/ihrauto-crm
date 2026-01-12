@if ($paginator->hasPages())
    <div class="flex items-center justify-between mt-6">
        {{-- Results Info --}}
        <div class="text-sm text-gray-500">
            Showing <span class="font-medium text-gray-700">{{ $paginator->firstItem() }}</span> to 
            <span class="font-medium text-gray-700">{{ $paginator->lastItem() }}</span> of 
            <span class="font-medium text-gray-700">{{ $paginator->total() }}</span> results
        </div>

        {{-- Pagination Controls --}}
        <div class="inline-flex items-center bg-white rounded-lg border border-gray-300 divide-x divide-gray-300 shadow-sm">
            {{-- Previous Button --}}
            @if ($paginator->onFirstPage())
                <span class="px-3 py-2 text-gray-400 cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" 
                   class="px-3 py-2 text-gray-600 hover:text-[#1A53F2] transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
            @endif

            {{-- Page Numbers --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <span class="px-4 py-2 text-sm text-gray-400">...</span>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="px-4 py-2 text-sm font-medium text-white bg-[#1A53F2]">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}" 
                               class="px-4 py-2 text-sm text-gray-600 hover:text-[#1A53F2] transition-colors">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next Button --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" 
                   class="px-3 py-2 text-gray-600 hover:text-[#1A53F2] transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            @else
                <span class="px-3 py-2 text-gray-400 cursor-not-allowed">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </span>
            @endif
        </div>
    </div>
@endif
