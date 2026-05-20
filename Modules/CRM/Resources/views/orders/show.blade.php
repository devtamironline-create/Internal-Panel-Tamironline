@extends('layouts.admin')

@section('page-title', 'جزئیات سفارش')

@section('main')
@php
    use Modules\CRM\Enums\OrderStatus;
    $adminNotes = $order->adminNotes()->with('user')->get();
@endphp
<div class="p-6 space-y-6" x-data="{ showNotes: false }">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">سفارش <span dir="ltr">{{ $order->order_code }}</span></h1>
            <div class="flex items-center gap-2 mt-2">
                <span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400">ثبت شده در <span dir="ltr">@jdatetime($order->created_at)</span></span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @can('view-crm-orders')
            <button type="button" @click="showNotes = true"
                    class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                یادداشت‌ها
                @if($adminNotes->count() > 0)
                    <span class="text-[10px] bg-white text-amber-700 rounded-full px-1.5 py-0.5 font-bold">{{ $adminNotes->count() }}</span>
                @endif
            </button>
            @endcan
            @can('edit-crm-order')
            <a href="{{ route('crm.orders.edit', $order) }}" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ویرایش</a>
            @endcan
            <a href="{{ route('crm.orders.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">بازگشت</a>
        </div>
    </div>

    {{-- ─── Modal یادداشت‌های اپراتور ─────────────────────────────── --}}
    <div x-show="showNotes" x-cloak @keydown.escape.window="showNotes = false"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
         @click.self="showNotes = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col"
             x-transition.scale.duration.150ms>
            <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">
                    یادداشت‌های اپراتور
                    <span class="text-xs text-gray-500 font-normal mr-2">({{ $adminNotes->count() }} یادداشت)</span>
                </h2>
                <button type="button" @click="showNotes = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-5 overflow-y-auto flex-1">
                {{-- فرم افزودن یادداشت --}}
                <form method="POST" action="{{ route('crm.orders.notes.store', $order) }}" class="mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
                    @csrf
                    <textarea name="content" rows="3" minlength="3" maxlength="2000" required
                              class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500 focus:outline-none"
                              placeholder="یادداشت خود را بنویسید... (حداقل ۳ کاراکتر)">{{ old('content') }}</textarea>
                    @error('content')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                    <div class="flex justify-end mt-2">
                        <button type="submit" class="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-lg text-sm font-medium">
                            ثبت یادداشت
                        </button>
                    </div>
                </form>

                {{-- لیست یادداشت‌ها --}}
                @if($adminNotes->isEmpty())
                    <p class="text-sm text-gray-400 text-center py-6">هنوز یادداشتی ثبت نشده.</p>
                @else
                    <ul class="space-y-3">
                        @foreach($adminNotes as $note)
                            <li class="flex items-start gap-3 pb-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                <div class="w-9 h-9 rounded-full bg-brand-100 dark:bg-brand-900 text-brand-700 dark:text-brand-200 flex items-center justify-center text-sm font-bold flex-shrink-0">
                                    {{ mb_substr($note->user?->first_name ?: '?', 0, 1) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between flex-wrap gap-2">
                                        <span class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                            {{ trim(($note->user?->first_name ?? '') . ' ' . ($note->user?->last_name ?? '')) ?: 'اپراتور حذف‌شده' }}
                                        </span>
                                        <span class="text-[11px] text-gray-500 dark:text-gray-400" dir="ltr">@jdatetime($note->created_at)</span>
                                    </div>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 mt-1 whitespace-pre-wrap break-words">{{ $note->content }}</p>
                                    @if($note->user_id === auth()->id() || (auth()->user()?->can('manage-permissions') ?? false))
                                        <form method="POST" action="{{ route('crm.orders.notes.destroy', [$order, $note->id]) }}" class="inline" onsubmit="return confirm('این یادداشت حذف شود؟');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-[11px] text-rose-600 hover:text-rose-800 mt-1">حذف</button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
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

                    <dt class="text-gray-500 dark:text-gray-400">استان</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $order->province?->name ?: '—' }}</dd>

                    <dt class="text-gray-500 dark:text-gray-400">شهر</dt>
                    <dd class="text-gray-900 dark:text-gray-100">{{ $order->city?->name ?: '—' }}</dd>

                    <dt class="text-gray-500 dark:text-gray-400">کد پستی</dt>
                    <dd class="text-gray-900 dark:text-gray-100" dir="ltr">{{ $order->postal_code ?: '—' }}</dd>

                    <dt class="text-gray-500 dark:text-gray-400 col-span-2">آدرس</dt>
                    <dd class="text-gray-900 dark:text-gray-100 col-span-2 whitespace-pre-wrap">{{ $order->address ?: '—' }}</dd>

                    @php
                        $deviceImg = $order->device_img1 ?: $order->device_image_input;
                        // مسیر ذخیره‌شده ممکن است نسبی (مثل crm/orders/.../x.jpg) یا
                        // مطلق (با /storage/ یا http) باشد. اگر نسبی است، از پراکسی
                        // /file/{path} استفاده می‌کنیم چون symlink storage روی
                        // cPanel/LiteSpeed خراب است.
                        $deviceImgUrl = null;
                        if ($deviceImg) {
                            $isAbsolute = preg_match('#^(https?:)?//#', $deviceImg)
                                || str_starts_with($deviceImg, '/');
                            $deviceImgUrl = $isAbsolute
                                ? $deviceImg
                                : storage_url(ltrim($deviceImg, '/'));
                        }
                    @endphp
                    @if($deviceImg)
                    <dt class="text-gray-500 dark:text-gray-400 col-span-2 pt-2 border-t border-gray-100 dark:border-gray-700">تصویر دستگاه</dt>
                    <dd class="col-span-2">
                        <a href="{{ $deviceImgUrl }}" target="_blank" rel="noopener" class="inline-block">
                            <img src="{{ $deviceImgUrl }}" alt="تصویر دستگاه" loading="lazy"
                                 class="max-h-40 rounded-lg border border-gray-200 dark:border-gray-700 object-cover hover:opacity-90 transition-opacity">
                        </a>
                        <a href="{{ $deviceImgUrl }}" target="_blank" rel="noopener"
                           class="block mt-2 text-xs text-brand-600 hover:underline" dir="ltr">{{ $deviceImg }}</a>
                    </dd>
                    @endif
                </dl>
            </div>

            {{-- آیتم‌ها --}}
            @php
                $localItems = $order->items;
                $wpPieces = is_array($order->piece_list) ? $order->piece_list : [];
                $wpCust   = is_array($order->customer_price_list) ? $order->customer_price_list : [];
                $wpBuy    = is_array($order->buy_price_list) ? $order->buy_price_list : [];
                $hasWpPieces = ! $localItems->count() && count($wpPieces);
                $itemsSubtotal = $localItems->count()
                    ? $order->items_subtotal
                    : ((int) array_sum(array_map('intval', $wpCust)) ?: (int) array_sum(array_map('intval', $wpBuy)));
            @endphp
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
                        @forelse($localItems as $item)
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
                        @if($hasWpPieces)
                            @foreach($wpPieces as $i => $piece)
                            @php
                                $cust = isset($wpCust[$i]) && $wpCust[$i] !== '' ? (int) $wpCust[$i] : null;
                                $buy  = isset($wpBuy[$i])  && $wpBuy[$i]  !== '' ? (int) $wpBuy[$i]  : null;
                                $unit = $cust ?? $buy;
                            @endphp
                            <tr class="text-sm">
                                <td class="py-2">قطعه</td>
                                <td class="py-2 text-gray-900 dark:text-gray-100">
                                    {{ $piece }}
                                    @if($cust !== null && $buy !== null)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">قیمت خرید: {{ number_format($buy) }}</div>
                                    @endif
                                </td>
                                <td class="py-2">۱</td>
                                <td class="py-2">{{ $unit !== null ? number_format($unit) : '—' }}</td>
                                <td class="py-2 font-medium">{{ $unit !== null ? number_format($unit) : '—' }}</td>
                                <td class="py-2 text-xs text-gray-400">از پنل قبلی</td>
                            </tr>
                            @endforeach
                        @else
                            <tr><td colspan="6" class="py-4 text-center text-sm text-gray-500">آیتمی ثبت نشده.</td></tr>
                        @endif
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200 dark:border-gray-700 text-sm">
                            <td colspan="4" class="py-2 text-left font-medium">جمع کل آیتم‌ها:</td>
                            <td class="py-2 font-bold">{{ number_format($itemsSubtotal) }} تومان</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>

                @can('edit-crm-order')
                <form action="{{ route('crm.orders.items.store', $order) }}" method="POST"
                      class="border-t border-gray-200 dark:border-gray-700 pt-4"
                      x-data="{
                          rows: [{ type: 'part', title: '', quantity: 1, unit_price: 0 }],
                          add() { this.rows.push({ type: 'part', title: '', quantity: 1, unit_price: 0 }); },
                          remove(i) { if (this.rows.length > 1) this.rows.splice(i, 1); }
                      }">
                    @csrf
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">افزودن آیتم</h3>

                    <template x-for="(row, i) in rows" :key="i">
                        <div class="grid grid-cols-1 md:grid-cols-7 gap-2 mb-2">
                            <select :name="`items[${i}][type]`" x-model="row.type"
                                    class="md:col-span-1 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                                <option value="part">قطعه</option>
                                <option value="service">خدمت</option>
                                <option value="transport">حمل‌ونقل</option>
                                <option value="discount">تخفیف</option>
                            </select>
                            <input type="text" :name="`items[${i}][title]`" x-model="row.title" placeholder="عنوان" required
                                   class="md:col-span-2 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                            <input type="number" :name="`items[${i}][quantity]`" x-model.number="row.quantity" min="1" placeholder="تعداد"
                                   class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                            <input type="number" :name="`items[${i}][unit_price]`" x-model.number="row.unit_price" min="0" placeholder="فی (تومان)"
                                   class="md:col-span-2 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                            <button type="button" @click="remove(i)"
                                    :disabled="rows.length === 1"
                                    class="px-3 py-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg text-sm disabled:opacity-40 disabled:cursor-not-allowed">حذف</button>
                        </div>
                    </template>

                    <div class="flex items-center justify-between mt-3">
                        <button type="button" @click="add()"
                                class="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg text-sm">
                            + افزودن ردیف
                        </button>
                        <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm">ثبت آیتم‌ها</button>
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
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">توسط: {{ $log->changer->full_name ?? '—' }}</div>
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

                {{-- ارسال/ارسال مجدد فاکتور به WP CRM —
                     فقط برای سفارش‌های Completed که فاکتور فعال دارند --}}
                @php
                    // global scope active روی Invoice فاکتورهای superseded را خارج می‌کند
                    $activeInvoice = \Modules\CRM\Models\Invoice::where('order_id', $order->id)->first();
                @endphp
                @can('manage-crm-financial')
                @if($activeInvoice)
                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <form action="{{ route('crm.invoices.push-to-wp', $activeInvoice) }}" method="POST"
                          onsubmit="return confirm('این فاکتور به WP CRM ارسال شود؟');">
                        @csrf
                        @if($activeInvoice->wp_id)
                            <button class="w-full px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg text-sm" title="wp_id={{ $activeInvoice->wp_id }}">
                                🔄 ارسال مجدد فاکتور به WP
                            </button>
                        @else
                            <button class="w-full px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm" title="فاکتور هنوز روی WP نیست">
                                📤 ارسال فاکتور به WP CRM
                            </button>
                        @endif
                    </form>
                </div>
                @endif
                @endcan
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

            {{-- منبع داده سفارش (per-order source of truth) --}}
            @can('manage-crm-orders')
            @php
                $sot = $order->source_of_truth ?: 'auto';
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">منبع داده این سفارش</h2>
                    @if($sot === 'panel')
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 font-bold">پنل لاراول اصل</span>
                    @elseif($sot === 'crm')
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-bold">WP CRM اصل</span>
                    @else
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 font-bold">خودکار</span>
                    @endif
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3 leading-6">
                    مشخص می‌کند کدام سمت برای این سفارش اصل است.
                    «خودکار» تابع تنظیم سینک تکنسین می‌شود؛ بقیه روی همین سفارش override می‌کنند.
                </p>
                <form method="POST" action="{{ route('crm.orders.source-of-truth', $order) }}" class="flex gap-2">
                    @csrf
                    <select name="source_of_truth"
                            class="flex-1 px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                        @foreach(\Modules\CRM\Models\Order::SOURCE_OF_TRUTH_OPTIONS as $value => $label)
                            <option value="{{ $value }}" @selected($sot === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-lg text-sm font-medium">
                        ذخیره
                    </button>
                </form>
            </div>
            @endcan

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

                {{-- ─── پنل پیشنهاد هوشمند تکنسین (فاز ۱) ─── --}}
                @can('view-tech-suggestions')
                    @if(isset($suggestions) && $suggestions->count())
                        @php
                            $tierClasses = [
                                'excellent' => ['bg-emerald-50','border-emerald-300','text-emerald-700','bg-emerald-500'],
                                'good'      => ['bg-blue-50','border-blue-200','text-blue-700','bg-blue-500'],
                                'normal'    => ['bg-gray-50','border-gray-200','text-gray-700','bg-gray-500'],
                                'caution'   => ['bg-amber-50','border-amber-300','text-amber-800','bg-amber-500'],
                                'blocked'   => ['bg-rose-50','border-rose-300','text-rose-700','bg-rose-500'],
                            ];
                        @endphp
                        <div class="mb-4">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-4 h-4 text-brand-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <span class="text-xs font-bold text-brand-700">پیشنهاد هوشمند ({{ $suggestions->count() }} نفر)</span>
                            </div>
                            <div class="space-y-2">
                                @foreach($suggestions as $s)
                                    @php [$bg, $border, $textColor, $dotBg] = $tierClasses[$s->tier] ?? $tierClasses['normal']; @endphp
                                    <div class="p-3 rounded-lg border-2 {{ $bg }} {{ $border }}">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                                <div class="w-9 h-9 rounded-full {{ $dotBg }} text-white flex items-center justify-center font-bold text-sm flex-shrink-0">
                                                    {{ $s->score }}
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="text-sm font-bold truncate">
                                                        {{ trim($s->technician->firstname_tech ?: $s->technician->first_name) ?: '—' }}
                                                    </div>
                                                    <div class="text-[10px] {{ $textColor }} font-medium">{{ $s->label }}</div>
                                                </div>
                                            </div>
                                            <form action="{{ route('crm.orders.assign', $order) }}" method="POST" class="flex-shrink-0">
                                                @csrf
                                                <input type="hidden" name="technician_id" value="{{ $s->technician->id }}">
                                                <button class="px-3 py-1.5 bg-brand-700 text-white rounded-lg hover:bg-brand-800 text-xs font-bold">تخصیص</button>
                                            </form>
                                        </div>

                                        <div class="mt-2 grid grid-cols-3 gap-1 text-[10px]">
                                            <div class="bg-white/60 rounded px-1.5 py-1 text-center">
                                                <span class="text-gray-500">سفارش باز:</span>
                                                <span class="font-bold">{{ $s->now_orders }}@if($s->max_orders)/{{ $s->max_orders }}@endif</span>
                                            </div>
                                            <div class="bg-white/60 rounded px-1.5 py-1 text-center">
                                                <span class="text-gray-500">بدهی:</span>
                                                <span class="font-bold">{{ number_format($s->debt) }}</span>
                                            </div>
                                            <div class="bg-white/60 rounded px-1.5 py-1 text-center">
                                                <span class="text-gray-500">کنسلی:</span>
                                                <span class="font-bold">{{ $s->cancel_rate_pct }}%</span>
                                            </div>
                                        </div>

                                        @if(! empty($s->reasons))
                                            <div class="mt-1.5 text-[10px] text-gray-600 leading-5">
                                                {{ implode(' · ', $s->reasons) }}
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <p class="text-[10px] text-gray-400 mt-2 leading-5">
                                پیشنهادها بر اساس وزن‌های: سفارش‌باز ۳۰٪، بدهی ۲۵٪، رضایت ۲۰٪، کنسلی ۱۰٪، فعالیت ۱۰٪، پاسخ ۵٪.
                            </p>
                        </div>
                    @elseif($order->city_id || $order->brand_id || $order->device_id)
                        <div class="mb-4 p-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-800 leading-6">
                            هیچ تکنسینی با تگ‌های منطبق این سفارش (شهر/برند/دستگاه) پیدا نشد.
                            تکنسین‌های مرتبط را در صفحهٔ پروفایلشان تگ کنید تا در پیشنهاد ظاهر شوند.
                        </div>
                    @endif
                @endcan

                @can('assign-crm-technician')
                <form action="{{ route('crm.orders.assign', $order) }}" method="POST" class="space-y-2">
                    @csrf
                    <select name="technician_id" required
                            data-searchable data-placeholder="جستجوی نام یا موبایل تکنسین..."
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        <option value="">— یا انتخاب دستی از کل تکنسین‌ها —</option>
                        @foreach($technicians as $t)
                        <option value="{{ $t->id }}">{{ trim($t->first_name . ' ' . ($t->last_name ?? '')) }} @if($t->mobile) — {{ $t->mobile }} @endif</option>
                        @endforeach
                    </select>
                    <button class="w-full px-3 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm">تخصیص</button>
                </form>
                @endcan
                @endif
            </div>

            {{-- تغییر وضعیت — قوانین گذار هم‌ارز WP show_order.php --}}
            @can('change-crm-order-status')
            @php
                $allowedTransitions = $order->status instanceof \Modules\CRM\Enums\OrderStatus
                    ? $order->status->allowedTransitions()
                    : [];
                $isFinal = $order->status instanceof \Modules\CRM\Enums\OrderStatus
                    ? $order->status->isFinal()
                    : false;
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">تغییر وضعیت</h2>
                @if($isFinal)
                <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-300 flex items-start gap-2">
                    <svg class="w-4 h-4 mt-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <span>این سفارش در وضعیت نهایی است. برای تغییر، از «بازگشت سفارش» در پایین استفاده کنید.</span>
                </div>
                @else
                <form action="{{ route('crm.orders.status.change', $order) }}" method="POST" class="space-y-2">
                    @csrf
                    <select name="status" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        <option value="">— انتخاب وضعیت جدید —</option>
                        @foreach($allowedTransitions as $target)
                        <option value="{{ $target->value }}">{{ $target->label() }}</option>
                        @endforeach
                    </select>
                    <textarea name="note" rows="2" placeholder="توضیح (اختیاری)" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm"></textarea>
                    <button class="w-full px-3 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm">ثبت تغییر</button>
                </form>
                @endif
            </div>
            @endcan

            {{-- بازگشت سفارش — هم‌ارز returnOrderStatus در WP CRM.
                 فقط روی سفارش‌های نهایی (انجام کار/کنسل/رد/ایاب و ذهاب)
                 نمایش داده می‌شود؛ روی سفارش جریانی این گزینه معنا ندارد. --}}
            @can('change-crm-order-status')
            @if($order->status->isFinal())
            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-5">
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                    <h2 class="text-base font-bold text-amber-900 dark:text-amber-100">بازگشت سفارش</h2>
                </div>
                @if($order->return_type)
                <div class="bg-white dark:bg-gray-800 border border-amber-200 dark:border-amber-700 rounded-lg p-3 mb-3 text-sm">
                    <div class="font-bold text-amber-800 dark:text-amber-200 mb-1">
                        وضعیت بازگشت:
                        {{ (string) $order->return_type === '1' ? 'برگشت انجام شده' : 'برگشت کنسل شده' }}
                    </div>
                    @if($order->return_description)
                    <p class="text-gray-700 dark:text-gray-200 whitespace-pre-wrap">{{ $order->return_description }}</p>
                    @endif
                </div>
                @endif
                <p class="text-xs text-amber-800 dark:text-amber-200 mb-3">
                    برای بازگرداندن سفارش، نوع بازگشت را انتخاب و دلیل را بنویسید. وضعیت سفارش به «جدید» تغییر می‌کند.
                </p>
                <form action="{{ route('crm.orders.return', $order) }}" method="POST"
                      class="space-y-2"
                      onsubmit="return confirm('این کار وضعیت سفارش را به «جدید» برمی‌گرداند. ادامه دهم؟');">
                    @csrf
                    <select name="return_type" required
                            class="w-full px-3 py-2 border border-amber-300 dark:border-amber-700 dark:bg-gray-700 rounded-lg text-sm">
                        <option value="">— نوع بازگشت سفارش —</option>
                        <option value="1" @selected(old('return_type') === '1')>برگشت انجام شده</option>
                        <option value="2" @selected(old('return_type') === '2')>برگشت کنسل شده</option>
                    </select>
                    <textarea name="return_description" rows="3" required
                              placeholder="دلیل/توضیح بازگشت..."
                              class="w-full px-3 py-2 border border-amber-300 dark:border-amber-700 dark:bg-gray-700 rounded-lg text-sm">{{ old('return_description') }}</textarea>
                    @error('return_type') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('return_description') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    <button class="w-full px-3 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 text-sm font-bold">
                        بازگشت سفارش
                    </button>
                </form>
            </div>
            @endif
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
