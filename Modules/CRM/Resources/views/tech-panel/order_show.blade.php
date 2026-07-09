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
        OrderStatus::Transit->value     => 'دلیل ثبت ایاب و ذهاب و توضیحات لازم را بنویسید',
    ];

    // یادداشت‌های متعلق به همین تکنسین — مشابه فیلتر $author در پنل WP.
    $myNotes = collect($order->wp_notes ?? [])
        ->filter(fn($n) => isset($n['author']) && (int) $n['author'] === (int) $technician->id)
        ->values();

    // قفل دسترسی به اطلاعات تماس و امکان ویرایش پس از نهایی شدن سفارش —
    // جلوگیری از سواستفاده/تماس مستقیم تکنسین با مشتری بعد از تسویه.
    $orderIsLocked = $order->status->isFinal();
    $maskContact = function (?string $number): ?string {
        if (! $number) return null;
        $clean = preg_replace('/\s+/', '', $number);
        $len = strlen($clean);
        if ($len <= 7) return str_repeat('*', $len);
        return substr($clean, 0, 4) . str_repeat('*', $len - 8) . substr($clean, -4);
    };
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
    @if(request('proforma'))
        <div class="mx-3 mt-3 px-4 py-3 rounded-2xl bg-indigo-50 border border-indigo-200 text-indigo-800 text-sm">
            پیش‌فاکتور <b dir="ltr">{{ request('proforma') }}</b> تأیید شد. مبلغِ پیشنهادی
            (<span dir="ltr">{{ number_format((int) request('price', 0)) }}</span> تومان) در فرمِ تکمیل پیش‌پر شده —
            وضعیت را «پایان سفارش» بگذارید و ثبت کنید تا فاکتور نهایی صادر شود.
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
                @if($orderIsLocked)
                    {{-- سفارش نهایی: شماره ماسک شده، بدون tel: link --}}
                    <div class="flex items-center justify-between bg-gray-100 rounded-xl px-3 py-2.5">
                        <span class="text-gray-500 text-xs font-medium">موبایل مشتری (محرمانه)</span>
                        <span dir="ltr" class="text-gray-500 font-bold text-sm">{{ $maskContact($order->customer_mobile) }}</span>
                    </div>
                @else
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
            @endif
            @if($order->customer_phone)
                @if($orderIsLocked)
                    <div class="flex items-center justify-between bg-gray-100 rounded-xl px-3 py-2.5">
                        <span class="text-gray-500 text-xs font-medium">تلفن ثابت (محرمانه)</span>
                        <span dir="ltr" class="text-gray-500 font-bold text-sm">{{ $maskContact($order->customer_phone) }}</span>
                    </div>
                @else
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
            @endif
            @if($orderIsLocked)
                <p class="text-[10px] text-gray-400 leading-6 mt-1">
                    این سفارش به وضعیت نهایی رسیده است. برای حفظ حریم خصوصی، اطلاعات تماس مشتری برای تماس مستقیم در دسترس نیست.
                </p>
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
    @php
        // آدرسِ انتخاب‌شدهٔ مشتری از اپ (نشان/نقشه) با مختصات، در اولویت است؛
        // اگر نبود، snapshot روی خودِ سفارش استفاده می‌شود.
        $addr = $order->customerAddress;
        $addrText = ($addr && filled($addr->full_address)) ? $addr->full_address : $order->address;
        $addrProvince = $addr?->province?->name ?? $order->province?->name;
        $addrCity = $addr?->city?->name ?? $order->city?->name;
        $addrDistrict = $addr?->district?->name;
        $addrPostal = $addr?->postal_code ?: $order->postal_code;
        $hasCoords = $addr && $addr->hasCoordinates();
        $lat = $hasCoords ? $addr->latitude : null;
        $lng = $hasCoords ? $addr->longitude : null;
    @endphp
    @if($addrText || $addrProvince || $addrCity || $addrPostal || $hasCoords)
        <div class="mx-3 mt-3 bg-white rounded-[24px] shadow-sm p-4">
            <div class="text-[11px] text-gray-400 mb-2">آدرس مشتری</div>
            @if($addrProvince || $addrCity)
                <div class="text-sm text-gray-700">
                    {{ $addrProvince }}{{ $addrProvince && $addrCity ? ' / ' : '' }}{{ $addrCity }}@if($addrDistrict) — {{ $addrDistrict }}@endif
                </div>
            @endif
            @if($addrText)
                <div class="text-sm text-gray-900 mt-1.5 leading-7">{{ $addrText }}</div>
            @endif
            @if($addrPostal)
                <div class="mt-2.5 flex items-center justify-between text-xs">
                    <span class="text-gray-400">کدپستی</span>
                    <span class="text-gray-800 font-medium" dir="ltr">{{ $addrPostal }}</span>
                </div>
            @endif

            {{-- موقعیتِ ثبت‌شده روی نقشه (اپ مشتری) — مسیریابی برای تکنسین --}}
            @if($hasCoords)
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] text-emerald-700 font-bold flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            موقعیت روی نقشه
                        </span>
                        <span dir="ltr" class="text-[10px] text-gray-400 font-mono">{{ $lat }}, {{ $lng }}</span>
                    </div>

                    {{-- پیش‌نمایشِ نقشه (بدونِ کلید — OpenStreetMap) --}}
                    <div class="rounded-xl overflow-hidden border border-gray-200 mb-2">
                        <iframe class="w-full h-40" loading="lazy" referrerpolicy="no-referrer"
                                src="https://www.openstreetmap.org/export/embed.html?bbox={{ $lng - 0.004 }}%2C{{ $lat - 0.0025 }}%2C{{ $lng + 0.004 }}%2C{{ $lat + 0.0025 }}&layer=mapnik&marker={{ $lat }}%2C{{ $lng }}"></iframe>
                    </div>

                    {{-- دکمه‌های مسیریابی — نشان / گوگل‌مپ / بلد --}}
                    <div class="grid grid-cols-3 gap-2">
                        <a href="https://neshan.org/maps/@{{ $lat }},{{ $lng }},16z" target="_blank" rel="noopener"
                           class="flex flex-col items-center justify-center gap-1 py-2.5 rounded-xl bg-emerald-50 text-emerald-700 text-[11px] font-bold active:bg-emerald-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                            نشان
                        </a>
                        <a href="https://www.google.com/maps/search/?api=1&query={{ $lat }},{{ $lng }}" target="_blank" rel="noopener"
                           class="flex flex-col items-center justify-center gap-1 py-2.5 rounded-xl bg-blue-50 text-blue-700 text-[11px] font-bold active:bg-blue-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            گوگل‌مپ
                        </a>
                        <a href="https://balad.ir/location?latitude={{ $lat }}&longitude={{ $lng }}&zoom=16" target="_blank" rel="noopener"
                           class="flex flex-col items-center justify-center gap-1 py-2.5 rounded-xl bg-orange-50 text-orange-700 text-[11px] font-bold active:bg-orange-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/></svg>
                            بلد
                        </a>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1.5 leading-5">با زدنِ هر دکمه، مسیریابی به موقعیتِ ثبت‌شدهٔ مشتری باز می‌شود.</p>
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

    {{-- ─────── Proforma (پیش‌فاکتور) ─────── --}}
    <div class="mx-3 mt-3 bg-white rounded-[24px] shadow-sm p-4">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-bold text-gray-900">پیش‌فاکتور</div>
                    <div class="text-[11px] text-gray-500">برآوردِ قیمت را قبل از تکمیل برای مشتری بفرستید.</div>
                </div>
            </div>
        </div>

        @if($proformas->isNotEmpty())
            <div class="space-y-2 mb-3">
                @foreach($proformas as $pf)
                    <a href="{{ route('tech.proformas.show', $pf) }}"
                       class="flex items-center justify-between bg-gray-50 rounded-xl px-3 py-2.5 active:bg-gray-100">
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-gray-800" dir="ltr">{{ $pf->proforma_code }}</div>
                            <span class="text-[10px] px-2 py-0.5 rounded-full {{ $pf->statusBadge() }}">{{ $pf->statusLabel() }}</span>
                        </div>
                        <div class="text-sm font-bold text-gray-900" dir="ltr">{{ number_format((int) $pf->total) }}</div>
                    </a>
                @endforeach
            </div>
        @endif

        @if(! $order->status->isFinal())
            <a href="{{ route('tech.proformas.create', ['order_id' => $order->id]) }}"
               class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl bg-indigo-600 text-white font-bold text-sm active:scale-95 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                پیش‌فاکتور جدید برای این سفارش
            </a>
        @elseif($proformas->isEmpty())
            <p class="text-[11px] text-gray-400">این سفارش نهایی شده و پیش‌فاکتوری ثبت نشده است.</p>
        @endif
    </div>

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

    {{-- ─────── Schedule visit form ───────
         وقتی تکنسین با مشتری هماهنگ می‌کند، روز و بازه ساعت را ثبت می‌کند.
         این مقدار در visit_scheduled_at سفارش می‌نشیند و در صفحهٔ مشاهدهٔ
         سفارش ادمین/اپراتور هم نمایش داده می‌شود. --}}
    @if(! $order->status->isFinal())
        @php
            $latin = ['0','1','2','3','4','5','6','7','8','9'];
            $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
            $weekdaysFa = [
                'Saturday' => 'شنبه', 'Sunday' => 'یکشنبه', 'Monday' => 'دوشنبه',
                'Tuesday' => 'سه‌شنبه', 'Wednesday' => 'چهارشنبه',
                'Thursday' => 'پنجشنبه', 'Friday' => 'جمعه',
            ];
            // ۷ روز پیشِ‌رو
            $visitDays = [];
            for ($i = 0; $i < 7; $i++) {
                $dt = now()->addDays($i)->startOfDay();
                $j = \Morilog\Jalali\Jalalian::fromCarbon($dt);
                $visitDays[] = [
                    'value' => $dt->format('Y-m-d'),
                    'weekday' => $weekdaysFa[$dt->format('l')] ?? '',
                    'day' => str_replace($latin, $persian, $j->format('d')),
                    'month' => $j->format('F'),
                ];
            }
            $visitSlots = \Modules\CRM\Livewire\OrderWizard::VISIT_SLOTS;
            $currentVisitDate = $order->visit_scheduled_at?->format('Y-m-d');
            $currentVisitTime = $order->visit_scheduled_at?->format('H:i:s');
            // پیدا کردن slot فعلی از روی ساعت ذخیره‌شده
            $currentSlotKey = null;
            foreach ($visitSlots as $k => $s) {
                if ($s['start'] === $currentVisitTime) { $currentSlotKey = $k; break; }
            }
        @endphp

        <div class="mx-3 mt-3 bg-white rounded-[24px] shadow-sm p-4">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-bold text-gray-900">هماهنگی زمان مراجعه</div>
                    <div class="text-[11px] text-gray-500">با مشتری هماهنگ کنید و روز/بازهٔ مراجعه را ثبت کنید.</div>
                </div>
            </div>

            @if($order->visit_scheduled_at)
                <div class="mb-3 px-3 py-2 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center justify-between gap-2">
                    <span class="font-medium">فعلاً ثبت‌شده:</span>
                    <span dir="ltr" class="font-bold">@jdatetime($order->visit_scheduled_at)</span>
                </div>
            @endif

            <form method="POST" action="{{ route('tech.orders.schedule-visit', $order) }}" class="space-y-3">
                @csrf

                {{-- روز --}}
                <div>
                    <div class="text-[11px] text-gray-500 mb-2">روز مراجعه</div>
                    <div class="grid grid-cols-4 gap-1.5">
                        @foreach($visitDays as $d)
                            <label class="cursor-pointer">
                                <input type="radio" name="visit_date" value="{{ $d['value'] }}"
                                       @checked($currentVisitDate === $d['value'])
                                       class="peer sr-only">
                                <div class="p-2 border-2 border-gray-200 rounded-xl peer-checked:border-emerald-500 peer-checked:bg-emerald-50 transition text-center">
                                    <div class="text-[10px] text-gray-500">{{ $d['weekday'] }}</div>
                                    <div class="text-base font-bold text-gray-900 my-0.5">{{ $d['day'] }}</div>
                                    <div class="text-[10px] text-gray-500">{{ $d['month'] }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('visit_date') <div class="text-[11px] text-rose-600 mt-1">{{ $message }}</div> @enderror
                </div>

                {{-- بازه ساعت --}}
                <div>
                    <div class="text-[11px] text-gray-500 mb-2">بازه ساعت</div>
                    <div class="grid grid-cols-2 gap-1.5">
                        @foreach($visitSlots as $key => $slot)
                            <label class="cursor-pointer">
                                <input type="radio" name="visit_slot" value="{{ $key }}"
                                       @checked($currentSlotKey === $key)
                                       class="peer sr-only">
                                <div class="p-2.5 border-2 border-gray-200 rounded-xl peer-checked:border-emerald-500 peer-checked:bg-emerald-50 transition text-center text-xs font-medium text-gray-800">
                                    {{ $slot['label'] }}
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('visit_slot') <div class="text-[11px] text-rose-600 mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit"
                            class="flex-1 py-2.5 rounded-xl text-white font-bold text-sm transition"
                            style="background: linear-gradient(135deg, #059669 0%, #10b981 100%);">
                        ثبت زمان مراجعه
                    </button>
                    @if($order->visit_scheduled_at)
                        <button type="submit" name="clear" value="1"
                                onclick="return confirm('زمان مراجعه پاک شود؟');"
                                class="px-3 py-2.5 rounded-xl bg-gray-100 text-gray-700 text-xs font-medium">
                            پاک کردن
                        </button>
                    @endif
                </div>
            </form>
        </div>
    @endif

    {{-- ─────── Freeze banner (در حالت به‌روزرسانی پنل) ─────── --}}
    @if(($isFrozen ?? false) && ! $order->status->isFinal())
        <div class="mx-3 mt-3 px-4 py-3 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 text-xs leading-7">
            ⏳ پنل در حال به‌روزرسانی است — تا اطلاع ثانوی، <strong>نهایی‌سازی سفارش</strong> (پایان کار / کنسل / رد / ایاب و ذهاب) موقتاً غیرفعال است. می‌توانید همچنان وضعیت را به «هماهنگی»، «انتقال به تعمیرگاه» یا «نامشخص» تغییر دهید.
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
                priceCustomer: {{ (int) old('price_customer', request('price', $order->price_customer ?? 0)) }},
                hire: 0, transportation: 0, discount: 0,
                techPercent: {{ (int) ($technician->percent ?? 0) }},
                techPerOfAll: {{ (int) ($technician->tech_per_of_all ?? 0) }},
                techCalcType: '{{ (string) ($technician->type_of_calc_tech ?? '') }}',
                saveAsDraft: {{ old('save_as_draft', $order->save_as_draft) ? 'true' : 'false' }},
                isReturned: {{ is_null($order->return_type) ? 'false' : 'true' }},
                get partsCost() { return this.pieces.reduce((s, p) => s + (Number(p.buy)||0), 0); },
                get totalInvoice() { return Math.max(0, (Number(this.priceCustomer)||0) - this.partsCost); },
                // جمع کل فاکتور نباید کمتر از هزینهٔ قطعات باشد (مگر پیش‌نویس/برگشتی).
                get invoiceBelowParts() {
                    return this.selected === '{{ OrderStatus::Completed->value }}'
                        && !this.saveAsDraft && !this.isReturned
                        && (Number(this.priceCustomer)||0) < this.partsCost;
                },
                // سهم تکنسین = price_customer × (۱۰۰ − درصد شرکت) / ۱۰۰
                // درصد روی تکنسین («percent» یا «tech_per_of_all» در حالت internal)
                // معنی «سهم شرکت از کل» را دارد، نه «سهم تکنسین». پایهٔ
                // محاسبه هم price_customer است (هزینهٔ قطعات کسر نمی‌شود).
                get techShare() {
                    const base = Number(this.priceCustomer) || 0;
                    const companyPct = (this.techCalcType === '1' || this.techCalcType === 'internal')
                        ? Math.max(0, Math.min(100, this.techPerOfAll))
                        : Math.max(0, Math.min(100, this.techPercent));
                    return Math.max(0, Math.round(base * (100 - companyPct) / 100));
                },
                fmt(n) { return Number(n||0).toLocaleString('fa-IR'); },
                toWords(n) { return numberToPersianWords(Math.floor(Number(n)||0)); },
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

                {{-- توضیحات وابسته به وضعیت انتخاب‌شده — اجباری برای ثبت تغییر.
                     یک textarea مشترک با label/placeholder داینامیک. وقتی
                     کاربر وضعیت را عوض می‌کند، فیلد پاک می‌شود تا متن یک
                     وضعیت روی وضعیت دیگر باقی نماند. --}}
                <div x-show="@js(array_keys($statusDescPrompts)).includes(selected)"
                     x-cloak class="pt-2"
                     x-data="{ descValue: @js(old('description', '')), lastStatus: '' }"
                     x-init="lastStatus = selected;
                             $watch('selected', v => {
                                 if (v !== lastStatus) { descValue = ''; lastStatus = v; }
                             })">
                    <label class="text-[11px] text-rose-700 font-bold mb-1 block">
                        <span x-text="@js($statusDescPrompts)[selected] || ''"></span> *
                    </label>
                    <textarea name="description" rows="3" maxlength="2000"
                              x-model="descValue"
                              :required="@js(array_keys($statusDescPrompts)).includes(selected)"
                              class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm text-gray-900 placeholder-gray-400 focus:bg-white focus:border-brand-400 focus:outline-none leading-7"
                              placeholder="دلیل و توضیح را شرح دهید..."></textarea>
                    @error('description')
                        <p class="text-[10px] text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- ── بلاک فاکتور — فقط هنگام انتخاب «پایان سفارش» ─── --}}
                <div x-show="selected === '{{ OrderStatus::Completed->value }}'" x-cloak class="pt-3 border-t border-gray-100 mt-3 space-y-3">
                    <div class="text-[11px] text-brand-700 font-bold">فاکتور سفارش</div>

                    {{-- کل مبلغ دریافتی از مشتری — تنها فیلد اصلی --}}
                    <div>
                        <label class="text-[11px] text-gray-500 mb-1 block">کل مبلغ دریافتی از مشتری (تومان)</label>
                        <input type="number" name="price_customer" min="0" step="1000" inputmode="numeric"
                               x-model.number="priceCustomer"
                               placeholder="در اینجا مبلغ را به تومان وارد کنید"
                               class="w-full bg-gray-50 border border-gray-200 rounded-xl py-3 px-3 text-base font-bold focus:bg-white focus:border-brand-400 focus:outline-none">
                        {{-- مبلغ به حروف --}}
                        <div class="mt-1.5 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-xs text-amber-900">
                            <span x-text="toWords(priceCustomer) + ' تومان'"></span>
                        </div>
                        @error('price_customer')
                            <p class="text-[10px] text-rose-600 mt-1 font-bold">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- قطعات استفاده‌شده — فقط نام + قیمت خرید (هم‌ارز WP) --}}
                    <div>
                        <label class="text-[11px] text-gray-500 mb-1 block">قطعات استفاده‌شده</label>
                        <div class="space-y-2">
                            <template x-for="(p, i) in pieces" :key="i">
                                <div class="bg-gray-50 rounded-xl p-2.5 grid grid-cols-2 gap-2">
                                    <input type="text" :name="`pieces[${i}][title]`" x-model="p.title"
                                           placeholder="نام قطعه"
                                           class="bg-white border border-gray-200 rounded-lg py-2 px-2.5 text-sm focus:border-brand-400 focus:outline-none">
                                    <input type="number" :name="`pieces[${i}][buy_price]`" x-model.number="p.buy" min="0" step="1000" inputmode="numeric"
                                           placeholder="قیمت خرید قطعه"
                                           class="bg-white border border-gray-200 rounded-lg py-2 px-2.5 text-sm focus:border-brand-400 focus:outline-none">
                                    {{-- customer_price حذف شد ولی برای backend سازگاری مقدار 0 ارسال می‌شود --}}
                                    <input type="hidden" :name="`pieces[${i}][customer_price]`" :value="0">
                                    <button type="button" @click="pieces.splice(i, 1)"
                                            x-show="pieces.length > 1"
                                            class="col-span-2 text-rose-600 text-[11px] text-right">حذف این قطعه</button>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="pieces.push({title:'', buy:0, sell:0})"
                                class="mt-2 w-full py-2 rounded-xl bg-gray-100 text-gray-700 text-xs font-bold">
                            + افزودن قطعات بیشتر
                        </button>
                    </div>

                    {{-- اجرت / ایاب و ذهاب / تخفیف به درخواست ادمین حذف شد —
                         در محاسبهٔ سهم تکنسین موثر نبودند و فقط فضا اشغال
                         می‌کردند. اگر روزی نیاز شد، در پنل ادمین می‌توانید
                         اضافه کنید. --}}

                    {{-- جمع‌بندی فاکتور — هم‌ارز WP tech_show_order.php --}}
                    <div class="bg-amber-50/50 border border-amber-200 rounded-xl overflow-hidden">
                        <div class="grid grid-cols-2 px-3 py-2.5 text-xs border-b border-amber-200/60">
                            <span class="bg-amber-100 -mx-3 -my-2.5 px-3 py-2.5 font-bold text-amber-900">جمع کل مبلغ فاکتور</span>
                            <span class="text-left font-bold text-gray-800" x-text="fmt(priceCustomer) + ' تومان'"></span>
                        </div>
                        <div class="grid grid-cols-2 px-3 py-2.5 text-xs border-b border-amber-200/60">
                            <span class="bg-amber-100 -mx-3 -my-2.5 px-3 py-2.5 font-bold text-amber-900">جمع هزینه‌ها قطعات</span>
                            <span class="text-left font-bold text-gray-800" x-text="fmt(partsCost) + ' تومان'"></span>
                        </div>
                        <div class="grid grid-cols-2 px-3 py-2.5 text-xs border-b border-amber-200/60">
                            <span class="bg-gray-100 -mx-3 -my-2.5 px-3 py-2.5 font-bold text-gray-800">مانده (پس از کسر هزینه‌ها)</span>
                            <span class="text-left font-bold text-gray-800" x-text="fmt(totalInvoice) + ' تومان'"></span>
                        </div>
                        <div class="grid grid-cols-2 px-3 py-2.5 text-xs">
                            <span class="bg-emerald-500 -mx-3 -my-2.5 px-3 py-2.5 font-bold text-white">سهم شما از سفارش</span>
                            <span class="text-left font-bold text-emerald-700" x-text="fmt(techShare) + ' تومان'"></span>
                        </div>
                    </div>

                    {{-- هشدار: جمع فاکتور کمتر از هزینهٔ قطعات → پایان سفارش مسدود --}}
                    <div x-show="invoiceBelowParts" x-cloak
                         class="bg-rose-50 border border-rose-200 rounded-xl px-3 py-2.5 text-[11px] text-rose-700 font-bold leading-6">
                        ⚠️ جمع کل مبلغ فاکتور نمی‌تواند کمتر از جمع هزینهٔ قطعات (<span x-text="fmt(partsCost)"></span> تومان) باشد. لطفاً «کل مبلغ دریافتی از مشتری» را اصلاح کنید.
                    </div>

                    {{-- total_invoice به‌صورت hidden — توسط JS برابر مانده ست می‌شود --}}
                    <input type="hidden" name="total_invoice" :value="totalInvoice">

                    {{-- device_img1 upload --}}
                    <div>
                        <label class="text-[11px] text-rose-700 font-bold mb-1 block">
                            عکس دستگاه (پس از تعمیر) — اجباری *
                        </label>
                        @if($order->device_img1)
                            <a href="{{ storage_url($order->device_img1) }}" target="_blank"
                               class="inline-flex items-center gap-1.5 text-xs text-brand-700 mb-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                                مشاهده عکس فعلی (در صورت آپلود جدید جایگزین می‌شود)
                            </a>
                        @endif
                        <input type="file" name="device_img1" accept="image/*"
                               @if(! $order->device_img1) :required="selected === '{{ OrderStatus::Completed->value }}'" @endif
                               class="block w-full text-xs text-gray-600 file:ms-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-rose-50 file:text-rose-700 file:font-bold file:text-xs">
                        <p class="text-[10px] text-rose-600 mt-1">
                            بدون عکس امکان بستن سفارش وجود ندارد. JPG/PNG حداکثر ۵ مگابایت.
                        </p>
                    </div>

                    {{-- invoice_descripotion — اجباری برای بستن سفارش (مگر پیش‌نویس یا سفارش برگشتی) --}}
                    @php $isReturned = ! is_null($order->return_type); @endphp
                    <div>
                        <label class="text-[11px] {{ $isReturned ? 'text-gray-600' : 'text-rose-700' }} font-bold mb-1 block">
                            توضیحات فاکتور
                            @if($isReturned)
                                <span class="text-emerald-600 mr-1">— اختیاری (سفارش برگشتی)</span>
                            @else
                                — اجباری *
                            @endif
                        </label>
                        <textarea name="invoice_descripotion" rows="3"
                                  @if(! $isReturned) :required="selected === '{{ OrderStatus::Completed->value }}'" @endif
                                  class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm focus:bg-white focus:border-brand-400 focus:outline-none leading-7">{{ old('invoice_descripotion', $order->invoice_descripotion) }}</textarea>
                        <div class="mt-2 px-3 py-2 rounded-lg {{ $isReturned ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-amber-50 border-amber-200 text-amber-900' }} border text-[10px] leading-6">
                            @if($isReturned)
                                <strong>سفارش برگشتی:</strong> در سفارش‌های برگشتی می‌توانید بدون توضیحات و با مبلغ صفر سفارش را ببندید (خدمات رایگان). در صورتی که هزینه‌ای دریافت می‌کنید، توضیحات و مبلغ را وارد کنید.
                            @else
                                <strong>توجه:</strong> توضیحات وارد شده در کادر بالا به صورت فاکتور آنلاین برای مشتری ارسال می‌گردد و اعتبار قانونی دارد. خواهشمندیم در ثبت قطعات و توضیحات، دقت لازم را به عمل آورید. از همکاری شما سپاسگزاریم.
                            @endif
                        </div>
                        @error('invoice_descripotion')
                            <p class="text-[10px] text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- save_as_draft --}}
                    <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                        <input type="hidden" name="save_as_draft" value="0">
                        <input type="checkbox" name="save_as_draft" value="1"
                               x-model="saveAsDraft"
                               class="w-4 h-4 accent-brand-700">
                        ذخیره به‌عنوان پیش‌نویس (هنوز نهایی نشود)
                    </label>
                </div>

                <button type="submit"
                        x-bind:disabled="!selected || invoiceBelowParts"
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

        @if($orderIsLocked)
            <p class="text-[11px] text-gray-400 italic">
                ثبت یادداشت جدید روی سفارش‌های نهایی غیرفعال است.
            </p>
        @else
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
        @endif
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
                                    <span dir="rtl" class="text-gray-500">{{ $log->changer->full_name }}</span>
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

