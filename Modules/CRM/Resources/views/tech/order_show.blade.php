@extends('layouts.admin')

@section('page-title', 'جزئیات سفارش')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">سفارش <span dir="ltr">{{ $order->order_code }}</span></h1>
            <div class="flex items-center gap-2 mt-2">
                <span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                <span class="text-xs text-gray-500">ثبت شده در <span dir="ltr">@jdatetime($order->created_at)</span></span>
            </div>
        </div>
        <a href="{{ route('crm.orders.my') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">بازگشت به لیست</a>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">

            {{-- اطلاعات سفارش --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">اطلاعات سفارش</h2>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-gray-500">مشتری</dt>
                    <dd>{{ $order->customer_name }}</dd>

                    <dt class="text-gray-500">شماره تماس</dt>
                    <dd>
                        <a href="tel:{{ $order->customer_mobile }}" class="text-brand-600 hover:underline" dir="ltr">{{ $order->customer_mobile }}</a>
                        @if($order->customer_phone) / <a href="tel:{{ $order->customer_phone }}" dir="ltr">{{ $order->customer_phone }}</a>@endif
                    </dd>

                    <dt class="text-gray-500">دستگاه</dt>
                    <dd>{{ $order->brand?->name ?: '—' }}{{ $order->device ? ' / ' . $order->device->name : '' }}</dd>

                    <dt class="text-gray-500">عنوان مشکل</dt>
                    <dd>{{ $order->problem_title ?: '—' }}</dd>

                    <dt class="text-gray-500">زمان مراجعه</dt>
                    <dd dir="ltr">@jdatetime($order->visit_scheduled_at)</dd>

                    <dt class="text-gray-500 col-span-2">شرح مشکل</dt>
                    <dd class="col-span-2 whitespace-pre-wrap">{{ $order->problem_description ?: '—' }}</dd>

                    <dt class="text-gray-500">استان / شهر</dt>
                    @php($__districtName = $order->district?->name)
                    <dd>{{ $order->province?->name ?: '—' }}{{ $order->city ? ' / ' . $order->city->name : '' }}{{ $__districtName ? ' / ' . $__districtName : '' }}</dd>

                    <dt class="text-gray-500">کد پستی</dt>
                    <dd dir="ltr">{{ $order->postal_code ?: '—' }}</dd>

                    <dt class="text-gray-500 col-span-2">آدرس</dt>
                    <dd class="col-span-2 whitespace-pre-wrap">{{ $order->address ?: '—' }}</dd>
                </dl>
            </div>

            {{-- آیتم‌های سفارش (فقط نمایشی برای تکنسین) --}}
            @if($order->items->count())
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">اقلام ثبت‌شده</h2>
                <table class="w-full">
                    <thead class="text-xs text-gray-500 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="py-2 text-right">نوع</th>
                            <th class="py-2 text-right">عنوان</th>
                            <th class="py-2 text-right">تعداد</th>
                            <th class="py-2 text-right">جمع</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                        @foreach($order->items as $item)
                        <tr>
                            <td class="py-2">{{ $item->typeLabel() }}</td>
                            <td class="py-2">{{ $item->title }}</td>
                            <td class="py-2">{{ $item->quantity }}</td>
                            <td class="py-2 font-medium">{{ number_format($item->total_price) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- تاریخچه وضعیت --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">تاریخچه وضعیت</h2>
                <ol class="space-y-3 text-sm">
                    @forelse($order->statusLogs as $log)
                    <li class="flex items-start gap-3 pb-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <span class="text-xs text-gray-500 whitespace-nowrap" dir="ltr">@jdatetime($log->created_at)</span>
                        <div class="flex-1">
                            <div>
                                @if($log->fromLabel())<span>{{ $log->fromLabel() }}</span><span class="text-gray-400 mx-1">→</span>@endif
                                <strong>{{ $log->toLabel() }}</strong>
                            </div>
                            @if($log->note)<div class="text-xs text-gray-600 mt-1">{{ $log->note }}</div>@endif
                        </div>
                    </li>
                    @empty
                    <li class="text-gray-500">تاریخچه‌ای ثبت نشده.</li>
                    @endforelse
                </ol>
            </div>
        </div>

        {{-- ستون کنار --}}
        <div class="space-y-6">
            {{-- اقدام‌های مجاز تکنسین --}}
            @if(count($allowedTransitions))
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">اقدام</h2>
                <div class="space-y-2">
                    @foreach($allowedTransitions as $next)
                    <form action="{{ route('crm.tech.orders.status', $order) }}" method="POST" class="space-y-2">
                        @csrf
                        <input type="hidden" name="status" value="{{ $next->value }}">
                        <textarea name="note" rows="2" placeholder="توضیح (اختیاری)" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm"></textarea>
                        <button class="w-full px-3 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm">
                            تغییر وضعیت به «{{ $next->label() }}»
                        </button>
                    </form>
                    @endforeach
                </div>
            </div>
            @else
            <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-4 text-xs text-gray-600 dark:text-gray-300">
                در وضعیت فعلی، اقدامی برای شما تعریف نشده. برای تغییر این سفارش با ادمین تماس بگیرید.
            </div>
            @endif

            {{-- رسیدِ انتقال — فقط در وضعیتِ «انتقال به تعمیرگاه» یا «شروع تعمیر» --}}
            @php
                $canTransfer = \Modules\CRM\Services\TransferReceiptService::enabled()
                    && $order->status instanceof \Modules\CRM\Enums\OrderStatus
                    && in_array($order->status, [\Modules\CRM\Enums\OrderStatus::Open, \Modules\CRM\Enums\OrderStatus::RepairStarted], true);
                $trReceipts = $order->transferReceipts;
            @endphp
            @if($canTransfer || $trReceipts->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-3">رسید انتقال</h2>
                @if($trReceipts->isNotEmpty())
                <ul class="space-y-2 mb-3">
                    @foreach($trReceipts as $tr)
                    <li class="flex items-center justify-between gap-2 text-sm border border-gray-100 dark:border-gray-700 rounded-lg px-3 py-2">
                        <span class="font-mono text-gray-700 dark:text-gray-200" dir="ltr">{{ $tr->code }}</span>
                        <a href="{{ route('crm.transfer-receipt.public', $tr->token) }}" target="_blank" rel="noopener" class="text-brand-600 hover:underline text-xs whitespace-nowrap">مشاهده/چاپ ↗</a>
                    </li>
                    @endforeach
                </ul>
                @endif
                @if($canTransfer)
                <form action="{{ route('crm.tech.orders.transfer-receipt', $order) }}" method="POST" class="space-y-2">
                    @csrf
                    <textarea name="description" rows="3" placeholder="توضیح انتقال (مثلاً: دستگاه جهت تعویض برد به تعمیرگاه منتقل شد)"
                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm"></textarea>
                    <button class="w-full px-3 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-bold">ثبت رسید انتقال</button>
                </form>
                @else
                <p class="text-xs text-gray-400">ثبت رسید انتقال فقط در وضعیت «انتقال به تعمیرگاه» یا «شروع تعمیر» ممکن است.</p>
                @endif
            </div>
            @endif

            {{-- خلاصه مالی (نمایش محدود) --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-3">مالی سفارش</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">برآورد اولیه</dt><dd>{{ $order->estimated_price ? number_format($order->estimated_price) . ' ت' : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">جمع اقلام</dt><dd>{{ number_format($order->items_subtotal) }} ت</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">مبلغ نهایی</dt><dd>{{ $order->final_price ? number_format($order->final_price) . ' ت' : '—' }}</dd></div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
