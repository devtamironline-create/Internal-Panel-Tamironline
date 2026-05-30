@extends('layouts.admin')

@section('page-title', 'جزئیات فاکتور')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">فاکتور <span dir="ltr">{{ $invoice->invoice_code }}</span></h1>
            <span class="inline-block mt-2 px-2.5 py-1 text-xs font-medium rounded-full {{ $invoice->statusBadge() }}">{{ $invoice->statusLabel() }}</span>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
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

                {{-- Push دستی به WP — مفید برای فاکتورهایی که هنوز سینک نشده‌اند --}}
                <form action="{{ route('crm.invoices.push-to-wp', $invoice) }}" method="POST" class="inline"
                      onsubmit="return confirm('این فاکتور به WP CRM ارسال شود؟');">
                    @csrf
                    @if($invoice->wp_id)
                        <button class="px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg text-sm" title="wp_id={{ $invoice->wp_id }} — برای ارسال مجدد">
                            🔄 ارسال مجدد به WP
                        </button>
                    @else
                        <button class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm" title="هنوز روی WP نیست">
                            📤 ارسال به WP CRM
                        </button>
                    @endif
                </form>
            @endcan
            <a href="{{ route('crm.invoices.print', $invoice) }}" target="_blank"
               class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm">
                🖨 چاپ / دانلود فاکتور
            </a>
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
            @php
                // اول از رابطهٔ Laravel-native (OrderItem). اگر خالی بود،
                // به آرایه‌های موازی WP fallback می‌کنیم تا سفارش‌های
                // ایمپورت‌شده از WP CRM هم اقلامشان دیده شود.
                $rows = collect();
                if ($invoice->order->items->isNotEmpty()) {
                    foreach ($invoice->order->items as $item) {
                        $rows->push([
                            'type'  => $item->typeLabel(),
                            'title' => $item->title,
                            'qty'   => (int) $item->quantity,
                            'unit'  => (int) $item->unit_price,
                            'total' => (int) $item->total_price,
                        ]);
                    }
                } else {
                    $titles = is_array($invoice->order->piece_list) ? $invoice->order->piece_list : [];
                    $sells  = is_array($invoice->order->customer_price_list) ? $invoice->order->customer_price_list : [];
                    $buys   = is_array($invoice->order->buy_price_list) ? $invoice->order->buy_price_list : [];
                    foreach ($titles as $i => $title) {
                        if ($title === '' || $title === null) continue;
                        $unit = (int) ($sells[$i] ?? 0);
                        $rows->push([
                            'type'  => 'قطعه',
                            'title' => is_string($title) ? $title : (string) ($title['title'] ?? ''),
                            'qty'   => 1,
                            'unit'  => $unit,
                            'total' => $unit,
                            'buy'   => (int) ($buys[$i] ?? 0),
                        ]);
                    }
                }
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">اقلام سفارش</h2>
                @if($rows->isEmpty())
                    <p class="text-xs text-gray-400 italic py-4">برای این سفارش هیچ قلمی ثبت نشده است.</p>
                @else
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
                        @foreach($rows as $r)
                        <tr>
                            <td class="py-2">{{ $r['type'] }}</td>
                            <td class="py-2">
                                {{ $r['title'] }}
                                @if(! empty($r['buy']))
                                    <span class="text-[10px] text-gray-400">(خرید: {{ number_format($r['buy']) }})</span>
                                @endif
                            </td>
                            <td class="py-2">{{ $r['qty'] }}</td>
                            <td class="py-2">{{ number_format($r['unit']) }}</td>
                            <td class="py-2 font-medium">{{ number_format($r['total']) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
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
