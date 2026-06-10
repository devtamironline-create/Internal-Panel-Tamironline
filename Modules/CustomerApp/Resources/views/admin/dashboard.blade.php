@extends('layouts.admin')

@section('page-title', 'مدیریت اپلیکیشن مشتریان')

@section('main')
<div class="p-4 md:p-6 max-w-7xl mx-auto space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
            <h1 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-gray-100">مدیریت اپلیکیشن مشتریان</h1>
            <p class="text-xs text-gray-500 mt-1">هاب مرکزی برای تنظیمات اپلیکیشن، نظرسنجی‌ها، و مدیریت داده‌های مشتریان اپ.</p>
        </div>
        <div class="flex items-center gap-2">
            @if ($appStatus['app_disabled'] || $appStatus['maintenance_active'])
                <span class="text-xs px-3 py-1.5 rounded-full bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 font-medium">⚠️ اپ {{ $appStatus['app_disabled'] ? 'غیرفعال' : 'در حال تعمیر' }}</span>
            @else
                <span class="text-xs px-3 py-1.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 font-medium">✓ سرویس فعال</span>
            @endif
        </div>
    </div>

    {{-- آمار کلی --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-500">مشتری‌های تأیید‌شده</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($stats['verified_customers']) }}</div>
            <div class="text-[10px] text-gray-400 mt-1">با موبایل تأیید شده</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-500">فعال هفته اخیر</div>
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ number_format($stats['active_week']) }}</div>
            <div class="text-[10px] text-gray-400 mt-1">بر اساس last_login</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-500">توکن‌های فعال</div>
            <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ number_format($stats['active_tokens']) }}</div>
            <div class="text-[10px] text-gray-400 mt-1">session های زنده</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-500">سفارش‌های ۳۰ روز</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($stats['orders_from_app_30d']) }}</div>
            <div class="text-[10px] text-gray-400 mt-1">ثبت‌شده از اپ</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 {{ $stats['pending_reviews'] > 0 ? 'border-2 border-amber-300' : '' }}">
            <div class="text-xs text-gray-500">نظرسنجی‌های معوق</div>
            <div class="text-2xl font-bold {{ $stats['pending_reviews'] > 0 ? 'text-amber-600' : 'text-gray-400' }} mt-1">{{ number_format($stats['pending_reviews']) }}</div>
            <div class="text-[10px] text-gray-400 mt-1">سفارش‌های completed بی‌نظر</div>
        </div>
    </div>

    {{-- وضعیت اپ --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">وضعیت اپ</h2>
            <a href="{{ route('customer-app.settings.index') }}" class="text-xs px-3 py-1.5 bg-brand-600 hover:bg-brand-700 text-white rounded-lg font-medium">ویرایش تنظیمات</a>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <div class="text-xs text-gray-500">app_disabled</div>
                <div class="mt-1">
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $appStatus['app_disabled'] ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                        {{ $appStatus['app_disabled'] ? 'بله — اپ غیرفعال' : 'خیر' }}
                    </span>
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-500">maintenance</div>
                <div class="mt-1">
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $appStatus['maintenance_active'] ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ $appStatus['maintenance_active'] ? 'فعال' : 'غیرفعال' }}
                    </span>
                </div>
            </div>
            <div>
                <div class="text-xs text-gray-500">min_version</div>
                <div class="font-mono mt-1 text-gray-900 dark:text-gray-100" dir="ltr">{{ $appStatus['min_version'] }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500">latest_version</div>
                <div class="font-mono mt-1 text-gray-900 dark:text-gray-100" dir="ltr">{{ $appStatus['latest_version'] }}</div>
            </div>
        </div>
        @if ($appStatus['force_reauth_at'] > 0)
            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 text-xs text-gray-500">
                <span class="font-medium">force_reauth_at:</span>
                <span dir="ltr" class="font-mono">{{ $appStatus['force_reauth_at'] }}</span>
                <span class="text-gray-400">({{ \Carbon\Carbon::createFromTimestamp($appStatus['force_reauth_at'])->diffForHumans() }})</span>
                — همه‌ی توکن‌های قبل از این timestamp از اپ logout اجباری می‌شوند.
            </div>
        @endif
    </div>

    {{-- لینک‌های سریع --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-3">مدیریت سریع</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">

            <a href="{{ route('customer-app.reviews.index') }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-brand-300 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition">
                <span class="text-2xl">⭐</span>
                <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">نظرسنجی‌ها</div>
                    <div class="text-[10px] text-gray-500">مشاهده و مدیریت</div>
                </div>
            </a>

            <a href="{{ route('customer-app.review-tags.index') }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-brand-300 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition">
                <span class="text-2xl">🏷️</span>
                <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">تگ‌های نظرسنجی</div>
                    <div class="text-[10px] text-gray-500">نقاط قوت/ضعف</div>
                </div>
            </a>

            <a href="{{ route('customer-app.holidays.index') }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-brand-300 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition">
                <span class="text-2xl">📅</span>
                <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">تعطیلات</div>
                    <div class="text-[10px] text-gray-500">picker تاریخ</div>
                </div>
            </a>

            <a href="{{ route('customer-app.settings.index') }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-brand-300 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition">
                <span class="text-2xl">⚙️</span>
                <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">تنظیمات اپ</div>
                    <div class="text-[10px] text-gray-500">maintenance، نسخه‌ها</div>
                </div>
            </a>

            @can('view-crm-customers')
            <a href="{{ route('crm.customers.index') }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-brand-300 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition">
                <span class="text-2xl">👥</span>
                <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">مشتری‌ها</div>
                    <div class="text-[10px] text-gray-500">لیست + آدرس‌ها</div>
                </div>
            </a>
            @endcan

            @can('view-crm-taxonomies')
            <a href="{{ route('crm.service-types.index') }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-brand-300 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition">
                <span class="text-2xl">🔧</span>
                <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">انواع خدمات</div>
                    <div class="text-[10px] text-gray-500">picker اپ</div>
                </div>
            </a>

            <a href="{{ route('crm.objections.index') }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-brand-300 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition">
                <span class="text-2xl">⚠️</span>
                <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">ایرادات</div>
                    <div class="text-[10px] text-gray-500">per-device</div>
                </div>
            </a>

            <a href="{{ route('crm.provinces.index') }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-brand-300 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition">
                <span class="text-2xl">📍</span>
                <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">استان/شهر</div>
                    <div class="text-[10px] text-gray-500">picker locations</div>
                </div>
            </a>
            @endcan

            @can('view-crm-orders')
            <a href="{{ route('crm.orders.index', ['source_of_truth' => 'panel']) }}" class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-brand-300 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition">
                <span class="text-2xl">📦</span>
                <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">سفارش‌های اپ</div>
                    <div class="text-[10px] text-gray-500">از کانال موبایل</div>
                </div>
            </a>
            @endcan

        </div>
    </div>

    {{-- نکات راهنما --}}
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4 text-sm text-blue-900 dark:text-blue-200">
        <div class="font-bold mb-2">💡 درباره‌ی این پنل</div>
        <ul class="list-disc pr-5 space-y-1 text-xs">
            <li>این پنل مرکز کنترل اپلیکیشن موبایل مشتریان است. تنظیمات اینجا روی API های <span class="font-mono" dir="ltr">/v1/customer/*</span> اعمال می‌شود.</li>
            <li>اگر <span class="font-mono">app_disabled</span> روشن شود، فرانت سراسری overlay «سرویس در دسترس نیست» نمایش می‌دهد و هیچ endpoint کار نمی‌کند.</li>
            <li>اگر <span class="font-mono">force_reauth_at</span> را به الان ست کنید، همه‌ی توکن‌های قبلی بی‌اعتبار می‌شوند و کاربران مجبور به ورود مجدد می‌شوند.</li>
            <li>نظرسنجی‌های معوق نشان‌دهنده‌ی سفارش‌های completed هستند که هنوز نظر مشتری ثبت نشده.</li>
        </ul>
    </div>

</div>
@endsection
