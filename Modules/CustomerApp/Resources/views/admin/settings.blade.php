@extends('layouts.admin')

@section('page-title', 'تنظیمات اپ')

@section('main')
<div class="p-4 md:p-6 max-w-3xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">تنظیمات اپلیکیشن</h1>
            <p class="text-xs text-gray-500 mt-1">این تنظیمات بدون redeploy از سمت اپ مشتری اعمال می‌شوند.</p>
        </div>
        <a href="{{ route('customer-app.dashboard') }}" class="text-xs px-3 py-1.5 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg">بازگشت</a>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm">
            <ul class="list-disc pr-4 space-y-1">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
        </div>
    @endif

    <form action="{{ route('customer-app.settings.update') }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 space-y-5">
        @csrf @method('PUT')

        {{-- وضعیت کلی --}}
        <div class="space-y-3">
            <h2 class="text-sm font-bold text-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700 pb-1">وضعیت سرویس</h2>

            <label class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900">
                <input type="hidden" name="app_disabled" value="0">
                <input type="checkbox" name="app_disabled" value="1" @checked(old('app_disabled', $values['app_disabled'])) class="mt-0.5">
                <div class="flex-1">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">⛔ غیرفعال‌سازی کامل اپ</div>
                    <div class="text-xs text-gray-500 mt-1">همه‌ی endpointهای /v1/customer/* پاسخ 503 می‌دهند. فرانت overlay سراسری «سرویس در دسترس نیست» نمایش می‌دهد.</div>
                </div>
            </label>

            <label class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900">
                <input type="hidden" name="app_maintenance_active" value="0">
                <input type="checkbox" name="app_maintenance_active" value="1" @checked(old('app_maintenance_active', $values['app_maintenance_active'])) class="mt-0.5">
                <div class="flex-1">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">🛠 حالت تعمیر و نگهداری</div>
                    <div class="text-xs text-gray-500 mt-1">اپ همچنان پاسخ می‌دهد ولی فرانت مودال maintenance با پیام شما نمایش می‌دهد.</div>
                </div>
            </label>

            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">پیام نمایش‌داده‌شده در maintenance</label>
                <textarea name="app_maintenance_message" rows="2" maxlength="500"
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm"
                          placeholder="مثلاً: در حال ارتقای سیستم هستیم. لطفاً ۳۰ دقیقه دیگر تلاش کنید.">{{ old('app_maintenance_message', $values['app_maintenance_message']) }}</textarea>
            </div>
        </div>

        {{-- نسخه‌ها --}}
        <div class="space-y-3 pt-2">
            <h2 class="text-sm font-bold text-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700 pb-1">کنترل نسخه</h2>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">حداقل نسخه‌ی مجاز (min_version)</label>
                    <input type="text" name="app_min_version" required value="{{ old('app_min_version', $values['app_min_version']) }}" dir="ltr"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm ltr font-mono"
                           placeholder="1.0.0">
                    <p class="text-[10px] text-gray-500 mt-1">پایین‌تر از این → اپ overlay اجباری به‌روزرسانی نمایش می‌دهد.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">آخرین نسخه‌ی موجود (latest_version)</label>
                    <input type="text" name="app_latest_version" required value="{{ old('app_latest_version', $values['app_latest_version']) }}" dir="ltr"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm ltr font-mono"
                           placeholder="1.0.0">
                    <p class="text-[10px] text-gray-500 mt-1">فرمت semver: X.Y.Z</p>
                </div>
            </div>
        </div>

        <div class="pt-2 flex items-center gap-2">
            <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">ذخیره</button>
        </div>
    </form>

    {{-- اقدام خطرناک: force reauth --}}
    <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 rounded-xl p-5">
        <h2 class="text-base font-bold text-rose-900 dark:text-rose-200 mb-2">⚠️ logout اجباری همه‌ی کاربران</h2>
        <p class="text-xs text-rose-700 dark:text-rose-300 mb-3">با این دکمه، <span class="font-mono">force_reauth_at</span> به الان ست می‌شود. همه‌ی توکن‌های صادر‌شده قبل از این لحظه در دفعه‌ی بعدی که اپ <span class="font-mono" dir="ltr">/v1/customer/status</span> را poll می‌کند، بی‌اعتبار اعلام می‌شوند و کاربر مجبور به login می‌شود.</p>
        @if ($values['app_force_reauth_at'] > 0)
            <p class="text-xs text-rose-800 dark:text-rose-200 mb-3">
                مقدار فعلی: <span class="font-mono" dir="ltr">{{ $values['app_force_reauth_at'] }}</span>
                ({{ \Carbon\Carbon::createFromTimestamp($values['app_force_reauth_at'])->diffForHumans() }})
            </p>
        @endif
        <form action="{{ route('customer-app.settings.force-reauth') }}" method="POST" onsubmit="return confirm('مطمئنی همه‌ی کاربران از اپ logout شوند؟')">
            @csrf
            <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-sm font-bold">logout همه</button>
        </form>
    </div>

</div>
@endsection
