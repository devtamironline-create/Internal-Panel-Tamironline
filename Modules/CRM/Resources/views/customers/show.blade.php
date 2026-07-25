@extends('layouts.admin')

@section('page-title', 'جزئیات مشتری')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ $customer->display_name }}</h1>
            <p class="mt-1">@tel($customer->mobile)</p>
            <p class="text-xs text-gray-500 mt-1">شماره اشتراک: <span dir="ltr" class="font-medium">{{ $customer->subscription }}</span></p>
        </div>
        <div class="flex items-center gap-2">
            @if(! $customer->trashed())
                @can('edit-crm-customer')
                <a href="{{ route('crm.customers.edit', $customer) }}" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ویرایش</a>
                @endcan
            @endif
            <a href="{{ route('crm.customers.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">بازگشت</a>
        </div>
    </div>

    {{-- حسابِ حذف‌شده توسطِ کاربر (delete-account از اپ) — قابلِ بازگردانی --}}
    @if($customer->trashed())
    <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="text-sm text-rose-800 dark:text-rose-200 leading-6">
            <div class="font-bold">این حساب حذف شده است.</div>
            <div>تاریخِ حذف: {{ \Morilog\Jalali\Jalalian::fromCarbon($customer->deleted_at)->format('Y/m/d H:i') }}</div>
            @if($customer->delete_reason)
                <div>دلیلِ اعلام‌شده توسطِ کاربر: «{{ $customer->delete_reason }}»</div>
            @endif
            <div class="text-xs mt-1 text-rose-700 dark:text-rose-300">تا بازگردانی، کاربر نمی‌تواند با این شماره وارد اپ/سایت شود. اطلاعات و سفارش‌ها حفظ شده‌اند.</div>
        </div>
        @can('delete-crm-customer')
        <form action="{{ route('crm.customers.restore', $customer->id) }}" method="POST" onsubmit="return confirm('حسابِ این مشتری بازگردانی شود؟');">
            @csrf
            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 whitespace-nowrap">بازگردانیِ حساب</button>
        </form>
        @endcan
    </div>
    @endif

    {{-- بلاکِ مشتری (جلوگیری از ثبتِ سفارشِ جدید؛ سوابق حفظ می‌شود) --}}
    @can('manage-order-security')
    @if($customer->is_blocked)
    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="text-sm text-red-800 dark:text-red-200 leading-6">
            <div class="font-bold flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                این مشتری بلاک شده است — امکان ثبتِ سفارشِ جدید ندارد.
            </div>
            @if($customer->block_reason)<div>دلیل: «{{ $customer->block_reason }}»</div>@endif
            @if($customer->blocked_at)<div class="text-xs text-red-600 dark:text-red-300">تاریخِ بلاک: {{ \Morilog\Jalali\Jalalian::fromDateTime($customer->blocked_at)->format('Y/m/d H:i') }}</div>@endif
            <div class="text-xs mt-1 text-red-700 dark:text-red-300">سوابق و سفارش‌های قبلیِ مشتری حفظ شده‌اند.</div>
        </div>
        <form action="{{ route('crm.customers.block', $customer) }}" method="POST" onsubmit="return confirm('بلاکِ این مشتری برداشته شود؟');">
            @csrf
            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 whitespace-nowrap">رفعِ بلاک</button>
        </form>
    </div>
    @else
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        <form action="{{ route('crm.customers.block', $customer) }}" method="POST" class="flex flex-col sm:flex-row sm:items-end gap-3"
              onsubmit="return confirm('این مشتری بلاک شود؟ پس از آن نمی‌تواند سفارشِ جدید ثبت کند.');">
            @csrf
            <div class="flex-1">
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">بلاکِ مشتری — دلیل (الزامی)</label>
                <input type="text" name="reason" required placeholder="دلیلِ بلاک..."
                       class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                @error('reason') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-bold whitespace-nowrap">بلاکِ مشتری</button>
        </form>
    </div>
    @endif
    @endcan

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">شماره اشتراک</div>
            <div class="text-sm text-gray-900 dark:text-gray-100" dir="ltr">{{ $customer->subscription }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">نام</div>
            <div class="text-sm text-gray-900 dark:text-gray-100">{{ $customer->first_name ?: '—' }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">موبایل</div>
            <div class="text-sm">@tel($customer->mobile)</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">تلفن ثابت</div>
            <div class="text-sm">@tel($customer->phone)</div>
        </div>
        <div class="md:col-span-2">
            <div class="text-xs text-gray-500 dark:text-gray-400">یادداشت‌ها</div>
            <div class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-wrap">{{ $customer->notes ?: '—' }}</div>
        </div>
    </div>

    {{-- دستگاه‌های فعال (Sanctum tokens) — Block 7 --}}
    @php
        $activeTokens = \Laravel\Sanctum\PersonalAccessToken::query()
            ->where('tokenable_type', \Modules\CRM\Models\Customer::class)
            ->where('tokenable_id', $customer->id)
            ->orderByDesc('last_used_at')
            ->get(['id', 'name', 'device_id', 'last_used_at', 'last_used_ip', 'created_at', 'expires_at']);
    @endphp
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">دستگاه‌های فعال (اپ موبایل)</h2>
            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ $activeTokens->count() }} دستگاه</span>
        </div>

        @if ($activeTokens->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">این مشتری از طریق اپ موبایل لاگین نکرده است.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-500 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="px-2 py-1.5 text-start">Device ID</th>
                            <th class="px-2 py-1.5 text-start">آخرین استفاده</th>
                            <th class="px-2 py-1.5 text-start">IP</th>
                            <th class="px-2 py-1.5 text-start">ساخت</th>
                            <th class="px-2 py-1.5 text-start">انقضا</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($activeTokens as $t)
                            <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                <td class="px-2 py-1.5 text-xs font-mono text-gray-700 dark:text-gray-300" dir="ltr">
                                    {{ $t->device_id ? substr($t->device_id, 0, 16).'…' : '—' }}
                                </td>
                                <td class="px-2 py-1.5 text-xs text-gray-500">
                                    {{ $t->last_used_at?->diffForHumans() ?? '—' }}
                                </td>
                                <td class="px-2 py-1.5 text-xs text-gray-500 font-mono" dir="ltr">
                                    {{ $t->last_used_ip ?: '—' }}
                                </td>
                                <td class="px-2 py-1.5 text-xs text-gray-500">
                                    {{ $t->created_at?->diffForHumans() }}
                                </td>
                                <td class="px-2 py-1.5 text-xs text-gray-500">
                                    {{ $t->expires_at?->diffForHumans() ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p class="text-[11px] text-gray-400 mt-3">
            ادمین این لیست را فقط مشاهده می‌کند. کاربر در اپ می‌تواند خودش دستگاه‌ها را revoke کند یا با force_reauth سراسری از همه logout شود.
        </p>
    </div>

    {{-- آدرس‌های اپ موبایل (multi-address) — read-only؛ ویرایش/حذف از سمت کاربر اپ --}}
    @php
        $appAddresses = $customer->addresses()->with(['province:id,name', 'city:id,name', 'district:id,name'])->get();
        $neshanWebKey = (string) config('services.neshan.web_key');
    @endphp
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">آدرس‌های ثبت‌شده در اپ موبایل</h2>
            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ $appAddresses->count() }} آدرس</span>
        </div>

        @if ($appAddresses->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">این مشتری از طریق اپ موبایل آدرسی ثبت نکرده است.</p>
        @else
            <div class="space-y-3">
                @foreach ($appAddresses as $addr)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 text-sm">
                        <div class="flex items-center justify-between gap-3 mb-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $addr->label ?: 'بدون عنوان' }}</span>
                                @if ($addr->is_default)
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">پیش‌فرض</span>
                                @endif
                            </div>
                            <span class="text-[10px] text-gray-400">#{{ $addr->id }}</span>
                        </div>
                        <div class="text-gray-700 dark:text-gray-300">
                            {{ optional($addr->province)->name }}{{ $addr->province && $addr->city ? ' — ' : '' }}{{ optional($addr->city)->name }}@if($addr->district) — {{ $addr->district->name }}@endif
                        </div>
                        <div class="text-gray-700 dark:text-gray-300 mt-1">{{ $addr->full_address }}</div>
                        <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                            @if ($addr->postal_code)
                                <span dir="ltr">کدپستی: {{ $addr->postal_code }}</span>
                            @endif
                            @if ($addr->phone)
                                <span>تلفن: @tel($addr->phone)</span>
                            @endif
                            @if ($addr->hasCoordinates())
                                <span dir="ltr" class="font-mono text-[10px]">{{ $addr->latitude }}, {{ $addr->longitude }}</span>
                            @endif
                        </div>
                        @if ($addr->hasCoordinates())
                            @if ($neshanWebKey !== '')
                                {{-- نقشه نشان (Web SDK — Leaflet) --}}
                                <div id="neshan-map-{{ $addr->id }}" class="mt-3 h-48 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700"
                                     data-lat="{{ $addr->latitude }}" data-lng="{{ $addr->longitude }}"></div>
                            @else
                                {{-- fallback بدون کلید: OSM embed --}}
                                <div class="mt-3 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                    <iframe class="w-full h-48" loading="lazy" referrerpolicy="no-referrer"
                                            src="https://www.openstreetmap.org/export/embed.html?bbox={{ $addr->longitude - 0.005 }}%2C{{ $addr->latitude - 0.003 }}%2C{{ $addr->longitude + 0.005 }}%2C{{ $addr->latitude + 0.003 }}&layer=mapnik&marker={{ $addr->latitude }}%2C{{ $addr->longitude }}"></iframe>
                                </div>
                                <p class="text-[10px] text-gray-400 mt-1">برای نمایش نقشه نشان، NESHAN_WEB_KEY را در env ست کنید.</p>
                            @endif
                        @endif
                    </div>
                @endforeach

                @if ($neshanWebKey !== '' && $appAddresses->contains(fn ($a) => $a->hasCoordinates()))
                    {{-- SDK رسمی نشان — Mapbox-gl. دقت: center به‌صورت [lng, lat] است --}}
                    @push('styles')
                        <link rel="stylesheet" href="https://static.neshan.org/sdk/mapboxgl/v1.13.2/neshan-sdk/v1.1.5/index.css">
                    @endpush
                    @push('scripts')
                        <script src="https://static.neshan.org/sdk/mapboxgl/v1.13.2/neshan-sdk/v1.1.5/index.js"></script>
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                document.querySelectorAll('[id^="neshan-map-"]').forEach(function (el) {
                                    var lat = parseFloat(el.dataset.lat), lng = parseFloat(el.dataset.lng);
                                    if (isNaN(lat) || isNaN(lng)) return;
                                    var map = new nmp_mapboxgl.Map({
                                        mapType: nmp_mapboxgl.Map.mapTypes.neshanVector,
                                        container: el.id,
                                        zoom: 15,
                                        pitch: 0,
                                        center: [lng, lat],
                                        minZoom: 2,
                                        maxZoom: 21,
                                        trackResize: true,
                                        mapKey: @json($neshanWebKey),
                                        poi: false,
                                        traffic: false,
                                        mapTypeControllerOptions: { show: false },
                                    });
                                    new nmp_mapboxgl.Marker().setLngLat([lng, lat]).addTo(map);
                                });
                            });
                        </script>
                    @endpush
                @endif
            </div>
        @endif

        <p class="text-[11px] text-gray-400 mt-4">
            ادمین این آدرس‌ها را فقط مشاهده می‌کند؛ ویرایش/حذف فقط از سمت خود کاربر در اپ ممکن است.
            آدرس اصلی (province/city/address روی پروفایل مشتری) جداست و در همین صفحه‌ی ویرایش مشتری قابل تغییر است.
        </p>
    </div>

    {{-- ─── اتصالِ بله ─────────────────────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-2">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">💬 اتصال به پیام‌رسانِ بله</h2>
                @if($customer->bale_user_id)
                    <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-full bg-teal-100 text-teal-800">متصل ✓</span>
                @else
                    <span class="px-2.5 py-0.5 text-[11px] font-bold rounded-full bg-gray-100 text-gray-600">متصل نیست</span>
                @endif
            </div>
            @if($customer->bale_user_id)
                @can('edit-crm-customer')
                <form method="POST" action="{{ route('crm.customers.bale-unlink', $customer) }}"
                      onsubmit="return confirm('اتصالِ بلهٔ این مشتری قطع شود؟ نوتیفیکیشن‌های چتِ ربات دیگر برایش نمی‌رود.');">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 bg-rose-100 text-rose-700 hover:bg-rose-200 rounded-lg text-xs font-medium">قطعِ اتصال</button>
                </form>
                @endcan
            @endif
        </div>
        <p class="text-xs text-gray-500 mt-2 leading-6">
            @if($customer->bale_user_id)
                شناسهٔ بله: <b dir="ltr">{{ $customer->bale_user_id }}</b> — نوتیفیکیشن‌های سفارش از طریقِ <b>چتِ ربات (رایگان)</b> برای این مشتری ارسال می‌شود.
            @else
                این مشتری هنوز از مینی‌اپِ بله وارد/متصل نشده. نوتیفیکیشن‌های بله (در صورتِ فعال‌بودنِ سفیر) با <b>شمارهٔ موبایل</b> ارسال می‌شوند.
            @endif
        </p>
    </div>

    {{-- ─── سفارش‌های مشتری ─────────────────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">🧾 سفارش‌های مشتری</h2>
            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ $orders->count() }} سفارشِ اخیر</span>
        </div>
        @if($orders->isEmpty())
            <p class="text-sm text-gray-400 p-6">این مشتری هنوز سفارشی ندارد.</p>
        @else
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-600 dark:text-gray-300">
                    <tr>
                        <th class="p-3 text-start">کد سفارش</th>
                        <th class="p-3 text-start">دستگاه / برند</th>
                        <th class="p-3 text-start">وضعیت</th>
                        <th class="p-3 text-start">منبع ثبت</th>
                        <th class="p-3 text-start">مبلغ (تومان)</th>
                        <th class="p-3 text-start">تاریخ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($orders as $o)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="p-3">
                                <a href="{{ route('crm.orders.show', $o) }}" class="font-bold text-brand-700 hover:underline" dir="ltr">{{ $o->order_code }}</a>
                                @if($o->is_lead)<span class="px-1.5 py-0.5 text-[10px] rounded bg-rose-100 text-rose-700 mr-1">لید</span>@endif
                            </td>
                            <td class="p-3 text-xs">{{ $o->device?->name ?: '—' }}@if($o->brand) / {{ $o->brand->name }}@endif</td>
                            <td class="p-3">
                                <span class="px-2 py-0.5 text-[11px] font-medium rounded-full {{ $o->status?->badgeClass() }}">{{ $o->status?->label() }}</span>
                            </td>
                            <td class="p-3 text-xs">
                                @if($o->source)
                                    <span class="px-2 py-0.5 text-[10px] font-medium rounded-full {{ str_starts_with($o->source, 'bale') ? 'bg-teal-100 text-teal-800' : 'bg-gray-100 text-gray-700' }}">{{ $o->sourceLabel() }}</span>
                                @else — @endif
                            </td>
                            <td class="p-3 text-xs" dir="ltr">{{ $o->price_customer ? number_format($o->price_customer) : '—' }}</td>
                            <td class="p-3 text-xs text-gray-500" dir="ltr">@jdatetime($o->created_at)</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>

    {{-- ─── نوتیفیکیشن‌های ارسال‌شده ─────────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">🔔 آخرین نوتیفیکیشن‌های ارسال‌شده</h2>
            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ $notifications->count() }} موردِ اخیر</span>
        </div>
        @if($notifications->isEmpty())
            <p class="text-sm text-gray-400 p-6">هنوز نوتیفیکیشنی برای این مشتری ارسال نشده.</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($notifications as $n)
                    <li class="px-6 py-3 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ $n->data['title'] ?? '—' }}</div>
                            <div class="text-xs text-gray-500 mt-0.5 leading-6">{{ $n->data['body'] ?? '' }}</div>
                        </div>
                        <div class="text-left shrink-0">
                            <div class="text-[10px] text-gray-400" dir="ltr">@jdatetime($n->created_at)</div>
                            @if($n->read_at)
                                <span class="text-[10px] text-emerald-600">خوانده شد</span>
                            @else
                                <span class="text-[10px] text-amber-600">خوانده نشده</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
