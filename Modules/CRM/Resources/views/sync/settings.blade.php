@extends('layouts.admin')

@section('page-title', 'تنظیمات سینک وردپرس')

@section('main')
<div class="p-6 space-y-6 max-w-4xl">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">تنظیمات سینک با CRM وردپرسی</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">
            توکن و آدرس endpointها را در پلاگین <code dir="ltr">tamironline-crm-sync</code> روی وردپرس وارد کنید.
        </p>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    {{-- توکن --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-3">توکن احراز هویت</h2>

        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Bearer Token</label>
        <div class="flex items-stretch gap-2">
            <input type="text" id="sync-token" value="{{ $token }}" readonly dir="ltr"
                   class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg font-mono text-xs">
            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('sync-token').value)"
                    class="px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-sm">
                کپی
            </button>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
            این توکن را در صفحهٔ تنظیمات پلاگین وردپرس، فیلد «Bearer Token» وارد کنید.
        </p>

        <form action="{{ route('crm.sync.regenerate') }}" method="POST" class="mt-4"
              onsubmit="return confirm('بازتولید توکن جدید باعث می‌شود توکن فعلی نامعتبر شود. تا زمانی که توکن جدید را در وردپرس وارد نکنید، سینک کار نمی‌کند. ادامه می‌دهید؟');">
            @csrf
            <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 text-sm">
                بازتولید توکن جدید
            </button>
        </form>
    </div>

    {{-- آدرس‌های API --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-3">آدرس‌های API</h2>

        <div class="space-y-3 text-sm">
            <div>
                <div class="text-gray-700 dark:text-gray-200 mb-1">آدرس پایه (Base URL):</div>
                <code class="block bg-gray-100 dark:bg-gray-900 px-3 py-2 rounded text-xs" dir="ltr">{{ $baseUrl }}</code>
            </div>
            <div>
                <div class="text-gray-700 dark:text-gray-200 mb-1">تست اتصال (Ping):</div>
                <code class="block bg-gray-100 dark:bg-gray-900 px-3 py-2 rounded text-xs" dir="ltr">GET {{ $pingUrl }}</code>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    در پلاگین وردپرس دکمهٔ «تست اتصال» همین endpoint را با Bearer token می‌خواند.
                </p>
            </div>
        </div>
    </div>

    {{-- دانلود پلاگین --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-3">دانلود پلاگین وردپرس</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            هربار که پنل به‌روزرسانی می‌شود، نسخهٔ تازهٔ پلاگین را از این‌جا دانلود و در WP جایگزین کنید.
        </p>

        <div class="flex flex-col sm:flex-row sm:items-start gap-4">
            @if($pluginAvailable)
                <a href="{{ route('crm.sync.plugin.download') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm font-medium flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    دانلود پلاگین
                    @if($pluginVersion)
                        <span class="text-xs bg-white/20 px-2 py-0.5 rounded-full" dir="ltr">v{{ $pluginVersion }}</span>
                    @endif
                </a>

                <div class="text-xs text-gray-500 dark:text-gray-400 space-y-1 font-mono" dir="ltr">
                    <div>SHA1: <span class="text-gray-700 dark:text-gray-300">{{ substr($pluginSha1, 0, 16) }}…</span></div>
                    <div>Size: {{ number_format($pluginSize) }} bytes</div>
                    <div>Built: {{ date('Y-m-d H:i:s', $pluginMtime) }} UTC</div>
                </div>
            @else
                <div class="text-sm text-amber-600">
                    ⚠ فایل پلاگین روی سرور یافت نشد. مدیر سیستم باید کد را با پوشهٔ <code dir="ltr">wp-sync-plugin/</code> سنک کند.
                </div>
            @endif
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400 mt-3 leading-relaxed">
            اگر بعد از دانلود همچنان نسخهٔ قدیمی نصب شد:
            (۱) cache مرورگر را پاک کن (Ctrl+Shift+Delete) و دوباره دانلود کن،
            (۲) در WP افزونهٔ قبلی را کاملاً <strong>غیرفعال + حذف</strong> کن،
            (۳) فایل تازه را آپلود و فعال کن.
            مقدار <code dir="ltr">SHA1</code> بالا را با hash فایل دانلود‌شده مقایسه کن (در ویندوز: <code dir="ltr">certutil -hashfile FILE SHA1</code>).
        </p>
    </div>

    {{-- راهنما --}}
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-5">
        <h2 class="text-base font-bold text-blue-900 dark:text-blue-100 mb-2">راهنمای نصب / به‌روزرسانی</h2>
        <ol class="text-sm text-blue-900 dark:text-blue-100 space-y-1 list-decimal ps-5">
            <li>دکمهٔ «دانلود پلاگین» بالا را بزنید و فایل zip را ذخیره کنید.</li>
            <li>اگر نسخهٔ قبلی نصب است، در WP به <strong>افزونه‌ها</strong> بروید، آن را <em>غیرفعال</em> و سپس <em>حذف</em> کنید (داده‌ها از بین نمی‌رود — تنظیمات در wp_options است).</li>
            <li>از مسیر <strong>افزونه‌ها → افزودن جدید → بارگذاری افزونه</strong>، فایل zip دانلود‌شده را آپلود و فعال کنید.</li>
            <li>به <strong>پیشخوان وردپرس → تنظیمات → CRM Sync</strong> بروید.</li>
            <li>«Base URL» و «Bearer Token» را از همین صفحه کپی و در آن‌جا وارد کنید.</li>
            <li>دکمهٔ «تست اتصال» را بزنید — باید پیام موفقیت ببینید.</li>
            <li>برای انتقال داده‌های موجود، باکس‌های backfill را به ترتیب بزنید: تنظیمات → داده‌های پایه → مشتری → تکنسین → سفارش → مالی.</li>
        </ol>
    </div>
</div>
@endsection