{{-- تبدیل عدد به حروف فارسی — هم‌ارز اسکریپت WP که در زیر input مبلغ
     نمایش می‌دهد. صفر = «صفر» و اعداد بزرگ تا میلیارد پشتیبانی می‌شوند. --}}
<script>
window.numberToPersianWords = (function () {
    const ones = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
    const tens = ['', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
    const teens = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];
    const hundreds = ['', 'یکصد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];
    const scales = ['', ' هزار', ' میلیون', ' میلیارد', ' هزار میلیارد'];

    function under1000(n) {
        if (n === 0) return '';
        const parts = [];
        const h = Math.floor(n / 100);
        const rest = n % 100;
        if (h) parts.push(hundreds[h]);
        if (rest >= 10 && rest <= 19) parts.push(teens[rest - 10]);
        else {
            const t = Math.floor(rest / 10);
            const o = rest % 10;
            if (t) parts.push(tens[t]);
            if (o) parts.push(ones[o]);
        }
        return parts.join(' و ');
    }

    return function (n) {
        n = Math.floor(Number(n) || 0);
        if (n === 0) return 'صفر';
        if (n < 0) return 'منفی ' + window.numberToPersianWords(-n);
        const groups = [];
        let i = 0;
        while (n > 0 && i < scales.length) {
            const part = n % 1000;
            if (part) groups.unshift(under1000(part) + scales[i]);
            n = Math.floor(n / 1000);
            i++;
        }
        return groups.join(' و ');
    };
})();
</script>
@endsection
