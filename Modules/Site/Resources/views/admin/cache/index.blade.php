@extends('layouts.admin')
@section('page-title', 'پاک‌سازی کش سایت')

@section('main')
<div class="p-6 max-w-4xl" dir="rtl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">پاک‌سازی کش سایت</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">ارسال درخواست بازاعتبارسنجی کش به فرانت Next.js — به‌صورت کامل یا برای مسیرهای مشخص.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    @if($errors->any())
    <div class="mb-4 p-3 rounded bg-red-50 text-red-700 text-sm">
        <ul class="list-disc pr-4">
            @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
    @endif

    @if(session('purge_result'))
        @php $pr = session('purge_result'); @endphp
        <div class="mb-4 p-3 rounded text-sm {{ ($pr['ok'] ?? false) ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
            <span class="font-semibold">نتیجه:</span>
            {{ $pr['message'] ?? '' }}
            @if(isset($pr['status']))
                <span class="opacity-70">(HTTP {{ $pr['status'] }})</span>
            @endif
        </div>
    @endif

    <p class="mb-6 p-3 rounded bg-amber-50 text-amber-700 text-xs">
        این سیستم به endpoint ‎/api/revalidate در فرانت Next نیاز دارد (تیم فرانت آن را پیاده می‌کند).
    </p>

    {{-- ─── کارت ۱: اتصال به فرانت ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">اتصال به فرانت</h2>

        <form method="POST" action="{{ route('site.admin.cache.config') }}" class="space-y-4">
            @csrf @method('PUT')

            <div>
                <label class="block text-sm mb-1">آدرس Webhook بازاعتبارسنجی</label>
                <input type="url" name="revalidate_url" dir="ltr"
                       value="{{ old('revalidate_url', $config['revalidate_url']) }}"
                       placeholder="https://example.com/api/revalidate"
                       class="w-full px-3 py-2 border border-gray-200 rounded text-sm ltr">
            </div>

            <div>
                <label class="block text-sm mb-1">رمز مشترک (x-revalidate-secret)</label>
                <input type="password" name="revalidate_secret" dir="ltr"
                       autocomplete="new-password"
                       placeholder="{{ trim((string) ($config['revalidate_secret'] ?? '')) !== '' ? '••••••• (تنظیم‌شده — برای تغییر مقدار جدید وارد کنید)' : 'رمز را وارد کنید' }}"
                       class="w-full px-3 py-2 border border-gray-200 rounded text-sm ltr">
                <p class="text-xs text-gray-400 mt-1">برای حفظ رمز فعلی این فیلد را خالی بگذارید.</p>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" id="revalidate_enabled" name="revalidate_enabled" value="1"
                       {{ old('revalidate_enabled', $config['revalidate_enabled']) === '1' ? 'checked' : '' }}
                       class="rounded">
                <label for="revalidate_enabled" class="text-sm">فعال‌سازی ارسال درخواست به فرانت</label>
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded text-sm">ذخیره تنظیمات</button>
            </div>
        </form>

        <form method="POST" action="{{ route('site.admin.cache.test') }}" class="mt-3">
            @csrf
            <button type="submit" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded text-sm">تست اتصال</button>
        </form>
    </div>

    {{-- ─── کارت ۲: پاک‌سازی کش ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold mb-4">پاک‌سازی کش</h2>

        {{-- پاک‌سازی کامل --}}
        <form method="POST" action="{{ route('site.admin.cache.purge') }}" class="mb-6"
              onsubmit="return confirm('کل کش سایت پاک‌سازی شود؟');">
            @csrf
            <input type="hidden" name="mode" value="all">
            <button type="submit" class="px-6 py-3 bg-red-600 text-white rounded text-sm font-semibold">
                پاک‌سازی کاملِ کش سایت
            </button>
            <p class="text-xs text-gray-400 mt-2">تمام صفحات کش‌شده‌ی سایت دوباره ساخته می‌شوند.</p>
        </form>

        <div class="border-t border-gray-100 dark:border-gray-700 pt-6">
            {{-- پاک‌سازی مسیرهای انتخابی --}}
            <form method="POST" action="{{ route('site.admin.cache.purge') }}">
                @csrf
                <input type="hidden" name="mode" value="paths">
                <label class="block text-sm mb-1">مسیرها (هر خط یک مسیر، مثل /services/lg)</label>
                <textarea name="paths" rows="5" dir="ltr"
                          placeholder="/services/lg&#10;/blog/article-slug"
                          class="w-full px-3 py-2 border border-gray-200 rounded text-sm ltr">{{ old('paths') }}</textarea>
                <button type="submit" class="mt-3 px-6 py-2 bg-blue-600 text-white rounded text-sm">
                    پاک‌سازی مسیرهای انتخابی
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
