@extends('layouts.admin')

@section('page-title', 'جزئیات فاکتور')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">فاکتور <span dir="ltr">{{ $invoice->invoice_code }}</span></h1>
            <span class="inline-block mt-2 px-2.5 py-1 text-xs font-medium rounded-full {{ $invoice->statusBadge() }}">{{ $invoice->statusLabel() }}</span>
        </div>
        <div class="flex items-center gap-2">
            @can('manage-crm-financial')
                @if($invoice->status === 'issued')
                <form action="{{ route('crm.invoices.paid', $invoice) }}" method="POST" class="inline">
                    @csrf
                    <button class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">علامت‌گذاری به‌عنوان پرداخت‌شده</button>
                </form>
                <form action="{{ route('crm.invoices.cancel', $invoice) }}" method="POST" class="inline" onsubmit="return confirm('لغو شود؟');">
                    @csrf
                    <button class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">لغو فاکتور</button>
                </form>
                @endif
            @endcan
            <a href="{{ route('crm.invoices.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">بازگشت</a>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    @if($invoice->status === 'issued')
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h3 class="text-sm font-bold text-blue-900 dark:text-blue-200">لینک پرداخت آنلاین مشتری</h3>
                <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">این لینک را برای مشتری بفرستید تا پرداخت کند (نیاز به ورود ندارد).</p>
            </div>
            <div class="flex items-center gap-2">
                <input type="text" readonly value="{{ url('/crm/pay/' . $invoice->invoice_code) }}" dir="ltr"
                       class="px-3 py-2 border border-blue-300 dark:border-blue-700 bg-white dark:bg-gray-800 rounded-lg text-xs w-80"
                       onclick="this.select()">
                <a href="{{ url('/crm/pay/' . $invoice->invoice_code) }}" target="_blank" class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-xs">باز کردن</a>
            </div>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">

            {{-- اطلاعات فاکتور --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">اطلاعات فاکتور</h2>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-gray-500">کد فاکتور</dt>
                    <dd dir="ltr">{{ $invoice->invoice_code }}</dd>

                    <dt class="text-gray-500">سفارش</dt>
                    <dd>
                        @if($invoice->order)
                        <a href="{{ route('crm.orders.show', $invoice->order) }}" class="text-brand-600 hover:underline" dir="ltr">{{ $invoice->order->order_code }}</a>
                        @endif
                    </dd>

                    <dt class="text-gray-500">مشتری</dt>
                    <dd>
                        @if($invoice->customer)
                        <a href="{{ route('crm.customers.show', $invoice->customer) }}" class="text-brand-600 hover:underline">{{ $invoice->customer->display_name }}</a>
                        <span class="text-xs">(@tel($invoice->customer->mobile))</span>
                        @endif
                    </dd>

                    <dt class="text-gray-500">تکنسین</dt>
                    <dd>{{ $invoice->technician ? trim($invoice->technician->first_name . ' ' . $invoice->technician->last_name) : '—' }}</dd>

                    <dt class="text-gray-500">روش محاسبه</dt>
                    <dd>{{ $invoice->calc_type === 'percent_of_total' ? 'درصد از کل فاکتور' : 'درصد از مبلغ دریافتی' }} ({{ $invoice->commission_percent }}%)</dd>

                    <dt class="text-gray-500">زمان صدور</dt>
                    <dd dir="ltr">@jdatetime($invoice->issued_at)</dd>

                    @if($invoice->paid_at)
                    <dt class="text-gray-500">زمان پرداخت</dt>
                    <dd dir="ltr">@jdatetime($invoice->paid_at)</dd>
                    @endif
                </dl>
            </div>

            {{-- آیتم‌های سفارش (snapshot) --}}
            @if($invoice->order)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">اقلام سفارش</h2>
                <table class="w-full">
                    <thead class="text-xs text-gray-500 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="py-2 text-right">نوع</th>
                            <th class="py-2 text-right">عنوان</th>
                            <th class="py-2 text-right">تعداد</th>
                            <th class="py-2 text-right">فی</th>
                            <th class="py-2 text-right">جمع</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                        @foreach($invoice->order->items as $item)
                        <tr>
                            <td class="py-2">{{ $item->typeLabel() }}</td>
                            <td class="py-2">{{ $item->title }}</td>
                            <td class="py-2">{{ $item->quantity }}</td>
                            <td class="py-2">{{ number_format($item->unit_price) }}</td>
                            <td class="py-2 font-medium">{{ number_format($item->total_price) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- خلاصه مالی و تراکنش‌ها --}}
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">خلاصه مالی</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">مبلغ نهایی</dt>
                        <dd class="font-bold">{{ number_format($invoice->total_amount) }} ت</dd>
                    </div>
                    <div class="flex justify-between text-green-600">
                        <dt>سهم تکنسین</dt>
                        <dd class="font-bold">{{ number_format($invoice->tech_share) }} ت</dd>
                    </div>
                    <div class="flex justify-between text-blue-600">
                        <dt>سهم شرکت</dt>
                        <dd class="font-bold">{{ number_format($invoice->company_share) }} ت</dd>
                    </div>
                </dl>
            </div>

            @if($invoice->walletTransactions->count())
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-3">تراکنش‌های مرتبط</h2>
                <ul class="space-y-2 text-sm">
                    @foreach($invoice->walletTransactions as $tx)
                    <li class="flex justify-between items-center pb-2 border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <div>
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $tx->type->badgeClass() }}">{{ $tx->type->label() }}</span>
                            <div class="text-xs text-gray-500 mt-1" dir="ltr">@jdatetime($tx->created_at)</div>
                        </div>
                        <span class="font-bold {{ $tx->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ ($tx->amount >= 0 ? '+' : '') . number_format($tx->amount) }} ت
                        </span>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
