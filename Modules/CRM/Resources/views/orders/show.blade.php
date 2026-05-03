@extends('layouts.admin')

@section('page-title', 'جزئیات سفارش')

@section('main')
@php use Modules\CRM\Enums\OrderStatus; @endphp
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">سفارش <span dir="ltr">{{ $order->order_code }}</span></h1>
            <div class="flex items-center gap-2 mt-2">
                <span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400">ثبت شده در <span dir="ltr">@jdatetime($order->created_at)</span></span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @can('edit-crm-order')
            <a href="{{ route('crm.orders.edit', $order) }}" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ویرایش</a>
            @endcan
            <a href="{{ route('crm.orders.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">بازگشت</a>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- ستون چپ: دو ستون وسیع --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- اطلاعات سفارش --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">اطلاعات سفارش</h2>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <dt class="text-gray-500 dark:text-gray-400">مشتری</dt>
                    <dd class="text-gray-900 dark:text-gray-100">
                        <a href="{{ route('crm.customers.show', $order->customer) }}" class="text-brand-600 hover:underline">{{ $order->customer_name }}</a>
                        <span class="text-xs">(@tel($order->customer_mobile))</span>
                    </dd>

                    <dt class="text-gray-500 dark:text-gray-400">برند</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $order->brand?->name ?: '—' }}</dd>

                    <dt class="text-gray-500 dark:text-gray-400">دستگاه</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $order->device?->name ?: '—' }}</dd>

                    <dt class="text-gray-500 dark:text-gray-400">عنوان مشکل</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $order->problem_title ?: '—' }}</dd>

                    <dt class="text-gray-500 dark:text-gray-400">زمان مراجعه</dt>
                    <dd class="text-gray-900 dark:text-gray-100" dir="ltr">@jdatetime($order->visit_scheduled_at)</dd>

                    <dt class="text-gray-500 dark:text-gray-400 col-span-2">شرح مشکل</dt>
                    <dd class="text-gray-900 dark:text-gray-100 col-span-2 whitespace-pre-wrap">{{ $order->problem_description ?: '—' }}</dd>

                    <dt class="text-gray-500 dark:text-gray-400">استان / شهر</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $order->province?->name ?: '—' }}{{ $order->city ? ' / ' . $order->city->name : '' }}</dd>

                    <dt class="text-gray-500 dark:text-gray-400">کد پستی</dt>
                    <dd class="text-gray-900 dark:text-gray-100" dir="ltr">{{ $order->postal_code ?: '—' }}</dd>

                    <dt class="text-gray-500 dark:text-gray-400 col-span-2">آدرس</dt>
                    <dd class="text-gray-900 dark:text-gray-100 col-span-2 whitespace-pre-wrap">{{ $order->address ?: '—' }}</dd>

                    @php $deviceImg = $order->device_img1 ?: $order->device_image_input; @endphp
                    @if($deviceImg)
                    <dt class="text-gray-500 dark:text-gray-400 col-span-2 pt-2 border-t border-gray-100 dark:border-gray-700">تصویر دستگاه</dt>
                    <dd class="col-span-2">
                        <a href="{{ $deviceImg }}" target="_blank" rel="noopener" class="inline-block">
                            <img src="{{ $deviceImg }}" alt="تصویر دستگاه" loading="lazy"
                                 class="max-h-40 rounded-lg border border-gray-200 dark:border-gray-700 object-cover hover:opacity-90 transition-opacity">
                        </a>
                        <a href="{{ $deviceImg }}" target="_blank" rel="noopener"
                           class="block mt-2 text-xs text-brand-600 hover:underline" dir="ltr">{{ $deviceImg }}</a>
                    </dd>
                    @endif
                </dl>
            </div>

            {{-- آیتم‌ها --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">آیتم‌های سفارش (قطعات/خدمات/...)</h2>

                <table class="w-full mb-4">
                    <thead>
                        <tr class="text-xs text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 text-right">نوع</th>
                            <th class="py-2 text-right">عنوان</th>
                            <th class="py-2 text-right">تعداد</th>
                            <th class="py-2 text-right">فی</th>
                            <th class="py-2 text-right">جمع</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($order->items as $item)
                        <tr class="text-sm">
                            <td class="py-2">{{ $item->typeLabel() }}</td>
                            <td class="py-2 text-gray-900 dark:text-gray-100">
                                {{ $item->title }}
                                @if($item->description)
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item->description }}</div>
                                @endif
                            </td>
                            <td class="py-2">{{ $item->quantity }}</td>
                            <td class="py-2">{{ number_format($item->unit_price) }}</td>
                            <td class="py-2 font-medium">{{ number_format($item->total_price) }}</td>
                            <td class="py-2">
                                @can('edit-crm-order')
                                <form action="{{ route('crm.orders.items.destroy', [$order, $item]) }}" method="POST" onsubmit="return confirm('حذف شود؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:text-red-800 text-xs">حذف</button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="py-4 text-center text-sm text-gray-500">آیتمی ثبت نشده.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200 dark:border-gray-700 text-sm">
                            <td colspan="4" class="py-2 text-left font-medium">جمع کل آیتم‌ها:</td>
                            <td class="py-2 font-bold">{{ number_format($order->items_subtotal) }} تومان</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>

                @can('edit-crm-order')
                <form action="{{ route('crm.orders.items.store', $order) }}" method="POST" class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    @csrf
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">افزودن آیتم</h3>
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-2">
                        <select name="type" class="md:col-span-1 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                            <option value="part">قطعه</option>
                            <option value="service">خدمت</option>
                            <option value="transport">حمل‌ونقل</option>
                            <option value="discount">تخفیف</option>
                        </select>
                        <input type="text" name="title" placeholder="عنوان" required class="md:col-span-2 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        <input type="number" name="quantity" value="1" min="1" placeholder="تعداد" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        <input type="number" name="unit_price" value="0" min="0" placeholder="فی (تومان)" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        <button type="submit" class="px-3 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm">افزودن</button>
                    </div>
                </form>
                @endcan
            </div>

            {{-- تاریخچه و رویدادها --}}
            @php
                $wpEvents = $order->wp_events;
                $wpNotes = $order->wp_notes;
                $wpReturns = $order->wp_return_logs;
                // مرتب‌سازی نزولی بر اساس date (مثل krsort در WP)
                $sortByDateDesc = function (array $items): array {
                    usort($items, fn ($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
                    return $items;
                };
                $wpEvents = $sortByDateDesc($wpEvents);
                $wpNotes = $sortByDateDesc($wpNotes);
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 space-y-6">
                <div>
                    <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">تاریخچه وضعیت</h2>
                    <ol class="space-y-3 text-sm">
                        @forelse($order->statusLogs as $log)
                        <li class="flex items-start gap-3 pb-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap" dir="ltr">@jdatetime($log->created_at)</span>
                            <div class="flex-1">
                                <div class="text-gray-900 dark:text-gray-100">
                                    @if($log->fromLabel())
                                    <span>{{ $log->fromLabel() }}</span>
                                    <span class="text-gray-400 mx-1">→</span>
                                    @endif
                                    <strong>{{ $log->toLabel() }}</strong>
                                </div>
                                @if($log->note)
                                <div class="text-xs text-gray-600 dark:text-gray-300 mt-1">{{ $log->note }}</div>
                                @endif
                                @if($log->changer)
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">توسط: {{ $log->changer->name ?? '—' }}</div>
                                @endif
                            </div>
                        </li>
                        @empty
                        <li class="text-gray-500 text-sm">رویدادی در پنل جدید ثبت نشده.</li>
                        @endforelse
                    </ol>
                </div>

                {{-- رویدادهای پنل قبلی (WP) --}}
                @if(count($wpEvents))
                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-3">رویدادهای پنل قبلی</h3>
                    <ul class="space-y-3 text-sm">
                        @foreach($wpEvents as $ev)
                        <li class="flex items-start gap-3 pb-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap" dir="ltr">{{ $ev['date'] ?? '—' }}</span>
                            <div class="flex-1">
                                @if(! empty($ev['subject']))
                                <div class="text-gray-900 dark:text-gray-100 font-medium">{{ $ev['subject'] }}</div>
                                @endif
                                @if(! empty($ev['content']))
                                <div class="text-xs text-gray-600 dark:text-gray-300 mt-1 whitespace-pre-wrap">{!! strip_tags((string) $ev['content'], '<br><b><strong><i><em>') !!}</div>
                                @endif
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex flex-wrap gap-x-3">
                                    @if(! empty($ev['author']))
                                    <span>اپراتور/تکنسین: {{ is_numeric($ev['author']) ? '#' . $ev['author'] : $ev['author'] }}</span>
                                    @endif
                                    @if(! empty($ev['status']))
                                    <span>وضعیت: {{ $ev['status'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                {{-- یادداشت‌های پنل قبلی --}}
                @if(count($wpNotes))
                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-3">یادداشت‌های پنل قبلی</h3>
                    <ul class="space-y-3 text-sm">
                        @foreach($wpNotes as $nt)
                        <li class="flex items-start gap-3 pb-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap" dir="ltr">{{ $nt['date'] ?? '—' }}</span>
                            <div class="flex-1">
                                @if(! empty($nt['content']))
                                <div class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{!! strip_tags((string) $nt['content'], '<br><b><strong><i><em>') !!}</div>
                                @endif
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex flex-wrap gap-x-3">
                                    @if(! empty($nt['author']))
                                    <span>اپراتور/تکنسین: {{ is_numeric($nt['author']) ? '#' . $nt['author'] : $nt['author'] }}</span>
                                    @endif
                                    @if(! empty($nt['status']))
                                    <span>وضعیت: {{ $nt['status'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                {{-- لاگ بازگشت سفارش --}}
                @if(count($wpReturns))
                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-3">لاگ بازگشت سفارش</h3>
                    <ul class="space-y-3 text-sm">
                        @foreach($wpReturns as $rt)
                        @php
                            $returnType = $rt['return_type'] ?? null;
                            if ((string) $returnType === '1') {
                                $msg = $rt['invoice_descripotion'] ?? '';
                            } else {
                                $msg = trim(($rt['cancel_desc'] ?? '') . ' ' . ($rt['cancel_desc_other'] ?? ''));
                            }
                            $negativeMsg = ((int) ($rt['negative_invoice'] ?? 0)) === 1 ? 'بلی' : 'خیر';
                        @endphp
                        <li class="flex items-start gap-3 pb-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                            <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap" dir="ltr">{{ $rt['date'] ?? '—' }}</span>
                            <div class="flex-1">
                                @if($msg !== '')
                                <div class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $msg }}</div>
                                @endif
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 flex flex-wrap gap-x-3">
                                    <span>منفی شدن فاکتور: {{ $negativeMsg }}</span>
                                    @if(! empty($rt['author']))
                                    <span>توسط: {{ is_numeric($rt['author']) ? '#' . $rt['author'] : $rt['author'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>
        </div>

        {{-- ستون راست (کناری) --}}
        <div class="space-y-6">

            {{-- فاکتور سفارش — هم‌ارز با Orders/show_order.php در WP CRM --}}
            @php $fin = $order->financialSummary(); @endphp
            @if($fin['has_data'])
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">فاکتور سفارش</h2>

                {{-- لیست قطعات / خدمات --}}
                @php
                    $pieces = is_array($order->piece_list) ? $order->piece_list : [];
                    $custPriceList = is_array($order->customer_price_list) ? $order->customer_price_list : [];
                    $buyPriceList = is_array($order->buy_price_list) ? $order->buy_price_list : [];
                @endphp
                @if(count($pieces))
                <div class="overflow-x-auto -mx-2 mb-4">
                    <table class="w-full text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                            <tr>
                                <th class="px-2 py-2 text-right font-medium">قطعه / خدمت</th>
                                <th class="px-2 py-2 text-right font-medium">قیمت به مشتری</th>
                                <th class="px-2 py-2 text-right font-medium">قیمت خرید</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($pieces as $i => $piece)
                            <tr>
                                <td class="px-2 py-2">{{ $piece }}</td>
                                <td class="px-2 py-2">{{ isset($custPriceList[$i]) && $custPriceList[$i] !== '' ? number_format((int) $custPriceList[$i]) : '—' }}</td>
                                <td class="px-2 py-2">{{ isset($buyPriceList[$i]) && $buyPriceList[$i] !== '' ? number_format((int) $buyPriceList[$i]) : '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                {{-- اجرت / ایاب و ذهاب / تخفیف --}}
                @if($order->hire || $order->transportation || $order->discount)
                <dl class="space-y-2 text-sm mb-4 pb-4 border-b border-gray-100 dark:border-gray-700">
                    @if($order->hire)
                    <div class="flex justify-between"><dt class="text-gray-500">اجرت</dt><dd>{{ number_format($order->hire) }} تومان</dd></div>
                    @endif
                    @if($order->transportation)
                    <div class="flex justify-between"><dt class="text-gray-500">ایاب و ذهاب</dt><dd>{{ number_format($order->transportation) }} تومان</dd></div>
                    @endif
                    @if($order->discount)
                    <div class="flex justify-between"><dt class="text-gray-500">تخفیف</dt><dd>{{ number_format($order->discount) }} تومان</dd></div>
                    @endif
                </dl>
                @endif

                {{-- جمع‌بندی مالی --}}
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">جمع کل صورت حساب</dt>
                        <dd class="font-medium">{{ number_format($fin['customer_total']) }} تومان</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">جمع هزینه‌ها</dt>
                        <dd>{{ number_format($fin['cost_total']) }} تومان</dd>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                        <dt class="text-gray-500">مانده</dt>
                        <dd class="font-medium">{{ number_format($fin['remaining']) }} تومان</dd>
                    </div>
                    <div class="flex justify-between text-amber-700 dark:text-amber-400">
                        <dt>سهم تکنسین</dt>
                        <dd class="font-medium">{{ number_format($fin['tech_share']) }} تومان</dd>
                    </div>
                    <div class="flex justify-between text-emerald-700 dark:text-emerald-400">
                        <dt>سهم شرکت</dt>
                        <dd class="font-bold">{{ number_format($fin['company_share']) }} تومان</dd>
                    </div>
                </dl>

                {{-- بیعانه (در صورت ثبت محلی) --}}
                @if($order->deposit)
                <dl class="space-y-2 text-sm mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <div class="flex justify-between text-gray-500">
                        <dt>بیعانه پرداخت‌شده</dt>
                        <dd>{{ number_format($order->deposit) }} تومان</dd>
                    </div>
                </dl>
                @endif

                {{-- متن فاکتور برای مشتری --}}
                @if($order->invoice_descripotion)
                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <h3 class="text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">متن فاکتور برای مشتری</h3>
                    <p class="text-xs text-gray-600 dark:text-gray-400 whitespace-pre-wrap">{{ $order->invoice_descripotion }}</p>
                </div>
                @endif
            </div>
            @else
            {{-- سفارش بدون داده فاکتور — fallback به فیلدهای پایه --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">خلاصه مالی</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">برآورد اولیه</dt>
                        <dd>{{ $order->estimated_price ? number_format($order->estimated_price) . ' تومان' : '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">جمع آیتم‌ها</dt>
                        <dd>{{ number_format($order->items_subtotal) }} تومان</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">مبلغ نهایی</dt>
                        <dd>{{ $order->final_price ? number_format($order->final_price) . ' تومان' : '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">بیعانه</dt>
                        <dd>{{ number_format($order->deposit ?? 0) }} تومان</dd>
                    </div>
                    <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700 font-bold">
                        <dt>مانده قابل پرداخت</dt>
                        <dd>{{ number_format($order->balance_due) }} تومان</dd>
                    </div>
                </dl>
            </div>
            @endif

            {{-- تخصیص تکنسین --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">تکنسین</h2>
                @if($order->technician)
                <div class="mb-3">
                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ trim($order->technician->first_name . ' ' . $order->technician->last_name) }}</div>
                    <div class="text-xs">@tel($order->technician->mobile)</div>
                    @if($order->assigned_at)
                    <div class="text-xs text-gray-500 mt-1">تخصیص داده شده در <span dir="ltr">@jdatetime($order->assigned_at)</span></div>
                    @endif
                </div>
                @can('assign-crm-technician')
                <form action="{{ route('crm.orders.unassign', $order) }}" method="POST" onsubmit="return confirm('تکنسین برداشته شود؟');">
                    @csrf
                    <button class="w-full px-3 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 text-sm">حذف تخصیص</button>
                </form>
                @endcan
                @else
                <p class="text-sm text-gray-500 mb-3">تکنسینی تخصیص داده نشده.</p>
                @can('assign-crm-technician')
                <form action="{{ route('crm.orders.assign', $order) }}" method="POST" class="space-y-2">
                    @csrf
                    <select name="technician_id" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        <option value="">— انتخاب تکنسین آماده —</option>
                        @foreach($technicians as $t)
                        <option value="{{ $t->id }}">{{ trim($t->first_name . ' ' . ($t->last_name ?? '')) }}</option>
                        @endforeach
                    </select>
                    <button class="w-full px-3 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm">تخصیص</button>
                </form>
                @endcan
                @endif
            </div>

            {{-- تغییر وضعیت --}}
            @can('change-crm-order-status')
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">تغییر وضعیت</h2>
                <form action="{{ route('crm.orders.status.change', $order) }}" method="POST" class="space-y-2">
                    @csrf
                    <select name="status" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(($order->status->value ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <textarea name="note" rows="2" placeholder="توضیح (اختیاری)" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm"></textarea>
                    <button class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm">ثبت تغییر</button>
                </form>
            </div>
            @endcan

            {{-- یادداشت داخلی --}}
            @if($order->notes)
            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl p-4">
                <h3 class="text-sm font-bold text-yellow-800 dark:text-yellow-200 mb-2">یادداشت داخلی</h3>
                <p class="text-sm text-yellow-900 dark:text-yellow-100 whitespace-pre-wrap">{{ $order->notes }}</p>
            </div>
            @endif

        </div>
    </div>
</div>
@endsection
