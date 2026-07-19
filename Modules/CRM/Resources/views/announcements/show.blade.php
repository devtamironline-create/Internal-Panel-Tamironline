@extends('layouts.admin')

@section('page-title', 'جزئیات اعلان')

@section('main')
<div class="space-y-4" x-data="{ filter: 'all', q: '' }">
    <div class="flex items-center justify-between gap-2">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">جزئیات اعلان</h1>
        <div class="flex items-center gap-2">
            <form method="POST" action="{{ route('crm.announcements.toggle', $announcement) }}">
                @csrf
                <button type="submit" class="px-3 py-2 rounded-lg text-sm font-medium
                        {{ $announcement->is_active ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' }}">
                    {{ $announcement->is_active ? 'غیرفعال کن' : 'فعال کن' }}
                </button>
            </form>
            <a href="{{ route('crm.announcements.index') }}" class="px-3 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">
                ← بازگشت
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    {{-- متن اعلان --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        <div class="flex items-center justify-between gap-2 mb-2">
            <h2 class="font-bold text-gray-900 dark:text-gray-100">{{ $announcement->title }}</h2>
            @if($announcement->is_active)
                <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-emerald-100 text-emerald-800">فعال</span>
            @else
                <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-gray-100 text-gray-600">غیرفعال</span>
            @endif
        </div>
        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-7">{{ $announcement->body }}</p>
        <div class="text-xs text-gray-400 mt-3 flex items-center gap-3 flex-wrap">
            <span>ثبت‌کننده: {{ trim(($announcement->creator?->first_name ?? '') . ' ' . ($announcement->creator?->last_name ?? '')) ?: '—' }}</span>
            <span dir="ltr">@jdatetime($announcement->created_at)</span>
        </div>
    </div>

    {{-- خلاصهٔ وضعیت تأیید — کارت آماری + نوار پیشرفت --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        <div class="grid grid-cols-3 gap-3 mb-4">
            <div class="text-center rounded-xl bg-emerald-50 dark:bg-emerald-900/20 py-3">
                <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-300 leading-none">{{ number_format($seenCount) }}</div>
                <div class="text-[11px] text-emerald-700/70 mt-1.5 font-medium">تأیید کرده</div>
            </div>
            <div class="text-center rounded-xl bg-amber-50 dark:bg-amber-900/20 py-3">
                <div class="text-2xl font-bold text-amber-700 dark:text-amber-300 leading-none">{{ number_format($pendingCount) }}</div>
                <div class="text-[11px] text-amber-700/70 mt-1.5 font-medium">در انتظار</div>
            </div>
            <div class="text-center rounded-xl bg-gray-50 dark:bg-gray-700/40 py-3">
                <div class="text-2xl font-bold text-gray-700 dark:text-gray-200 leading-none">{{ number_format($totalCount) }}</div>
                <div class="text-[11px] text-gray-500 mt-1.5 font-medium">کل تکنسین فعال</div>
            </div>
        </div>
        <div class="flex items-center justify-between text-xs text-gray-500 mb-1.5">
            <span>پیشرفت تأیید</span>
            <span class="font-bold text-gray-700 dark:text-gray-200">{{ $percent }}%</span>
        </div>
        <div class="w-full h-2.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all {{ $percent >= 100 ? 'bg-emerald-500' : ($percent >= 50 ? 'bg-brand-500' : 'bg-amber-500') }}"
                 style="width: {{ max($percent, 2) }}%"></div>
        </div>
    </div>

    {{-- فیلتر + جستجو + لیست --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="p-3 border-b border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
            <div class="flex items-center gap-1.5">
                <button type="button" @click="filter='all'"
                        :class="filter==='all' ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold">همه ({{ $totalCount }})</button>
                <button type="button" @click="filter='pending'"
                        :class="filter==='pending' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold">در انتظار ({{ $pendingCount }})</button>
                <button type="button" @click="filter='acked'"
                        :class="filter==='acked' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold">تأییدشده ({{ $seenCount }})</button>
            </div>
            <input type="search" x-model="q" placeholder="جستجوی نام یا موبایل..."
                   class="w-full sm:w-56 px-3 py-1.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-xs">
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="p-3 text-start">تکنسین</th>
                    <th class="p-3 text-start">موبایل</th>
                    <th class="p-3 text-start">وضعیت</th>
                    <th class="p-3 text-start">زمان تأیید</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($technicians as $t)
                    @php $ack = $acked->get($t->id); $status = $ack ? 'acked' : 'pending'; @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30"
                        data-status="{{ $status }}"
                        data-search="{{ mb_strtolower(trim($t->full_name.' '.$t->mobile)) }}"
                        x-show="(filter==='all' || filter===$el.dataset.status) && $el.dataset.search.includes(q.trim().toLowerCase())">
                        <td class="p-3 font-medium">{{ $t->full_name ?: '—' }}</td>
                        <td class="p-3 text-xs text-gray-500" dir="ltr">{{ $t->mobile ?: '—' }}</td>
                        <td class="p-3">
                            @if($ack)
                                <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-emerald-100 text-emerald-800">✓ متوجه شدم</span>
                            @else
                                <span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-amber-100 text-amber-800">هنوز ندیده</span>
                            @endif
                        </td>
                        <td class="p-3 text-xs text-gray-500" dir="ltr">
                            @if($ack && $ack->pivot->acknowledged_at)
                                @jdatetime($ack->pivot->acknowledged_at)
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
