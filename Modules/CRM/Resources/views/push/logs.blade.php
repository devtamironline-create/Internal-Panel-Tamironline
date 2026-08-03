@extends('layouts.admin')

@section('page-title', 'گزارش پوش')

@section('main')
<div class="p-4 md:p-6 space-y-4">

    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">📋 گزارش ارسال‌ها</h1>
        <p class="text-xs text-gray-500 mt-1">یک ردیف به ازای هر مرورگر — چون نجوا هزینه و وضعیت را برای هر توکن جدا گزارش می‌کند.</p>
    </div>

    @include('crm::push._nav')

    {{-- ─── فیلتر ─── --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 border border-gray-100 dark:border-gray-700">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">رویداد</label>
                <select name="event" class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    <option value="">همه</option>
                    <option value="manual" @selected(request('event') === 'manual')>ارسال دستی</option>
                    @foreach($events as $event)
                        <option value="{{ $event->value }}" @selected(request('event') === $event->value)>{{ $event->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">وضعیت</label>
                <select name="status" class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    <option value="">همه</option>
                    @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">تکنسین</label>
                <select name="technician_id" class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    <option value="">همه</option>
                    @foreach($technicians as $technician)
                        <option value="{{ $technician->id }}" @selected((string) request('technician_id') === (string) $technician->id)>
                            {{ $technician->firstname_tech ?: $technician->first_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">از تاریخ</label>
                <input type="text" name="from" value="{{ request('from') }}" placeholder="۱۴۰۵/۰۵/۰۱" dir="ltr"
                       class="jalali-datepicker w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">تا تاریخ</label>
                <input type="text" name="to" value="{{ request('to') }}" placeholder="۱۴۰۵/۰۵/۳۱" dir="ltr"
                       class="jalali-datepicker w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 text-xs px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold">فیلتر</button>
                <a href="{{ route('crm.push.logs') }}" class="text-xs px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">پاک</a>
            </div>
        </div>
    </form>

    {{-- ─── خلاصهٔ همین فیلتر ─── --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        @foreach([
            ['کل', $summary['count'], 'gray'],
            ['پذیرفته‌شده', $summary['ok'], 'emerald'],
            ['توکن نامعتبر', $summary['invalid'], 'amber'],
            ['ناموفق', $summary['failed'], 'rose'],
            ['هزینه', number_format($summary['cost']), 'indigo'],
        ] as [$label, $value, $tone])
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 border border-gray-100 dark:border-gray-700">
                <p class="text-[11px] text-gray-500">{{ $label }}</p>
                <p class="text-lg font-bold text-{{ $tone }}-600 dark:text-{{ $tone }}-400">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs text-gray-500">{{ number_format($logs->total()) }} ردیف</p>
        <div class="flex gap-2">
            <a href="{{ route('crm.push.logs.export', request()->query() + ['format' => 'xlsx']) }}"
               class="text-xs px-3 py-2 bg-emerald-100 hover:bg-emerald-200 text-emerald-700 rounded-lg">⬇ اکسل</a>
            <a href="{{ route('crm.push.logs.export', request()->query() + ['format' => 'csv']) }}"
               class="text-xs px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">⬇ CSV</a>
        </div>
    </div>

    {{-- ─── جدول ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-gray-50 dark:bg-gray-900/40 text-gray-500 border-b border-gray-200 dark:border-gray-700">
                <tr>
                    <th class="text-right p-3">تاریخ</th>
                    <th class="text-right p-3">تکنسین</th>
                    <th class="text-right p-3">رویداد</th>
                    <th class="text-right p-3">عنوان</th>
                    <th class="text-right p-3">وضعیت</th>
                    <th class="text-right p-3">هزینه</th>
                    <th class="text-right p-3">پیگیری</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($logs as $log)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                        <td class="p-3 whitespace-nowrap text-gray-500">
                            {{ \App\Support\JalaliDate::toJalali($log->created_at?->toDateString()) }}
                            <span class="text-gray-400">{{ $log->created_at?->format('H:i') }}</span>
                        </td>
                        <td class="p-3 text-gray-900 dark:text-gray-100">
                            {{ $log->technician->firstname_tech ?? $log->technician->first_name ?? '—' }}
                        </td>
                        <td class="p-3 text-gray-600 dark:text-gray-300">
                            @php $event = $log->event_key ? \Modules\CRM\Enums\PushEvent::tryFrom($log->event_key) : null; @endphp
                            {{ $event?->label() ?? ($log->event_key ?: 'ارسال دستی') }}
                        </td>
                        <td class="p-3 text-gray-700 dark:text-gray-200 max-w-xs truncate" title="{{ $log->body }}">{{ $log->title }}</td>
                        <td class="p-3">
                            @php
                                $tone = match($log->status) {
                                    'Sent', 'Scheduled' => 'emerald',
                                    'InvalidToken' => 'amber',
                                    'cancelled' => 'gray',
                                    default => 'rose',
                                };
                            @endphp
                            <span class="px-2 py-0.5 rounded bg-{{ $tone }}-100 dark:bg-{{ $tone }}-900/40 text-{{ $tone }}-700 dark:text-{{ $tone }}-300">
                                {{ $log->statusLabel() }}
                            </span>
                            @if($log->error_code)
                                <span class="text-[10px] text-rose-500 block mt-1">کد {{ $log->error_code }}</span>
                            @endif
                        </td>
                        <td class="p-3 text-gray-600 dark:text-gray-300">{{ $log->cost ? number_format($log->cost) : '—' }}</td>
                        <td class="p-3">
                            @if($log->request_id)
                                <form method="POST" action="{{ route('crm.push.logs.trace') }}">
                                    @csrf
                                    <input type="hidden" name="request_id" value="{{ $log->request_id }}">
                                    <button type="submit" class="text-[11px] px-2 py-1 bg-sky-100 hover:bg-sky-200 text-sky-700 rounded">
                                        پیگیری از نجوا
                                    </button>
                                </form>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-8 text-center text-gray-400">هیچ ارسالی با این فیلتر ثبت نشده.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
</div>
@endsection
