@extends('crm::tech-panel.layout')

@section('title', 'سفارش ' . $order->order_code)

@php
    use Modules\CRM\Enums\OrderStatus;

    $hasItems = $order->items->isNotEmpty();
    $pieceList = is_array($order->piece_list) ? $order->piece_list : [];
    $buyPriceList = is_array($order->buy_price_list) ? $order->buy_price_list : [];
    $customerPriceList = is_array($order->customer_price_list) ? $order->customer_price_list : [];
    $hasWpPieces = !$hasItems && !empty($pieceList);
    $returnLogs = $order->wp_return_logs ?? [];
    $hasFinancial = $order->final_price || $order->total_invoice || $order->price_customer || $hasItems || $hasWpPieces;

    $techDescriptions = array_filter([
        'description_tech'  => $order->description_tech,
        'description_tech1' => $order->description_tech1,
        'description_tech2' => $order->description_tech2,
    ], fn($v) => filled($v));
    $descLabels = [
        'description_tech'  => 'یادداشت تکنسین',
        'description_tech1' => 'دلیل وضعیت نامشخص',
        'description_tech2' => 'لیست اقلام تحویل گرفته‌شده (رسید مشتری)',
    ];

    // برچسب رادیو هر وضعیت برای پنل تکنسین — هم‌سو با tech_show_order.php پنل WP.
    $statusActionLabels = [
        OrderStatus::Coordinated->value => 'هماهنگ کردن سفارش',
        OrderStatus::Open->value        => 'انتقال به تعمیرگاه',
        OrderStatus::Suspended->value   => 'وضعیت نامشخص',
        OrderStatus::Completed->value   => 'پایان سفارش',
        OrderStatus::Declined->value    => 'رد سفارش',
        OrderStatus::Transit->value     => 'فقط ایاب و ذهاب',
        OrderStatus::Cancelled->value   => 'کنسل سفارش',
    ];
    $statusDescPrompts = [
        OrderStatus::Coordinated->value => 'تاریخ و ساعت هماهنگی با مشتری را بنویسید',
        OrderStatus::Suspended->value   => 'دلیل نامشخص بودن وضعیت را شرح دهید',
        OrderStatus::Open->value        => 'لیست اقلامی که از مشتری تحویل گرفته‌اید (به‌عنوان رسید)',
        OrderStatus::Declined->value    => 'دلیل رد سفارش را بنویسید',
    ];

    // یادداشت‌های متعلق به همین تکنسین — مشابه فیلتر $author در پنل WP.
    $myNotes = collect($order->wp_notes ?? [])
        ->filter(fn($n) => isset($n['author']) && (int) $n['author'] === (int) $technician->id)
        ->values();
@endphp

