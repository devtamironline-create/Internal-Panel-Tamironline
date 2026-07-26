@extends('layouts.admin')

@section('page-title', 'بازوی سئو — لینک‌سازی')

@section('main')
@php use Modules\Seo\Support\Arm\ArmEnums; @endphp
<div class="p-6 space-y-4" dir="rtl">
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-white">🔗 فضای کاری لینک‌سازی</h1>
            <p class="text-sm text-gray-500 mt-1">لینک‌های داخلی، خارجی، PR دیجیتال و سایتیشن — هیچ لینکی به‌صورت خودکار ساخته نمی‌شود.</p>
        </div>
        <div class="flex gap-2 text-center text-xs">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2"><b class="text-lg block">{{ $stats['internal_planned'] }}</b>داخلی در برنامه</div>
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2"><b class="text-lg block">{{ $stats['external_active'] }}</b>خارجی در جریان</div>
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2"><b class="text-lg block text-emerald-600">{{ $stats['live'] }}</b>فعال</div>
        </div>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">{{ $errors->first() }}</div>@endif

    @can('seo-arm-manage')
        <details class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
            <summary class="p-3 text-sm font-bold text-gray-700 dark:text-gray-200 cursor-pointer">➕ افزودن آیتم لینک‌سازی</summary>
            <form method="POST" action="{{ route('seo.arm.links.store') }}" class="p-4 pt-0 grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                @csrf
                <label class="block text-xs text-gray-500">نوع
                    <select name="link_type" class="mt-1 w-full px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                        @foreach(ArmEnums::LINK_TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </label>
                <label class="block text-xs text-gray-500">آدرس مبدأ (برای لینک داخلی)
                    <input name="source_url" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm" dir="ltr" placeholder="/blog/...">
                </label>
                <label class="block text-xs text-gray-500">آدرس مقصد *
                    <input name="target_url" required class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm" dir="ltr" placeholder="/services/...">
                </label>
                <label class="block text-xs text-gray-500">انکرتکست
                    <input name="anchor_text" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                </label>
                <label class="block text-xs text-gray-500">دامنه (لینک خارجی)
                    <input name="domain" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm" dir="ltr">
                </label>
                <label class="block text-xs text-gray-500">تاریخ برنامه
                    <input type="date" name="planned_at" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm" dir="ltr">
                </label>
                <div class="md:col-span-3"><button class="px-6 py-2 bg-brand-600 text-white rounded-lg text-sm">ثبت</button></div>
            </form>
        </details>
    @endcan

    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 flex flex-wrap items-end gap-2 text-sm">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="انکر، دامنه یا URL…" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm w-48">
        <select name="link_type" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ انواع</option>
            @foreach(ArmEnums::LINK_TYPES as $k => $v)<option value="{{ $k }}" @selected($filters['link_type'] === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="status" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ وضعیت‌ها</option>
            @foreach(ArmEnums::LINK_STATUSES as $k => $v)<option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $v }}</option>@endforeach
        </select>
        <button class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm">اعمال</button>
        <a href="{{ route('seo.arm.links.index') }}" class="px-3 py-2 text-gray-500 text-sm">پاک‌کردن</a>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50 text-[11px] text-gray-500">
                    <tr>
                        <th class="p-3 text-right">لینک</th>
                        <th class="p-3">نوع</th>
                        <th class="p-3">صفحهٔ مقصد</th>
                        <th class="p-3">follow</th>
                        <th class="p-3">تاریخ برنامه</th>
                        <th class="p-3">وضعیت</th>
                        @can('seo-arm-manage')<th class="p-3"></th>@endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($links as $link)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="p-3">
                                <div class="font-medium text-gray-800 dark:text-gray-100 max-w-[220px] truncate">{{ $link->anchor_text ?: '—' }}</div>
                                <div class="text-[11px] text-gray-400 truncate" dir="ltr">{{ $link->source_url ? $link->source_url.' → ' : '' }}{{ $link->target_url }}</div>
                                @if($link->domain)<div class="text-[10px] text-gray-400" dir="ltr">{{ $link->domain }}</div>@endif
                            </td>
                            <td class="p-3 text-center text-xs text-gray-500">{{ $link->typeLabel() }}</td>
                            <td class="p-3 text-center text-xs text-gray-500 max-w-[160px] truncate">{{ $link->destinationPage?->title ?: '—' }}</td>
                            <td class="p-3 text-center text-xs" dir="ltr">{{ $link->follow_type }}</td>
                            <td class="p-3 text-center text-xs" dir="ltr">{{ $link->planned_at ? \Morilog\Jalali\Jalalian::fromCarbon($link->planned_at->copy())->format('Y/m/d') : '—' }}</td>
                            <td class="p-3 text-center">@include('seo::arm.partials._status', ['status' => $link->status, 'label' => $link->statusLabel()])</td>
                            @can('seo-arm-manage')
                                <td class="p-3 text-center">
                                    <form method="POST" action="{{ route('seo.arm.links.update', $link) }}">
                                        @csrf @method('PUT')
                                        <select name="status" onchange="this.form.submit()" class="text-[11px] px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                                            @foreach(ArmEnums::LINK_STATUSES as $k => $v)<option value="{{ $k }}" @selected($link->status === $k)>{{ $v }}</option>@endforeach
                                        </select>
                                    </form>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr><td colspan="7">@include('seo::arm.partials._empty', ['message' => 'آیتم لینک‌سازی پیدا نشد.', 'icon' => '🔗'])</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div>{{ $links->links() }}</div>
</div>
@endsection
