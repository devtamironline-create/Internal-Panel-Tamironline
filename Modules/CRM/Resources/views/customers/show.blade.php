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
            @can('edit-crm-customer')
            <a href="{{ route('crm.customers.edit', $customer) }}" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ویرایش</a>
            @endcan
            <a href="{{ route('crm.customers.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">بازگشت</a>
        </div>
    </div>

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
                    @push('styles')
                        <link rel="stylesheet" href="https://static.neshan.org/sdk/leaflet/1.4.0/leaflet.css">
                    @endpush
                    @push('scripts')
                        <script src="https://static.neshan.org/sdk/leaflet/1.4.0/leaflet.js"></script>
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                document.querySelectorAll('[id^="neshan-map-"]').forEach(function (el) {
                                    var lat = parseFloat(el.dataset.lat), lng = parseFloat(el.dataset.lng);
                                    if (isNaN(lat) || isNaN(lng)) return;
                                    var map = new L.Map(el.id, {
                                        key: @json($neshanWebKey),
                                        maptype: 'neshan',
                                        center: [lat, lng],
                                        zoom: 15,
                                        poi: false,
                                        traffic: false,
                                        scrollWheelZoom: false,
                                    });
                                    L.marker([lat, lng]).addTo(map);
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

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-3">سفارش‌های مشتری</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">در فازهای بعدی، لیست سفارش‌های تعمیر این مشتری اینجا نمایش داده می‌شود.</p>
    </div>
</div>
@endsection
