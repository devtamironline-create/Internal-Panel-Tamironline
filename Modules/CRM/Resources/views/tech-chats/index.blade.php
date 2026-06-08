@extends('layouts.admin')

@section('page-title', 'گفت‌وگو با تکنسین‌ها')

@section('main')
<div class="p-4 md:p-6 max-w-5xl mx-auto"
     x-data="techChatSearch()" x-init="init()">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">گفت‌وگو با تکنسین‌ها</h1>
        <div class="flex items-center gap-2">
            @if($canManage)
                <a href="{{ route('crm.tech-chats.assignments') }}"
                   class="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-medium">
                    تخصیص اپراتورها
                </a>
                <a href="{{ route('crm.tech-chats.index', ['all' => $showAll ? 0 : 1] + ($filter ? ['filter' => $filter] : [])) }}"
                   class="px-3 py-1.5 rounded-lg {{ $showAll ? 'bg-brand-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700' }} text-xs font-medium">
                    {{ $showAll ? 'فقط من' : 'همهٔ تکنسین‌ها' }}
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>
    @endif

    {{-- آمار — کلیک‌پذیر برای فیلتر --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
        <a href="{{ route('crm.tech-chats.index', ['filter' => 'unread'] + ($showAll ? ['all' => 1] : [])) }}"
           class="block bg-white dark:bg-gray-800 rounded-xl p-3 border {{ $filter === 'unread' ? 'border-rose-500 ring-2 ring-rose-200' : 'border-gray-200 dark:border-gray-700 hover:border-rose-400' }} transition">
            <div class="flex items-center justify-between">
                <div class="text-xs text-gray-500">پیام بی‌پاسخ</div>
                @if($stats['unread_messages'] > 0)
                    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-rose-500 text-white text-[10px] font-bold">
                        {{ $stats['unread_messages'] }}
                    </span>
                @endif
            </div>
            <div class="text-2xl font-bold text-rose-600 mt-1">{{ number_format($stats['unread']) }}</div>
            <div class="text-[10.5px] text-gray-400 mt-0.5">تکنسین منتظر پاسخ</div>
        </a>
        <a href="{{ route('crm.tech-chats.index', ['filter' => 'replied'] + ($showAll ? ['all' => 1] : [])) }}"
           class="block bg-white dark:bg-gray-800 rounded-xl p-3 border {{ $filter === 'replied' ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200 dark:border-gray-700 hover:border-blue-400' }} transition">
            <div class="text-xs text-gray-500">پاسخ‌داده‌شده</div>
            <div class="text-2xl font-bold text-blue-600 mt-1">{{ number_format($stats['replied']) }}</div>
            <div class="text-[10.5px] text-gray-400 mt-0.5">منتظر پیام تکنسین</div>
        </a>
        <a href="{{ route('crm.tech-chats.index', $showAll ? ['all' => 1] : []) }}"
           class="block bg-white dark:bg-gray-800 rounded-xl p-3 border {{ ! $filter ? 'border-gray-500 ring-2 ring-gray-200' : 'border-gray-200 dark:border-gray-700 hover:border-gray-400' }} transition">
            <div class="text-xs text-gray-500">جمع چت‌های جاری</div>
            <div class="text-2xl font-bold text-gray-700 mt-1">{{ number_format($stats['total']) }}</div>
            <div class="text-[10.5px] text-gray-400 mt-0.5">تکنسین با گفت‌وگوی فعال</div>
        </a>
    </div>

    {{-- باکس جست‌وجو برای شروع چت جدید --}}
    <div class="relative mb-4" @click.outside="results = []; q = ''">
        <svg class="w-4 h-4 absolute top-1/2 -translate-y-1/2 right-3 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="search" x-model="q" @input.debounce.250ms="doSearch()"
               placeholder="شروع چت جدید — جست‌وجوی نام یا موبایل تکنسین فعال…"
               class="w-full ps-9 pe-3 py-2.5 border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 rounded-xl text-sm focus:outline-none focus:border-brand-400">

        {{-- Dropdown نتایج --}}
        <div x-show="results.length > 0 || (q.length >= 2 && !loading && results.length === 0)" x-cloak
             class="absolute z-20 top-full mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg max-h-80 overflow-y-auto">
            <template x-for="r in results" :key="r.id">
                <a :href="r.url" class="flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700/40 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                    <div class="w-9 h-9 rounded-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center text-brand-700 dark:text-brand-300 font-bold text-sm"
                         x-text="r.name.substring(0, 1)"></div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate" x-text="r.name"></div>
                        <div class="text-[11px] text-gray-500" dir="ltr" x-text="r.mobile"></div>
                    </div>
                    <span class="text-[10px] text-brand-700 dark:text-brand-300">شروع چت ←</span>
                </a>
            </template>
            <div x-show="q.length >= 2 && !loading && results.length === 0"
                 class="p-4 text-center text-sm text-gray-500">
                تکنسین فعالی با این مشخصات پیدا نشد.
            </div>
        </div>
        <div x-show="loading" class="absolute z-20 top-full mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg p-3 text-center text-xs text-gray-400">
            در حال جست‌وجو…
        </div>
    </div>

    {{-- لیست چت‌های جاری --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow divide-y divide-gray-100 dark:divide-gray-700">
        @forelse($technicians as $tech)
            @php
                $name = trim(($tech->firstname_tech ?: ($tech->first_name . ' ' . ($tech->last_name ?? ''))));
                $name = $name !== '' ? $name : ('تکنسین #' . $tech->id);
            @endphp
            <a href="{{ route('crm.tech-chats.show', $tech) }}"
               class="flex items-center gap-3 p-4 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition {{ $tech->needs_reply ? 'bg-rose-50/40 dark:bg-rose-900/10' : '' }}">
                <div class="w-11 h-11 rounded-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center text-brand-700 dark:text-brand-300 font-bold">
                    {{ mb_substr($name, 0, 1) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="font-bold text-gray-900 dark:text-gray-100 truncate">{{ $name }}</div>
                            @if($tech->needs_reply)
                                <span class="inline-block px-1.5 py-0.5 text-[10px] font-bold rounded bg-rose-100 text-rose-700">نیاز به پاسخ</span>
                            @endif
                        </div>
                        <div class="text-[10.5px] text-gray-400 shrink-0 ms-2">
                            @if($tech->last_message_at) @jdatetime($tech->last_message_at) @else — @endif
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-0.5">
                        <div class="text-xs text-gray-500 truncate" dir="ltr">{{ $tech->mobile }}</div>
                        @if($tech->unread_count > 0)
                            <span class="ms-2 inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full bg-rose-500 text-white text-[10px] font-bold">
                                {{ $tech->unread_count }}
                            </span>
                        @endif
                    </div>
                    @if($showAll && $tech->operators->isNotEmpty())
                        <div class="text-[10.5px] text-gray-400 mt-0.5">
                            اپراتورها: {{ $tech->operators->map(fn($o) => trim($o->first_name . ' ' . $o->last_name))->implode('، ') }}
                        </div>
                    @elseif($showAll)
                        <div class="text-[10.5px] text-amber-500 mt-0.5">اپراتور تخصیص داده نشده</div>
                    @endif
                </div>
            </a>
        @empty
            <div class="p-10 text-center text-sm text-gray-500">
                @if($filter === 'unread')
                    هیچ پیام بی‌پاسخی وجود ندارد.
                @elseif($filter === 'replied')
                    چت پاسخ‌داده‌شده‌ای وجود ندارد.
                @else
                    هنوز چتی شروع نشده. برای شروع گفت‌وگو از باکس جست‌وجوی بالا استفاده کنید.
                @endif
            </div>
        @endforelse
    </div>
</div>

<script>
function techChatSearch() {
    return {
        q: '',
        results: [],
        loading: false,
        init() {},
        async doSearch() {
            if (this.q.trim().length < 2) {
                this.results = [];
                this.loading = false;
                return;
            }
            this.loading = true;
            try {
                const url = "{{ route('crm.tech-chats.search') }}?q=" + encodeURIComponent(this.q);
                const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const j = await r.json();
                this.results = j.results || [];
            } catch (e) {
                this.results = [];
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
@endsection
