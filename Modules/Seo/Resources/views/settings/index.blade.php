@extends('layouts.admin')

@section('page-title', 'تنظیمات سئو')

@section('main')
<div class="p-6 max-w-3xl mx-auto" dir="rtl">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-1">تنظیمات سراسری سئو</h1>
    <p class="text-sm text-gray-500 mb-6">این مقادیر به‌عنوان پیش‌فرض/fallback برای همهٔ صفحات و در endpoint <code class="font-mono ltr">/v1/seo/settings</code> استفاده می‌شوند.</p>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('seo.admin.settings.update') }}" class="space-y-6">
        @csrf @method('PUT')

        <fieldset class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 space-y-3">
            <legend class="font-bold text-gray-800 dark:text-gray-100 px-2">عمومی</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="block text-sm">نام سایت
                    <input name="site_name" value="{{ old('site_name', $settings['site_name'] ?? '') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
                <label class="block text-sm">جداکننده (sep)
                    <input name="separator" value="{{ old('separator', $settings['separator'] ?? '–') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
            </div>
            <label class="block text-sm">توضیح سایت
                <textarea name="site_description" rows="2" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">{{ old('site_description', $settings['site_description'] ?? '') }}</textarea>
            </label>
            <label class="block text-sm">آدرس پایه (canonical base)
                <input name="canonical_base_url" dir="ltr" value="{{ old('canonical_base_url', $settings['canonical_base_url'] ?? '') }}" placeholder="https://tamironline.com" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </label>
        </fieldset>

        <fieldset class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 space-y-3">
            <legend class="font-bold text-gray-800 dark:text-gray-100 px-2">شبکه‌های اجتماعی</legend>
            <label class="block text-sm">تصویر پیش‌فرض OG
                <input name="og_default_image" dir="ltr" value="{{ old('og_default_image', $settings['og_default_image'] ?? '') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="block text-sm">نوع Twitter Card
                    <select name="twitter_card" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        @foreach(['summary','summary_large_image','app','player'] as $c)
                            <option value="{{ $c }}" @selected(($settings['twitter_card'] ?? 'summary_large_image') === $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">Facebook App ID
                    <input name="facebook_app_id" dir="ltr" value="{{ old('facebook_app_id', $settings['facebook_app_id'] ?? '') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
                <label class="block text-sm">Twitter @site
                    <input name="twitter_site" dir="ltr" value="{{ old('twitter_site', $settings['twitter_site'] ?? '') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
                <label class="block text-sm">Twitter @creator
                    <input name="twitter_creator" dir="ltr" value="{{ old('twitter_creator', $settings['twitter_creator'] ?? '') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
            </div>
        </fieldset>

        <fieldset class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 space-y-3">
            <legend class="font-bold text-gray-800 dark:text-gray-100 px-2">کدهای تأیید (Verification)</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach(['verify_google'=>'Google','verify_bing'=>'Bing','verify_yandex'=>'Yandex','verify_baidu'=>'Baidu','verify_pinterest'=>'Pinterest'] as $k=>$label)
                    <label class="block text-sm">{{ $label }}
                        <input name="{{ $k }}" dir="ltr" value="{{ old($k, $settings[$k] ?? '') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    </label>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 space-y-3">
            <legend class="font-bold text-gray-800 dark:text-gray-100 px-2">Knowledge Graph</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="block text-sm">نوع
                    <select name="kg_type" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        @foreach(['Organization','Person'] as $t)
                            <option value="{{ $t }}" @selected(($settings['kg_type'] ?? 'Organization') === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">نام
                    <input name="kg_name" value="{{ old('kg_name', $settings['kg_name'] ?? '') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
            </div>
            <label class="block text-sm">لوگو
                <input name="kg_logo" dir="ltr" value="{{ old('kg_logo', $settings['kg_logo'] ?? '') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </label>
            <div>
                <span class="block text-sm mb-1">پروفایل‌های اجتماعی (sameAs)</span>
                @for($i = 0; $i < 5; $i++)
                    <input name="kg_same_as[]" dir="ltr" value="{{ $sameAs[$i] ?? '' }}" placeholder="https://instagram.com/..." class="mb-2 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                @endfor
            </div>
        </fieldset>

        <fieldset class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 space-y-3">
            <legend class="font-bold text-gray-800 dark:text-gray-100 px-2">Local SEO (کسب‌وکار محلی)</legend>
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="lb_enabled" value="1" @checked(($settings['lb_enabled'] ?? '') === '1') class="rounded">
                فعال‌سازی schema کسب‌وکار محلی (LocalBusiness)
            </label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="block text-sm">نوع کسب‌وکار
                    <input name="lb_type" value="{{ old('lb_type', $settings['lb_type'] ?? 'LocalBusiness') }}" placeholder="LocalBusiness" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
                <label class="block text-sm">نام
                    <input name="lb_name" value="{{ old('lb_name', $settings['lb_name'] ?? '') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
                <label class="block text-sm">تلفن
                    <input name="lb_phone" dir="ltr" value="{{ old('lb_phone', $settings['lb_phone'] ?? '') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
                <label class="block text-sm">محدودهٔ قیمت
                    <input name="lb_price_range" value="{{ old('lb_price_range', $settings['lb_price_range'] ?? '') }}" placeholder="$$" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
                <label class="block text-sm">آدرس (خیابان)
                    <input name="lb_street" value="{{ old('lb_street', $settings['lb_street'] ?? '') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
                <label class="block text-sm">شهر
                    <input name="lb_city" value="{{ old('lb_city', $settings['lb_city'] ?? '') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
                <label class="block text-sm">استان
                    <input name="lb_region" value="{{ old('lb_region', $settings['lb_region'] ?? '') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
                <label class="block text-sm">کد پستی
                    <input name="lb_postal" dir="ltr" value="{{ old('lb_postal', $settings['lb_postal'] ?? '') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
                <label class="block text-sm">عرض جغرافیایی (lat)
                    <input name="lb_lat" dir="ltr" value="{{ old('lb_lat', $settings['lb_lat'] ?? '') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
                <label class="block text-sm">طول جغرافیایی (lng)
                    <input name="lb_lng" dir="ltr" value="{{ old('lb_lng', $settings['lb_lng'] ?? '') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
            </div>
        </fieldset>

        <fieldset class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 space-y-3">
            <legend class="font-bold text-gray-800 dark:text-gray-100 px-2">robots.txt</legend>
            <p class="text-xs text-gray-500">اگر خالی بماند، نسخهٔ پیش‌فرض با ارجاع به sitemap تولید می‌شود. خروجی در <code class="font-mono ltr">/v1/seo/robots.txt</code> سرو می‌شود.</p>
            <textarea name="robots_txt" rows="6" dir="ltr" placeholder="User-agent: *&#10;Allow: /"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm font-mono">{{ old('robots_txt', $settings['robots_txt'] ?? '') }}</textarea>
        </fieldset>

        <div class="flex justify-end">
            <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">ذخیره</button>
        </div>
    </form>
</div>
@endsection