@section('body')
<div class="min-h-screen pb-nav" style="background: #eef0f4;">
    @if(session('success'))
        <div class="mx-3 mt-3 px-4 py-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mx-3 mt-3 px-4 py-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M4.93 4.93l14.14 14.14M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mx-3 mt-3 px-4 py-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs leading-7">
            @foreach($errors->all() as $err)
                <div>• {{ $err }}</div>
            @endforeach
        </div>
    @endif

    {{-- ─────── Hero header ─────── --}}
    <div class="relative overflow-hidden rounded-b-[40px] pb-24"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
        <div class="flex items-center justify-between px-5 pt-5">
            <a href="{{ route('tech.orders') }}"
               class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20"
               aria-label="بازگشت">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/>
                </svg>
            </a>
            <div class="text-white font-bold text-base">جزئیات سفارش</div>
            <div class="w-10"></div>
        </div>

        <div class="px-6 mt-5 text-right">
            <div class="text-white/70 text-xs">کد سفارش</div>
            <div class="text-white text-xl font-bold mt-1" dir="ltr">{{ $order->order_code }}</div>

            <div class="flex items-center gap-2 mt-3 flex-wrap">
                <span class="px-3 py-1 text-xs font-bold rounded-full {{ $order->status->badgeClass() }}">
                    {{ $order->status->label() }}
                </span>
                @if($order->return_type)
                    <span class="px-3 py-1 text-xs font-bold rounded-full bg-orange-100 text-orange-800">
                        سفارش برگشتی
                    </span>
                @endif
                @if($order->visit_scheduled_at)
                    <span class="text-white/85 text-xs flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span dir="ltr">@jdatetime($order->visit_scheduled_at)</span>
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- ─────── Customer card ─────── --}}
    <div class="relative z-10 -mt-12 mx-3 bg-white rounded-[24px] shadow-lg p-4">
        <div class="text-[11px] text-gray-400 mb-2">مشتری</div>
        <div class="font-bold text-gray-900 text-base">
            {{ $order->customer_name ?: ($order->customer->display_name ?? '—') }}
        </div>

        <div class="mt-3 space-y-2">
            @if($order->customer_mobile)
                <a href="tel:{{ $order->customer_mobile }}"
                   class="flex items-center justify-between bg-emerald-50 rounded-xl px-3 py-2.5 active:bg-emerald-100">
                    <span class="text-emerald-700 text-xs font-medium">تماس با موبایل</span>
                    <span class="flex items-center gap-2">
                        <span dir="ltr" class="text-emerald-900 font-bold text-sm">{{ $order->customer_mobile }}</span>
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                    </span>
                </a>
            @endif
            @if($order->customer_phone)
                <a href="tel:{{ $order->customer_phone }}"
                   class="flex items-center justify-between bg-blue-50 rounded-xl px-3 py-2.5 active:bg-blue-100">
                    <span class="text-blue-700 text-xs font-medium">تماس با تلفن ثابت</span>
                    <span class="flex items-center gap-2">
                        <span dir="ltr" class="text-blue-900 font-bold text-sm">{{ $order->customer_phone }}</span>
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                    </span>
                </a>
            @endif
        </div>

        @if($order->subscription)
            <div class="mt-3 flex items-center justify-between text-xs">
                <span class="text-gray-400">کد اشتراک</span>
                <span class="text-gray-800 font-medium" dir="ltr">{{ $order->subscription }}</span>
            </div>
        @endif
    </div>

    {{-- ─────── Address card ─────── --}}
    @if($order->address || $order->province || $order->city || $order->postal_code)
        <div class="mx-3 mt-3 bg-white rounded-[24px] shadow-sm p-4">
            <div class="text-[11px] text-gray-400 mb-2">آدرس</div>
            @if($order->province || $order->city)
                <div class="text-sm text-gray-700">
                    {{ $order->province?->name }}{{ $order->province && $order->city ? ' / ' : '' }}{{ $order->city?->name }}
                </div>
            @endif
            @if($order->address)
                <div class="text-sm text-gray-900 mt-1.5 leading-7">{{ $order->address }}</div>
            @endif
            @if($order->postal_code)
                <div class="mt-2.5 flex items-center justify-between text-xs">
                    <span class="text-gray-400">کدپستی</span>
                    <span class="text-gray-800 font-medium" dir="ltr">{{ $order->postal_code }}</span>
                </div>
            @endif
        </div>
    @endif

    {{-- ─────── Device / problem card ─────── --}}
    @if($order->brand || $order->device || $order->problem_title || $order->problem_description)
        <div class="mx-3 mt-3 bg-white rounded-[24px] shadow-sm p-4">
            <div class="text-[11px] text-gray-400 mb-2">دستگاه و عیب</div>
            @if($order->brand || $order->device)
                <div class="text-sm font-bold text-gray-900">
                    {{ $order->brand?->name }}{{ $order->brand && $order->device ? ' / ' : '' }}{{ $order->device?->name }}
                </div>
            @endif
            @if($order->problem_title)
                <div class="text-sm text-gray-800 mt-2">{{ $order->problem_title }}</div>
            @endif
            @if($order->problem_description)
                <div class="text-xs text-gray-600 mt-1.5 leading-7">{{ $order->problem_description }}</div>
            @endif
        </div>
    @endif

    {{-- ─────── Tech descriptions ─────── --}}
    @if(count($techDescriptions))
        <div class="mx-3 mt-3 bg-white rounded-[24px] shadow-sm p-4">
            <div class="text-[11px] text-gray-400 mb-2">یادداشت‌های شما</div>
            <div class="space-y-3">
                @foreach($techDescriptions as $key => $value)
                    <div>
                        <div class="text-[11px] font-medium text-gray-500">{{ $descLabels[$key] ?? $key }}</div>
                        <div class="text-sm text-gray-800 leading-7 mt-1">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ─────── Status change form ─────── --}}
    @if(count($allowedStatuses))
        @php
            $existingPieces = [];
            $titles = is_array($order->piece_list) ? $order->piece_list : [];
            $buys = is_array($order->buy_price_list) ? $order->buy_price_list : [];
            $sells = is_array($order->customer_price_list) ? $order->customer_price_list : [];
            foreach ($titles as $i => $t) {
                if (filled($t)) {
                    $existingPieces[] = [
                        'title' => is_string($t) ? $t : (string) ($t['title'] ?? ''),
                        'buy' => (int) ($buys[$i] ?? 0),
                        'sell' => (int) ($sells[$i] ?? 0),
                    ];
                }
            }
            if (empty($existingPieces)) {
                $existingPieces = [['title' => '', 'buy' => 0, 'sell' => 0]];
            }
        @endphp

        <div class="mx-3 mt-3 bg-white rounded-[24px] shadow-sm p-4"
             x-data="{
                selected: '{{ old('status', '') }}',
                pieces: {{ \Illuminate\Support\Js::from($existingPieces) }},
             }">
            <div class="text-[11px] text-gray-400 mb-3">تغییر وضعیت سفارش</div>

            <form method="POST" action="{{ route('tech.orders.update-status', $order) }}"
                  enctype="multipart/form-data" class="space-y-2">
                @csrf

                <div class="space-y-2">
                    @foreach($allowedStatuses as $option)
                        <label class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl border cursor-pointer transition"
                               :class="selected === '{{ $option->value }}' ? 'border-brand-600 bg-brand-50' : 'border-gray-200 bg-white'">
                            <input type="radio" name="status" value="{{ $option->value }}"
                                   x-model="selected"
                                   class="w-4 h-4 accent-brand-700">
                            <span class="text-sm text-gray-800 font-medium">
                                {{ $statusActionLabels[$option->value] ?? $option->label() }}
                            </span>
                            <span class="ms-auto px-2 py-0.5 text-[10px] font-bold rounded-full {{ $option->badgeClass() }}">
                                {{ $option->label() }}
                            </span>
                        </label>
                    @endforeach
                </div>

                {{-- توضیحات وابسته به وضعیت انتخاب‌شده --}}
                @foreach($statusDescPrompts as $value => $prompt)
                    <div x-show="selected === '{{ $value }}'" x-cloak class="pt-2">
                        <label class="text-[11px] text-gray-500 mb-1 block">{{ $prompt }}</label>
                        <textarea name="description" rows="3"
                                  class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm text-gray-900 placeholder-gray-400 focus:bg-white focus:border-brand-400 focus:outline-none leading-7"
                                  placeholder="...">{{ old('description') }}</textarea>
                    </div>
                @endforeach

                {{-- ── بلاک فاکتور — فقط هنگام انتخاب «پایان سفارش» ─── --}}
                <div x-show="selected === '{{ OrderStatus::Completed->value }}'" x-cloak class="pt-3 border-t border-gray-100 mt-3 space-y-3">
                    <div class="text-[11px] text-brand-700 font-bold">جزئیات فاکتور</div>

                    {{-- price_customer + total_invoice --}}
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[11px] text-gray-500 mb-1 block">مبلغ کل صورت‌حساب (تومان)</label>
                            <input type="number" name="price_customer" min="0" step="1000" inputmode="numeric"
                                   value="{{ old('price_customer', $order->price_customer) }}"
                                   class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:border-brand-400 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-[11px] text-gray-500 mb-1 block">مانده پس از کسر هزینه‌ها</label>
                            <input type="number" name="total_invoice" min="0" step="1000" inputmode="numeric"
                                   value="{{ old('total_invoice', $order->total_invoice) }}"
                                   class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:border-brand-400 focus:outline-none">
                        </div>
                    </div>

                    {{-- Pieces repeater --}}
                    <div>
                        <label class="text-[11px] text-gray-500 mb-1 block">قطعات استفاده‌شده</label>
                        <div class="space-y-2">
                            <template x-for="(p, i) in pieces" :key="i">
                                <div class="bg-gray-50 rounded-xl p-2.5 space-y-1.5">
                                    <input type="text" :name="`pieces[${i}][title]`" x-model="p.title"
                                           placeholder="نام قطعه"
                                           class="w-full bg-white border border-gray-200 rounded-lg py-2 px-2.5 text-sm focus:border-brand-400 focus:outline-none">
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="number" :name="`pieces[${i}][buy_price]`" x-model.number="p.buy" min="0" step="1000" inputmode="numeric"
                                               placeholder="قیمت خرید"
                                               class="w-full bg-white border border-gray-200 rounded-lg py-2 px-2.5 text-sm focus:border-brand-400 focus:outline-none">
                                        <input type="number" :name="`pieces[${i}][customer_price]`" x-model.number="p.sell" min="0" step="1000" inputmode="numeric"
                                               placeholder="قیمت فروش"
                                               class="w-full bg-white border border-gray-200 rounded-lg py-2 px-2.5 text-sm focus:border-brand-400 focus:outline-none">
                                    </div>
                                    <button type="button" @click="pieces.splice(i, 1)"
                                            x-show="pieces.length > 1"
                                            class="text-rose-600 text-[11px]">حذف این قطعه</button>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="pieces.push({title:'', buy:0, sell:0})"
                                class="mt-2 w-full py-2 rounded-xl bg-gray-100 text-gray-700 text-xs font-bold">
                            + افزودن قطعه
                        </button>
                    </div>

                    {{-- hire / transportation / discount --}}
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="text-[11px] text-gray-500 mb-1 block">اجرت</label>
                            <input type="number" name="hire" min="0" step="1000" inputmode="numeric"
                                   value="{{ old('hire', $order->hire) }}"
                                   class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2 px-2.5 text-sm focus:bg-white focus:border-brand-400 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-[11px] text-gray-500 mb-1 block">ایاب و ذهاب</label>
                            <input type="number" name="transportation" min="0" step="1000" inputmode="numeric"
                                   value="{{ old('transportation', $order->transportation) }}"
                                   class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2 px-2.5 text-sm focus:bg-white focus:border-brand-400 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-[11px] text-gray-500 mb-1 block">تخفیف</label>
                            <input type="number" name="discount" min="0" step="1000" inputmode="numeric"
                                   value="{{ old('discount', $order->discount) }}"
                                   class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2 px-2.5 text-sm focus:bg-white focus:border-brand-400 focus:outline-none">
                        </div>
                    </div>

                    {{-- device_img1 upload --}}
                    <div>
                        <label class="text-[11px] text-gray-500 mb-1 block">عکس دستگاه (پس از تعمیر)</label>
                        @if($order->device_img1)
                            <a href="{{ asset('storage/' . $order->device_img1) }}" target="_blank"
                               class="inline-flex items-center gap-1.5 text-xs text-brand-700 mb-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                                مشاهده عکس فعلی
                            </a>
                        @endif
                        <input type="file" name="device_img1" accept="image/*"
                               class="block w-full text-xs text-gray-600 file:ms-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-bold file:text-xs">
                    </div>

                    {{-- invoice_descripotion --}}
                    <div>
                        <label class="text-[11px] text-gray-500 mb-1 block">توضیحات فاکتور</label>
                        <textarea name="invoice_descripotion" rows="2"
                                  class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:border-brand-400 focus:outline-none leading-7">{{ old('invoice_descripotion', $order->invoice_descripotion) }}</textarea>
                    </div>

                    {{-- save_as_draft --}}
                    <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                        <input type="hidden" name="save_as_draft" value="0">
                        <input type="checkbox" name="save_as_draft" value="1"
                               {{ old('save_as_draft', $order->save_as_draft) ? 'checked' : '' }}
                               class="w-4 h-4 accent-brand-700">
                        ذخیره به‌عنوان پیش‌نویس (هنوز نهایی نشود)
                    </label>
                </div>

                <button type="submit"
                        x-bind:disabled="!selected"
                        class="w-full mt-3 py-3 rounded-xl text-white font-bold text-sm transition disabled:opacity-50 disabled:cursor-not-allowed"
                        style="background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);">
                    ثبت تغییر وضعیت
                </button>
            </form>
        </div>

    @elseif($order->status->isFinal())
        <div class="mx-3 mt-3 px-4 py-3 rounded-2xl bg-gray-100 border border-gray-200 text-gray-600 text-xs flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            این سفارش در وضعیت نهایی «{{ $order->status->label() }}» قرار دارد و قابل ویرایش نیست.
        </div>
    @endif

    {{-- ─────── Send "ready for delivery" SMS — only for completed orders + capable techs ─────── --}}
    @if($technician->ready_for_delivery && $order->status === OrderStatus::Completed)
        <div class="mx-3 mt-3 bg-white rounded-[24px] shadow-sm p-4">
            <div class="text-[11px] text-gray-400 mb-2">اطلاع‌رسانی به مشتری</div>
            <p class="text-xs text-gray-600 leading-7 mb-3">
                با کلیک روی دکمه زیر، پیامک «آماده تحویل» برای مشتری ارسال می‌شود.
            </p>
            <form method="POST" action="{{ route('tech.orders.deliver-sms', $order) }}">
                @csrf
                <button type="submit"
                        class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm transition">
                    ارسال پیامک آماده تحویل
                </button>
            </form>
        </div>
    @endif

    {{-- ─────── Notes (own notes + add form) ─────── --}}
    <div class="mx-3 mt-3 bg-white rounded-[24px] shadow-sm p-4">
        <div class="text-[11px] text-gray-400 mb-3">یادداشت‌های من برای این سفارش</div>

        @if($myNotes->count())
            <ul class="space-y-2.5 mb-3">
                @foreach($myNotes as $note)
                    <li class="bg-gray-50 rounded-xl p-3">
                        <div class="text-sm text-gray-800 leading-7">{{ $note['content'] ?? '' }}</div>
                        @if(!empty($note['date']))
                            <div class="text-[10px] text-gray-400 mt-1.5" dir="ltr">{{ $note['date'] }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <div class="text-xs text-gray-400 mb-3">هنوز یادداشتی ثبت نکرده‌اید.</div>
        @endif

        <form method="POST" action="{{ route('tech.orders.add-note', $order) }}">
            @csrf
            <textarea name="note" rows="3" required maxlength="2000"
                      placeholder="یادداشت جدید برای این سفارش..."
                      class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm text-gray-900 placeholder-gray-400 focus:bg-white focus:border-brand-400 focus:outline-none leading-7">{{ old('note') }}</textarea>
            <button type="submit"
                    class="w-full mt-2.5 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-sm transition">
                ثبت یادداشت
            </button>
        </form>
    </div>

    {{-- ─────── Pieces / invoice ─────── --}}
    @if($hasFinancial)
        <div class="mx-3 mt-3 bg-white rounded-[24px] shadow-sm p-4">
            <div class="text-[11px] text-gray-400 mb-3">قطعات و فاکتور</div>

            {{-- Native items table (Laravel-side) --}}
            @if($hasItems)
                <div class="space-y-2">
                    @foreach($order->items as $item)
                        <div class="flex items-center justify-between text-sm">
                            <div class="text-gray-800">
                                {{ $item->title }}
                                <span class="text-gray-400 text-xs">({{ $item->typeLabel() }})</span>
                            </div>
                            <div class="text-gray-700 text-xs whitespace-nowrap">
                                {{ $item->quantity }} × {{ number_format($item->unit_price) }}
                                = <b>{{ number_format($item->total_price) }}</b>
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif($hasWpPieces)
                {{-- WP-imported parallel arrays piece_list / buy_price_list / customer_price_list --}}
                <div class="space-y-2">
                    @foreach($pieceList as $i => $piece)
                        @php
                            $title = is_string($piece) ? $piece : (is_array($piece) ? ($piece['title'] ?? '') : '');
                            $buy = $buyPriceList[$i] ?? null;
                            $sell = $customerPriceList[$i] ?? null;
                        @endphp
                        @if($title)
                            <div class="flex items-center justify-between text-sm">
                                <div class="text-gray-800">{{ $title }}</div>
                                <div class="text-gray-700 text-xs whitespace-nowrap">
                                    @if($buy !== null) خرید: {{ number_format((int) $buy) }} @endif
                                    @if($sell !== null) · فروش: {{ number_format((int) $sell) }} @endif
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            {{-- Financial summary numbers --}}
            <div class="mt-4 pt-3 border-t border-gray-100 space-y-1.5 text-xs">
                @if($order->price_customer)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">جمع کل صورت‌حساب</span>
                        <span class="text-gray-900 font-bold">{{ number_format((int) $order->price_customer) }} <span class="font-normal text-gray-400">تومان</span></span>
                    </div>
                @endif
                @if($order->cost_price)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">هزینه قطعات</span>
                        <span class="text-gray-700">{{ number_format((int) $order->cost_price) }} <span class="text-gray-400">تومان</span></span>
                    </div>
                @endif
                @if($order->total_invoice)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">مانده پس از کسر هزینه‌ها</span>
                        <span class="text-gray-900 font-bold">{{ number_format((int) $order->total_invoice) }} <span class="font-normal text-gray-400">تومان</span></span>
                    </div>
                @endif
                @if($order->hire)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">اجرت</span>
                        <span class="text-gray-700">{{ number_format((int) $order->hire) }} <span class="text-gray-400">تومان</span></span>
                    </div>
                @endif
                @if($order->transportation)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">ایاب و ذهاب</span>
                        <span class="text-gray-700">{{ number_format((int) $order->transportation) }} <span class="text-gray-400">تومان</span></span>
                    </div>
                @endif
                @if($order->discount)
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500">تخفیف</span>
                        <span class="text-rose-600">−{{ number_format((int) $order->discount) }} <span class="text-gray-400">تومان</span></span>
                    </div>
                @endif
                @if($order->final_price)
                    <div class="flex items-center justify-between pt-2 mt-2 border-t border-gray-100">
                        <span class="text-gray-700 font-bold">مبلغ نهایی</span>
                        <span class="text-emerald-700 font-bold text-sm">{{ number_format((int) $order->final_price) }} <span class="font-normal text-gray-400">تومان</span></span>
                    </div>
                @endif
            </div>

            @if($order->invoice_descripotion)
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <div class="text-[11px] text-gray-400 mb-1">توضیحات فاکتور</div>
                    <div class="text-xs text-gray-700 leading-7">{{ $order->invoice_descripotion }}</div>
                </div>
            @endif
        </div>
    @endif

    {{-- ─────── Status history ─────── --}}
    @if($order->statusLogs->isNotEmpty())
        <div class="mx-3 mt-3 bg-white rounded-[24px] shadow-sm p-4">
            <div class="text-[11px] text-gray-400 mb-3">تاریخچه وضعیت</div>
            <ol class="space-y-3">
                @foreach($order->statusLogs as $log)
                    @php $toEnum = OrderStatus::tryFrom($log->to_status); @endphp
                    <li class="flex items-start gap-3">
                        <span class="mt-1 w-2 h-2 rounded-full bg-brand-500 flex-shrink-0"></span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                @if($log->fromLabel())
                                    <span class="text-gray-400 text-xs">{{ $log->fromLabel() }}</span>
                                    <span class="text-gray-300 text-xs">←</span>
                                @endif
                                <span class="text-xs font-bold px-2 py-0.5 rounded-full {{ $toEnum?->badgeClass() ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ $log->toLabel() }}
                                </span>
                            </div>
                            @if($log->note)
                                <div class="text-xs text-gray-700 mt-1.5 leading-7">{{ $log->note }}</div>
                            @endif
                            <div class="text-[10px] text-gray-400 mt-1" dir="ltr">
                                @jdatetime($log->created_at)
                                @if($log->changer)
                                    <span class="text-gray-300">·</span>
                                    <span dir="rtl" class="text-gray-500">{{ $log->changer->name }}</span>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    {{-- ─────── Return logs (WP-imported) ─────── --}}
    @if(!empty($returnLogs))
        <div class="mx-3 mt-3 bg-white rounded-[24px] shadow-sm p-4">
            <div class="text-[11px] text-orange-500 mb-3 font-bold">تاریخچه برگشت سفارش</div>
            <div class="space-y-3">
                @foreach($returnLogs as $log)
                    <div class="bg-orange-50 border border-orange-100 rounded-xl p-3">
                        @if(!empty($log['return_type_message']))
                            <div class="text-xs font-bold text-orange-800">{{ $log['return_type_message'] }}</div>
                        @endif
                        @if(!empty($log['return_description']))
                            <div class="text-xs text-gray-700 mt-1.5 leading-7">{{ $log['return_description'] }}</div>
                        @endif
                        @if(!empty($log['cancel_desc']))
                            <div class="text-xs text-rose-700 mt-1.5 leading-7">{{ $log['cancel_desc'] }}</div>
                        @endif
                        @if(!empty($log['date']))
                            <div class="text-[10px] text-gray-400 mt-2" dir="ltr">{{ $log['date'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="h-4"></div>
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.orders'])
@endsection
