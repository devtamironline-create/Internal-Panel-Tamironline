@extends('layouts.admin')

@section('page-title', 'تنظیمات پوش')

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-4xl">

    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">⚙ تنظیمات پوش نوتیفیکیشن</h1>
        <p class="text-xs text-gray-500 mt-1">کلید نجوا، شناسهٔ سایت و پنجرهٔ ساعت مجاز. مقدار این‌جا بر مقدار فایل env اولویت دارد.</p>
    </div>

    @include('crm::push._nav')

    {{-- ─── چک‌لیست راه‌اندازی ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
        <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-3">وضعیت راه‌اندازی</h2>
        <ul class="space-y-2 text-xs">
            @foreach([
                ['کلید API نجوا', filled($settings['najva_api_key']) || $envKey],
                ['شناسهٔ عددی سایت', filled($settings['najva_website_id']) || $envWebsite],
                ['ثبت IP سرور در وایت‌لیست نجوا', filled($settings['najva_whitelisted_ips'])],
            ] as [$label, $done])
                <li class="flex items-center gap-2">
                    <span class="{{ $done ? 'text-emerald-600' : 'text-rose-500' }}">{{ $done ? '✓' : '✗' }}</span>
                    <span class="{{ $done ? 'text-gray-700 dark:text-gray-200' : 'text-rose-700 dark:text-rose-300 font-medium' }}">{{ $label }}</span>
                </li>
            @endforeach
        </ul>
        <p class="text-[11px] text-gray-500 mt-3 leading-relaxed">
            وایت‌لیست IP در پنل خود نجوا انجام می‌شود، نه این‌جا — فیلد پایین فقط یادداشت است تا بدانید چه IPهایی ثبت شده.
            بدون آن، همهٔ ارسال‌ها خطای ۴۱۶ می‌گیرند.
        </p>
    </div>

    <form method="POST" action="{{ route('crm.push.settings.update') }}"
          class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border-2 border-sky-200 dark:border-sky-800 space-y-4">
        @csrf

        <div>
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">کلید API نجوا</label>
            <input type="text" name="najva_api_key" dir="ltr"
                   value="{{ old('najva_api_key', $settings['najva_api_key']) }}"
                   class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
            <p class="text-[11px] text-gray-500 mt-1">
                در هدر <code class="px-1 rounded bg-gray-100 dark:bg-gray-700">apiKey</code> فرستاده می‌شود، نه Authorization.
                @if($envKey)<span class="text-emerald-600">مقداری در env هم تعریف شده — خالی گذاشتن این فیلد یعنی از env بخوان.</span>@endif
            </p>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">شناسهٔ عددی سایت (website_id)</label>
            <input type="text" name="najva_website_id" dir="ltr" placeholder="45290"
                   value="{{ old('najva_website_id', $settings['najva_website_id']) }}"
                   class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
            <p class="text-[11px] text-amber-700 dark:text-amber-400 mt-1">
                ⚠ این «عدد» است، نه UUID اسکریپت. اگر UUID بگذارید همهٔ ارسال‌ها خطای ۴۰۰ می‌گیرند.
                عدد درست را با دکمهٔ «تست اتصال» پایین بگیرید.
            </p>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">IPهای ثبت‌شده در وایت‌لیست نجوا</label>
            <input type="text" name="najva_whitelisted_ips" dir="ltr" placeholder="1.2.3.4, 5.6.7.8"
                   value="{{ old('najva_whitelisted_ips', $settings['najva_whitelisted_ips']) }}"
                   class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
            <p class="text-[11px] text-gray-500 mt-1">فقط یادداشت است. ثبت واقعی در پنل نجوا انجام می‌شود.</p>
        </div>

        <div class="grid grid-cols-2 gap-3 pt-3 border-t border-gray-100 dark:border-gray-700">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">ساعت مجاز ارسال — از</label>
                <input type="number" name="tech_push_quiet_start" min="0" max="23" required
                       value="{{ old('tech_push_quiet_start', $window['start']) }}"
                       class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">تا</label>
                <input type="number" name="tech_push_quiet_end" min="0" max="23" required
                       value="{{ old('tech_push_quiet_end', $window['end']) }}"
                       class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
            </div>
        </div>
        <p class="text-[11px] text-gray-500">
            فقط روی اطلاعیه و پیام کارشناس اعمال می‌شود. رویدادهای مهلت‌دار هر ساعتی می‌روند —
            اعلانی که تا صبح نگه داشته شود مهلتی را یادآوری می‌کند که گذشته است.
        </p>

        <div class="flex justify-end pt-2">
            <button type="submit" class="text-sm px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold">ذخیره تنظیمات</button>
        </div>
    </form>

    {{-- ─── تست اتصال ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-gray-700">
        <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-2">تست اتصال</h2>
        <p class="text-xs text-gray-500 mb-3">
            سریع‌ترین راه تشخیص مشکل: خطای ۴۰۳ یعنی کلید غلط است، خطای ۴۱۶ یعنی IP ثبت نشده.
            در صورت موفقیت، شناسهٔ عددی سایت‌های حساب شما نمایش داده می‌شود.
        </p>
        <form method="POST" action="{{ route('crm.push.settings.test') }}">
            @csrf
            <button type="submit" class="text-xs px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg font-bold">
                تست اتصال به نجوا
            </button>
        </form>
    </div>

    {{-- ─── وضعیت فنی ─── --}}
    <div class="bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-xs space-y-2">
        <h2 class="font-bold text-gray-700 dark:text-gray-200">وضعیت فنی</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-gray-600 dark:text-gray-400">
            <p>آدرس اپ تکنسین: <code class="px-1 rounded bg-gray-200 dark:bg-gray-700" dir="ltr">{{ $appUrl }}</code></p>
            <p>
                صف ارسال:
                @if($queueConnection === '')
                    <span class="text-amber-600">بعد از ارسال پاسخ (بدون worker)</span>
                @else
                    <code class="px-1 rounded bg-gray-200 dark:bg-gray-700" dir="ltr">{{ $queueConnection }}</code>
                @endif
            </p>
        </div>
        <p class="text-[11px] text-gray-500 leading-relaxed">
            صف پوش مستقل از <code dir="ltr">QUEUE_CONNECTION</code> سایت است و با
            <code dir="ltr">NAJVA_QUEUE_CONNECTION</code> تنظیم می‌شود. خالی بودنش یعنی ارسال بلافاصله بعد از
            پاسخ به کاربر انجام می‌شود — بدون نیاز به worker، و بدون اینکه کندی نجوا ثبت سفارش را معطل کند.
        </p>
    </div>
</div>
@endsection
