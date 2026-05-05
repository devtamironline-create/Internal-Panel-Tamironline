@php
    $current = $current ?? request()->route()?->getName();
    $items = [
        ['name' => 'tech.profile',  'label' => 'پروفایل',  'icon' => 'user'],
        ['name' => 'tech.invoices', 'label' => 'فاکتورها', 'icon' => 'doc'],
        ['name' => 'tech.wallet',   'label' => 'کیف‌پول',   'icon' => 'wallet'],
        ['name' => 'tech.orders',   'label' => 'سفارش‌ها', 'icon' => 'list'],
        ['name' => 'tech.dashboard','label' => 'خانه',     'icon' => 'home'],
    ];
@endphp

<nav class="fixed bottom-0 inset-x-0 max-w-[480px] mx-auto bg-white border-t border-gray-100 nav-safe z-30">
    <div class="grid grid-cols-5 h-[68px]">
        @foreach($items as $item)
            @php $isActive = $current === $item['name']; @endphp
            <a href="{{ route($item['name']) }}"
               class="flex flex-col items-center justify-center gap-1 transition {{ $isActive ? 'text-brand-700' : 'text-gray-400' }}">
                <div class="relative">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="{{ $isActive ? '2.4' : '1.8' }}" viewBox="0 0 24 24">
                        @switch($item['icon'])
                            @case('home')
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                @break
                            @case('list')
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                @break
                            @case('wallet')
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M5 6h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2zm12 8h.01"/>
                                @break
                            @case('doc')
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                @break
                            @case('user')
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                @break
                        @endswitch
                    </svg>
                </div>
                <span class="text-[11px] {{ $isActive ? 'font-bold' : 'font-medium' }}">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
