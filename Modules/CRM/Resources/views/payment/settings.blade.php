@extends('layouts.admin')

@section('page-title', 'تنظیمات درگاه پرداخت')

@section('main')
<div class="p-6 space-y-6 max-w-3xl">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">تنظیمات درگاه پرداخت (زیبال)</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">اطلاعات Merchant برای اتصال به درگاه zibal.ir</p>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <form action="{{ route('crm.payments.settings.update') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Merchant ID</label>
                <input type="text" name="zibal_merchant" value="{{ old('zibal_merchant', $merchant) }}" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">کد مرچنت را از پنل zibal.ir → بخش پذیرنده‌ها دریافت کنید.</p>
            </div>

            <div>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="zibal_sandbox" value="1" @checked($sandbox)
                           class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500">
                    <span class="text-sm text-gray-700 dark:text-gray-200">حالت تست (Sandbox) — به‌جای مرچنت بالا، از شناسه <code dir="ltr">zibal</code> استفاده می‌شود.</span>
                </label>
            </div>

            <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 text-sm text-blue-800 dark:text-blue-200">
                    <strong>آدرس Callback:</strong>
                    <code class="block mt-1 bg-white dark:bg-gray-900 px-2 py-1 rounded text-xs" dir="ltr">{{ $callbackUrl }}</code>
                    <p class="mt-2 text-xs">این آدرس را در پنل zibal.ir در تنظیمات پذیرنده به‌عنوان Callback URL ثبت کنید (اگر زیبال این را الزامی کند).</p>
                </div>
            </div>

            <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">ذخیره</button>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-3">نحوه استفاده</h2>
        <ol class="text-sm text-gray-700 dark:text-gray-300 space-y-2 list-decimal ps-5">
            <li>پس از صدور فاکتور، لینک پرداخت از صفحه جزئیات فاکتور قابل دریافت است.</li>
            <li>لینک به‌صورت <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded" dir="ltr">/crm/pay/{invoice_code}</code> عمومی است و نیاز به ورود ندارد.</li>
            <li>مشتری این لینک را باز می‌کند → به درگاه زیبال هدایت می‌شود → پس از پرداخت به صفحه نتیجه برمی‌گردد.</li>
            <li>در صورت موفقیت، فاکتور به‌صورت خودکار به وضعیت «پرداخت‌شده» تغییر می‌کند.</li>
        </ol>
    </div>
</div>
@endsection
