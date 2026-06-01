@php
    $current = $current ?? request()->route()?->getName();
    $items = [
        ['name' => 'tech.dashboard','label' => 'خانه',     'icon' => 'home'],
        ['name' => 'tech.wallet',   'label' => 'کیف‌پول',   'icon' => 'wallet'],
        ['name' => 'tech.orders',   'label' => 'سفارش‌ها', 'icon' => 'list',   'fab' => true],
        ['name' => 'tech.invoices', 'label' => 'فاکتورها', 'icon' => 'doc'],
        ['name' => 'tech.profile',  'label' => 'پروفایل',  'icon' => 'user'],
    ];
@endphp

{{-- ─────── ویجت شناور چت کارشناس (روی همهٔ صفحات تکنسین) ─────── --}}
<div class="fixed inset-x-0 max-w-[480px] mx-auto pointer-events-none z-40" style="bottom: 210px;">
    <a href="{{ route('tech.messages') }}"
       class="absolute pointer-events-auto flex flex-col items-center transition active:scale-95"
       style="left: 14px;"
       aria-label="چت با کارشناس">
        <div class="relative w-14 h-14 rounded-full flex items-center justify-center text-white
                    shadow-[0_10px_24px_-6px_rgba(16,185,129,0.55)] ring-4 ring-white/80"
             style="background: linear-gradient(135deg, #059669 0%, #0d9488 100%);">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4-.8L3 20l1-3.2A7.96 7.96 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            <span class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1 rounded-full text-white text-[10.5px] font-bold flex items-center justify-center ring-2 ring-white transition"
                  :class="$store.techNav.unread > 0 ? 'bg-rose-500' : 'bg-emerald-600'"
                  x-text="$store.techNav.unread > 99 ? '99+' : $store.techNav.unread"></span>
        </div>
        <span class="mt-1.5 text-[10px] font-bold text-gray-700 bg-white/90 backdrop-blur px-2 py-0.5 rounded-full whitespace-nowrap shadow-sm flex items-center gap-1">
            چت با کارشناس
            <span class="px-1.5 py-0.5 rounded-full text-white text-[9.5px] transition"
                  :class="$store.techNav.unread > 0 ? 'bg-rose-500' : 'bg-emerald-600'"
                  x-text="$store.techNav.unread > 99 ? '99+' : $store.techNav.unread"></span>
        </span>
    </a>
</div>

<nav class="fixed bottom-0 inset-x-0 max-w-[480px] mx-auto nav-safe z-30
            bg-white/70 backdrop-blur-xl backdrop-saturate-150
            border-t border-white/40 rounded-t-[28px]
            shadow-[0_-12px_32px_-8px_rgba(15,23,42,0.12)]">
    <div class="grid grid-cols-5 h-[74px] relative px-2">
        @foreach($items as $item)
            @php $isActive = $current === $item['name']; @endphp

            @if(!empty($item['fab']))
                {{-- Elevated center FAB --}}
                <a href="{{ route($item['name']) }}"
                   class="flex flex-col items-center justify-end pb-2 relative">
                    <div class="absolute -top-5 w-14 h-14 rounded-full flex items-center justify-center text-white
                                shadow-[0_10px_24px_-6px_rgba(29,78,216,0.55)]
                                ring-[5px] ring-white/80 backdrop-blur"
                         style="background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            @switch($item['icon'])
                                @case('list')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                    @break
                            @endswitch
                        </svg>
                    </div>
                    <span class="text-[11px] font-bold {{ $isActive ? 'text-brand-700' : 'text-gray-500' }} mt-9">{{ $item['label'] }}</span>
                </a>
            @else
                <a href="{{ route($item['name']) }}"
                   class="flex flex-col items-center justify-center gap-1 transition group relative">
                    <span class="flex items-center justify-center w-10 h-10 rounded-2xl transition relative
                                 {{ $isActive ? 'bg-brand-50 text-brand-700' : 'text-gray-400 group-active:bg-gray-100' }}">
                        <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" stroke-width="{{ $isActive ? '2.4' : '1.8' }}" viewBox="0 0 24 24"
                             style="width:1.4rem;height:1.4rem;">
                            @switch($item['icon'])
                                @case('home')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
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
                    </span>
                    <span class="text-[10.5px] {{ $isActive ? 'font-bold text-brand-700' : 'font-medium text-gray-500' }}">{{ $item['label'] }}</span>
                </a>
            @endif
        @endforeach
    </div>
</nav>

{{-- Polling برای badge ویجت چت — هر ۱۵ ثانیه. در صفحهٔ /tech/messages
     خود کنترلر پیام‌ها را خوانده می‌کند، پس عدد به ۰ بازمی‌گردد.
     همچنین درخواست permission نوتیفیکیشن مرورگر و push notification
     وقتی پیام جدید از پشتیبانی می‌رسد. --}}
<script>
document.addEventListener('alpine:init', () => {
    Alpine.store('techNav', { unread: 0, lastSeen: 0 });

    // درخواست permission نوتیفیکیشن — با کمی تاخیر تا مزاحم لحظهٔ ورود نباشد
    if ('Notification' in window && Notification.permission === 'default') {
        setTimeout(() => {
            try { Notification.requestPermission(); } catch (e) {}
        }, 4000);
    }

    function notifyNewMessage(count) {
        if (! ('Notification' in window) || Notification.permission !== 'granted') return;
        try {
            const n = new Notification('پیام جدید از پشتیبانی', {
                body: count > 1
                    ? 'شما ' + count + ' پیام جدید از پشتیبانی تعمیرآنلاین دارید.'
                    : 'پشتیبانی تعمیرآنلاین برای شما پیام فرستاده — روی ویجت چت کلیک کنید.',
                icon: '{{ asset('tech-pwa/icons/icon.svg') }}',
                badge: '{{ asset('tech-pwa/icons/icon.svg') }}',
                tag: 'tech-chat-message',
                renotify: true,
            });
            n.onclick = () => {
                window.focus();
                window.location.href = '{{ route('tech.messages') }}';
                n.close();
            };
        } catch (e) {}
    }

    async function refreshUnread() {
        try {
            const res = await fetch('{{ route('tech.messages.unread') }}', { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            const newUnread = parseInt(json.unread || 0, 10);
            const store = Alpine.store('techNav');

            // فقط وقتی تعداد نخوانده‌ها افزایش یافته (پیام جدید آمده) نوتیف بزن
            if (newUnread > store.lastSeen) {
                notifyNewMessage(newUnread - store.lastSeen);
            }
            store.lastSeen = newUnread;
            store.unread = newUnread;
        } catch (e) {}
    }
    refreshUnread();
    setInterval(refreshUnread, 15000);
});
</script>
