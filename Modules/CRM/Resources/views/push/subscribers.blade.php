@extends('layouts.admin')

@section('page-title', 'مشترکین پوش')

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-6xl">

    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">👥 مشترکین پوش</h1>
        <p class="text-xs text-gray-500 mt-1">چه کسی می‌تواند اعلان بگیرد و چه کسی نه. بدون مرورگر فعال، هیچ اعلانی نمی‌رسد.</p>
    </div>

    @include('crm::push._nav')

    <div class="grid grid-cols-2 gap-3">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border border-gray-100 dark:border-gray-700">
            <p class="text-xs text-gray-500">اعلان فعال دارند</p>
            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $with }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border-2 {{ $without > 0 ? 'border-amber-300 dark:border-amber-800' : 'border-gray-100 dark:border-gray-700' }}">
            <p class="text-xs text-gray-500">اعلان را فعال نکرده‌اند</p>
            <p class="text-2xl font-bold {{ $without > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-gray-100' }} mt-1">{{ $without }}</p>
            @if($without > 0)
                <p class="text-[11px] text-amber-600 dark:text-amber-400 mt-1">این افراد باید پیگیری شوند — اعلان به آن‌ها نمی‌رسد.</p>
            @endif
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-gray-50 dark:bg-gray-900/40 text-gray-500 border-b border-gray-200 dark:border-gray-700">
                <tr>
                    <th class="text-right p-3">تکنسین</th>
                    <th class="text-right p-3">موبایل</th>
                    <th class="text-right p-3">مرورگر فعال</th>
                    <th class="text-right p-3">آخرین ثبت</th>
                    <th class="text-right p-3">آخرین ارسال</th>
                    <th class="text-right p-3">وضعیت آخرین ارسال</th>
                    <th class="text-right p-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($rows as $row)
                    <tr class="{{ $row['active'] === 0 ? 'bg-amber-50/50 dark:bg-amber-900/10' : '' }} hover:bg-gray-50 dark:hover:bg-gray-700/40">
                        <td class="p-3 text-gray-900 dark:text-gray-100 font-medium">
                            {{ $row['technician']->firstname_tech ?: $row['technician']->first_name }}
                        </td>
                        <td class="p-3 text-gray-500" dir="ltr">{{ $row['technician']->mobile ?: '—' }}</td>
                        <td class="p-3">
                            @if($row['active'] > 0)
                                <span class="px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300">
                                    {{ $row['active'] }}
                                </span>
                                @if($row['total'] > $row['active'])
                                    <span class="text-[10px] text-gray-400">({{ $row['total'] - $row['active'] }} باطل‌شده)</span>
                                @endif
                            @else
                                <span class="px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">ندارد</span>
                            @endif
                        </td>
                        <td class="p-3 text-gray-500">
                            {{ $row['last_seen'] ? \App\Support\JalaliDate::toJalali(\Illuminate\Support\Carbon::parse($row['last_seen'])->toDateString()) : '—' }}
                        </td>
                        <td class="p-3 text-gray-500">
                            {{ $row['last_push'] ? \App\Support\JalaliDate::toJalali(\Illuminate\Support\Carbon::parse($row['last_push'])->toDateString()) : '—' }}
                        </td>
                        <td class="p-3 text-gray-600 dark:text-gray-300">
                            {{ $row['last_status'] ? ($statuses[$row['last_status']] ?? $row['last_status']) : '—' }}
                        </td>
                        <td class="p-3 text-left whitespace-nowrap">
                            <a href="{{ route('crm.push.subscribers.devices', $row['technician']->id) }}"
                               class="text-[11px] px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded">دستگاه‌ها</a>
                            @if($row['active'] > 0)
                                <form method="POST" action="{{ route('crm.push.subscribers.test', $row['technician']->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-[11px] px-2 py-1 bg-sky-100 hover:bg-sky-200 text-sky-700 rounded">
                                        ارسال آزمایشی
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-[11px] text-gray-400 leading-relaxed">
        «آخرین ثبت» یعنی آخرین باری که مرورگر خودش را معرفی کرد — نه آخرین ارسال ما.
        این دو عمداً جدا نگه داشته شده‌اند تا بشود فهمید چه کسی اپ را رها کرده است.
    </p>
</div>
@endsection
