@extends('layouts.admin')

@section('page-title', 'گفت‌وگو با تکنسین‌ها')

@section('main')
<div class="p-4 md:p-6 max-w-5xl mx-auto" x-data="{ q: '' }">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">گفت‌وگو با تکنسین‌ها</h1>
        <div class="flex items-center gap-2">
            @can('manage-technicians')
                <a href="{{ route('crm.tech-chats.assignments') }}"
                   class="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-medium">
                    تخصیص اپراتورها
                </a>
                <a href="{{ route('crm.tech-chats.index', ['all' => $showAll ? 0 : 1]) }}"
                   class="px-3 py-1.5 rounded-lg {{ $showAll ? 'bg-brand-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700' }} text-xs font-medium">
                    {{ $showAll ? 'فقط من' : 'همهٔ تکنسین‌ها' }}
                </a>
            @endcan
        </div>
    </div>

    {{-- جست‌وجو در لیست تکنسین‌ها --}}
    <div class="relative mb-4">
        <svg class="w-4 h-4 absolute top-1/2 -translate-y-1/2 right-3 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="search" x-model="q" placeholder="جست‌وجو در نام یا موبایل تکنسین…"
               class="w-full ps-9 pe-3 py-2.5 border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 rounded-xl text-sm focus:outline-none focus:border-brand-400">
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow divide-y divide-gray-100 dark:divide-gray-700">
        @forelse($technicians as $tech)
            @php
                $name = trim(($tech->firstname_tech ?: ($tech->first_name . ' ' . ($tech->last_name ?? ''))));
                $name = $name !== '' ? $name : ('تکنسین #' . $tech->id);
                $searchHaystack = mb_strtolower($name . ' ' . $tech->mobile);
            @endphp
            <a href="{{ route('crm.tech-chats.show', $tech) }}"
               class="flex items-center gap-3 p-4 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition"
               x-show="q === '' || @js($searchHaystack).includes(q.toLowerCase())">
                <div class="w-11 h-11 rounded-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center text-brand-700 dark:text-brand-300 font-bold">
                    {{ mb_substr($name, 0, 1) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                        <div class="font-bold text-gray-900 dark:text-gray-100 truncate">{{ $name }}</div>
                        <div class="text-[10.5px] text-gray-400">
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
                هیچ تکنسینی به شما تخصیص داده نشده است.
                @can('manage-technicians')
                    <br><a href="{{ route('crm.tech-chats.assignments') }}" class="text-brand-700 hover:underline mt-2 inline-block">مدیریت تخصیص اپراتورها</a>
                @endcan
            </div>
        @endforelse
    </div>
</div>
@endsection
