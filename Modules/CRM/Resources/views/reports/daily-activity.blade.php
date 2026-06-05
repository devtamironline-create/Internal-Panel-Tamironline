@extends('layouts.admin')

@section('page-title', 'فعالیت روزانه')

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-7xl">

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">📅 فعالیت روزانه سفارش‌ها</h1>
            <p class="text-xs text-gray-500 mt-1">همه‌ی سفارش‌هایی که در روز انتخاب‌شده اتفاقی برایشان افتاده (ایجاد، تغییر وضعیت، فاکتور، کیف‌پول) — با هایلایت anomalyها.</p>
        </div>
        <form method="GET" class="flex items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">تاریخ</label>
                <input type="text" name="date" value="{{ $dateJ }}" dir="ltr" placeholder="1405/03/02" readonly
                       class="jalali-datepicker w-40 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg cursor-pointer bg-white text-sm">
            </div>
            <button class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">نمایش</button>
            <a href="{{ route('crm.reports.daily-activity') }}" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-sm">امروز</a>
        </form>
    </div>

    {{-- ─── باکس‌های جمع‌بندی ─── --}}
    @php
        $box = 'bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 border border-gray-100 dark:border-gray-700';
        $lbl = 'text-[10px] text-gray-500 uppercase tracking-wider';
        $val = 'text-lg font-bold mt-1';
        $anomTotal = array_sum($summary['anomalies']);
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="{{ $box }}">
            <div class="{{ $lbl }}">سفارش‌های دارای فعالیت</div>
            <div class="{{ $val }} text-gray-900 dark:text-gray-100">{{ number_format($summary['total_orders']) }}</div>
        </div>
        <div class="{{ $box }}">
            <div class="{{ $lbl }}">ایجاد شده امروز</div>
            <div class="{{ $val }} text-sky-600">{{ number_format($summary['created_today']) }}</div>
        </div>
        <div class="{{ $box }}">
            <div class="{{ $lbl }}">تغییر وضعیت</div>
            <div class="{{ $val }} text-indigo-600">{{ number_format($summary['status_changes']) }}</div>
        </div>
        <div class="{{ $box }}">
            <div class="{{ $lbl }}">فاکتور صادر شده</div>
            <div class="{{ $val }} text-emerald-600">{{ number_format($summary['invoices_issued']) }}</div>
        </div>
        <div class="{{ $box }} {{ $anomTotal > 0 ? 'border-rose-300 bg-rose-50 dark:bg-rose-900/20' : '' }}">
            <div class="{{ $lbl }}">⚠ هشدارها</div>
            <div class="{{ $val }} {{ $anomTotal > 0 ? 'text-rose-700' : 'text-gray-400' }}">{{ number_format($anomTotal) }}</div>
        </div>
    </div>

    @if($anomTotal > 0)
    <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 rounded-xl p-4 text-xs space-y-1">
        <div class="font-bold text-rose-700 mb-2">⚠ تفکیک هشدارها:</div>
        @if($summary['anomalies']['completed_no_invoice'] > 0)
            <div class="text-rose-700">• <b>{{ $summary['anomalies']['completed_no_invoice'] }}</b> سفارش Completed شد ولی فاکتور صادر نشد</div>
        @endif
        @if($summary['anomalies']['invoice_no_wallet'] > 0)
            <div class="text-rose-700">• <b>{{ $summary['anomalies']['invoice_no_wallet'] }}</b> فاکتور صادر شد ولی wallet tx ندارد</div>
        @endif
        @if($summary['anomalies']['status_regression'] > 0)
            <div class="text-rose-700">• <b>{{ $summary['anomalies']['status_regression'] }}</b> وضعیت برگشت کرد (مثلاً Completed → New)</div>
        @endif
        @if($summary['anomalies']['tech_no_wp_id'] > 0)
            <div class="text-rose-700">• <b>{{ $summary['anomalies']['tech_no_wp_id'] }}</b> تکنسین wp_id ندارد</div>
        @endif
    </div>
    @endif

    {{-- ─── لیست سفارش‌ها ─── --}}
    @if(empty($orders))
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-12 text-center text-gray-500">
            هیچ فعالیتی در این روز ثبت نشده.
        </div>
    @else
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach($orders as $r)
            @php
                $order = $r['order'];
                $events = $r['events'];
                $anomalies = $r['anomalies'];
                $hasAnom = ! empty($anomalies);
            @endphp
            <div class="p-4 {{ $hasAnom ? 'bg-rose-50 dark:bg-rose-900/10' : '' }}">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <a href="{{ route('crm.orders.show', $order->id) }}"
                               class="text-sm font-bold text-brand-600 hover:underline" dir="ltr" target="_blank">
                                {{ $order->order_code }}
                            </a>
                            <span class="px-2 py-0.5 text-[10px] font-medium rounded-full {{ $order->status?->badgeClass() ?? 'bg-gray-100' }}">
                                {{ $order->status?->label() ?? '—' }}
                            </span>
                            @foreach($anomalies as $a)
                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-rose-200 text-rose-800" title="{{ $a['msg'] }}">⚠ {{ $a['msg'] }}</span>
                            @endforeach
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-300 mt-1 flex flex-wrap gap-x-4 gap-y-1">
                            <span>مشتری: <b>{{ $order->customer?->first_name ?: '—' }}</b></span>
                            @if($order->customer?->mobile)
                                <span dir="ltr">{{ $order->customer->mobile }}</span>
                            @endif
                            <span>تکنسین: <b>{{ $order->technician ? trim($order->technician->firstname_tech ?: ($order->technician->first_name . ' ' . ($order->technician->last_name ?? ''))) : '— بدون تکنسین —' }}</b></span>
                        </div>
                    </div>
                    <div class="text-[10px] text-gray-400" dir="ltr">
                        آخرین فعالیت: {{ $r['last_event_at']->format('H:i:s') }}
                    </div>
                </div>

                {{-- timeline events --}}
                <div class="mt-3 space-y-1">
                    @foreach($events as $ev)
                    <div class="flex items-start gap-2 text-xs pl-4 border-r-2
                        @if($ev['kind'] === 'created') border-sky-400
                        @elseif($ev['kind'] === 'status') border-indigo-400
                        @elseif($ev['kind'] === 'invoice') border-emerald-400
                        @elseif($ev['kind'] === 'wallet') border-amber-400
                        @else border-gray-200 @endif pr-3 py-1">
                        <span class="text-base leading-tight">{{ $ev['icon'] }}</span>
                        <div class="flex-1">
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ $ev['label'] }}</span>
                            @if(! empty($ev['detail']))
                                <span class="text-gray-500"> — {{ $ev['detail'] }}</span>
                            @endif
                        </div>
                        <span class="text-[10px] text-gray-400" dir="ltr">{{ $ev['time']->format('H:i:s') }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
