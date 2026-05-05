@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $from = $paginator->firstItem();
        $to = $paginator->lastItem();
        $total = $paginator->total();
    @endphp

    <nav class="bg-white rounded-2xl shadow-sm p-3 flex items-center justify-between gap-2"
         role="navigation" aria-label="صفحه‌بندی">
        {{-- Previous (RTL: rightmost) --}}
        @if ($paginator->onFirstPage())
            <span class="w-10 h-10 rounded-xl bg-gray-50 text-gray-300 flex items-center justify-center"
                  aria-label="صفحه قبل" aria-disabled="true">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/>
                </svg>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}"
               class="w-10 h-10 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center active:bg-brand-100"
               aria-label="صفحه قبل" rel="prev">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/>
                </svg>
            </a>
        @endif

        {{-- Center info --}}
        <div class="flex-1 text-center">
            <div class="text-xs text-gray-700 font-medium">
                صفحه <span class="font-bold text-brand-700">{{ $current }}</span>
                <span class="text-gray-400">از</span>
                <span class="font-bold text-gray-800">{{ $last }}</span>
            </div>
            @if($total)
                <div class="text-[10px] text-gray-400 mt-0.5">
                    {{ number_format($from) }} تا {{ number_format($to) }} از مجموع {{ number_format($total) }}
                </div>
            @endif
        </div>

        {{-- Next (RTL: leftmost) --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}"
               class="w-10 h-10 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center active:bg-brand-100"
               aria-label="صفحه بعد" rel="next">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        @else
            <span class="w-10 h-10 rounded-xl bg-gray-50 text-gray-300 flex items-center justify-center"
                  aria-label="صفحه بعد" aria-disabled="true">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </span>
        @endif
    </nav>
@endif
