@extends('layouts.admin')

@section('page-title', 'سفارش‌های بدون فاکتور')

@php use Modules\CRM\Enums\OrderStatus; @endphp

@section('main')
<div class="p-4 md:p-6 max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">سفارش‌های بسته‌شده بدون فاکتور</h1>
            <p class="text-xs text-gray-500 mt-1">
                سفارش‌هایی که در <b>{{ $sinceDays }}</b> روز اخیر «انجام شده» شده‌اند ولی فاکتور فعالی برایشان ساخته نشده.
            </p>
        </div>
        <div class="flex items-center gap-2">
            @foreach([7, 14, 30, 60] as $d)
                <a href="{{ route('crm.orders.missing-invoices', ['days' => $d]) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-medium
                          {{ $sinceDays == $d ? 'bg-brand-600 text-white' : 'bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 text-gray-700' }}">
                    {{ $d }} روز
                </a>
            @endforeach
        </div>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm mb-4">{{ session('error') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
        @if($orders->isEmpty())
            <div class="p-10 text-center text-sm text-gray-500">
                ✅ هیچ سفارش بسته‌شده‌ای در این بازه بدون فاکتور نیست.
            </div>
        @else
            <div class="bg-amber-50 border-b border-amber-200 px-4 py-2 text-xs text-amber-900">
                ⚠ <b>{{ number_format($orders->total()) }}</b> سفارش بدون فاکتور پیدا شد.
                با کلیک روی «صدور فاکتور» در هر ردیف، فاکتور به‌صورت دستی صادر می‌شود
                (هم‌ارز فلوی خودکار: تراکنش commission و پیامک به مشتری هم ارسال می‌شود).
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-600 dark:text-gray-300">
                    <tr>
                        <th class="p-3 text-start">کد سفارش</th>
                        <th class="p-3 text-start">تکمیل</th>
                        <th class="p-3 text-start">مشتری</th>
                        <th class="p-3 text-start">دستگاه</th>
                        <th class="p-3 text-start">تکنسین</th>
                        <th class="p-3 text-start">مبلغ مشتری</th>
                        <th class="p-3 text-start w-32"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($orders as $o)
                        @php
                            $techName = trim(($o->technician?->firstname_tech ?: (($o->technician?->first_name ?? '') . ' ' . ($o->technician?->last_name ?? ''))));
                            $custName = $o->customerDisplayName();
                            $custMobile = $o->customer_mobile ?: $o->customer?->mobile;
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="p-3 font-bold" dir="ltr">
                                <a href="{{ route('crm.orders.show', $o) }}" target="_blank" class="text-brand-700 hover:underline">
                                    {{ $o->order_code }}
                                </a>
                            </td>
                            <td class="p-3 text-gray-500" dir="ltr">
                                @jdatetime($o->completed_at ?? $o->updated_at)
                                @if(! $o->completed_at)
                                    <div class="text-[10px] text-amber-600" dir="rtl">تاریخ تکمیل ثبت نشده — آخرین تغییر</div>
                                @endif
                            </td>
                            <td class="p-3">
                                <div class="font-medium">{{ $custName ?: '—' }}</div>
                                <div class="text-[10.5px] text-gray-400" dir="ltr">{{ $custMobile ?: '' }}</div>
                            </td>
                            <td class="p-3 text-xs">
                                {{ $o->device?->name ?: '—' }}
                                @if($o->brand) <span class="text-gray-400">/ {{ $o->brand->name }}</span> @endif
                            </td>
                            <td class="p-3 text-xs text-gray-600">{{ $techName ?: '—' }}</td>
                            <td class="p-3 text-xs font-medium">
                                {{ $o->price_customer ? number_format((int) $o->price_customer) . ' ت' : '—' }}
                            </td>
                            <td class="p-3">
                                @can('manage-crm-financial')
                                <form method="POST" action="{{ route('crm.orders.invoice.generate', $o) }}"
                                      onsubmit="return confirm('صدور فاکتور برای سفارش {{ $o->order_code }}؟ تراکنش commission و پیامک به مشتری ارسال خواهد شد.');">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold whitespace-nowrap">
                                        🧾 صدور فاکتور
                                    </button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="p-3 border-t border-gray-100 dark:border-gray-700">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
