@extends('layouts.admin')

@section('page-title', 'مدیریت پیامک‌ها')

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-6xl">

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">💬 مدیریت پیامک‌ها</h1>
            <p class="text-xs text-gray-500 mt-1">تنظیمات کاوه‌نگار، پراکسی، و قالب‌های همه پیامک‌های خودکار.</p>
        </div>
        <a href="{{ route('crm.sms.logs') }}" class="text-xs px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">📋 لاگ پیامک‌های ارسال‌شده</a>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif

    {{-- ─── ۱) تنظیمات کاوه‌نگار + پراکسی ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border-2 border-sky-200 dark:border-sky-800">
        <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-3">⚙ تنظیمات سرویس پیامک</h2>
        <form method="POST" action="{{ route('crm.sms-management.settings.update') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">API Key کاوه‌نگار</label>
                <input type="text" name="kavenegar_api_key" value="{{ old('kavenegar_api_key', $settings['kavenegar_api_key']) }}"
                       placeholder="API key از پنل کاوه‌نگار" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                <p class="text-[11px] text-gray-500 mt-1">اگر خالی بماند، از مقدار <code>KAVENEGAR_API_KEY</code> در <code>.env</code> استفاده می‌شود.</p>
            </div>

            <div class="border-t border-gray-100 dark:border-gray-700 pt-4 space-y-3">
                <h3 class="text-xs font-bold text-gray-800 dark:text-gray-200">پراکسی پیامک (برای دور زدن block IP کاوه‌نگار)</h3>

                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="hidden" name="sms_proxy_enabled" value="0">
                        <input type="checkbox" name="sms_proxy_enabled" value="1"
                               {{ $settings['sms_proxy_enabled'] === '1' ? 'checked' : '' }}
                               class="w-4 h-4">
                        <strong>فعال‌سازی پراکسی</strong>
                    </label>
                    <span class="text-[11px] text-gray-500">وقتی فعال است، پیامک‌ها از طریق پراکسی ارسال می‌شوند.</span>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">URL پراکسی</label>
                    <input type="url" name="sms_proxy_url" value="{{ old('sms_proxy_url', $settings['sms_proxy_url']) }}"
                           placeholder="https://api.ganjemarket.com" dir="ltr"
                           class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                    <p class="text-[11px] text-gray-500 mt-1">endpoint نهایی: <code dir="ltr">{URL}/sms-proxy.php</code></p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">Secret پراکسی</label>
                    <input type="text" name="sms_proxy_secret" value="{{ old('sms_proxy_secret', $settings['sms_proxy_secret']) }}"
                           placeholder="X-Proxy-Secret" dir="ltr"
                           class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
                </div>
            </div>

            <button type="submit" class="px-5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-sm font-bold">
                ذخیره تنظیمات
            </button>
        </form>
    </div>

    {{-- ─── ۲) متغیرهای قابل استفاده در قالب‌ها ─── --}}
    <details class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs">
        <summary class="cursor-pointer font-bold text-amber-800">📝 متغیرهای قابل استفاده در قالب‌ها</summary>
        <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-1 text-amber-900">
            @foreach($variables as $var => $desc)
                <div><code class="bg-amber-100 px-1 rounded">{{ $var }}</code> — {{ $desc }}</div>
            @endforeach
        </div>
    </details>

    {{-- ─── ۳) لیست قالب‌های پیامک ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
        <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-3">📩 رویدادهای پیامکی</h2>

        @foreach($templates as $tpl)
            @php
                $recipientColor = $tpl->recipient === 'technician' ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700';
                $recipientLabel = $tpl->recipient === 'technician' ? 'تکنسین' : 'مشتری';
            @endphp
            <form method="POST" action="{{ route('crm.sms-management.template.update', $tpl) }}"
                  class="border-2 {{ $tpl->is_active ? 'border-emerald-200' : 'border-gray-200' }} rounded-lg p-4 mb-3">
                @csrf
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <input type="text" name="title" value="{{ $tpl->title }}"
                                   class="font-bold text-sm text-gray-900 dark:text-gray-100 bg-transparent border-b border-transparent hover:border-gray-300 focus:border-brand-500 focus:outline-none px-1 flex-1 min-w-0">
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $recipientColor }}">{{ $recipientLabel }}</span>
                            <code class="text-[10px] text-gray-400">{{ $tpl->trigger_key }}</code>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-xs whitespace-nowrap">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" {{ $tpl->is_active ? 'checked' : '' }} class="w-4 h-4">
                        <strong class="{{ $tpl->is_active ? 'text-emerald-700' : 'text-gray-500' }}">
                            {{ $tpl->is_active ? 'فعال' : 'غیرفعال' }}
                        </strong>
                    </label>
                </div>

                <textarea name="body" rows="3"
                          class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm font-mono leading-7"
                          placeholder="متن پیامک با متغیرهای {var}...">{{ $tpl->body }}</textarea>

                <div class="flex justify-end mt-2">
                    <button type="submit" class="px-4 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded text-xs font-bold">
                        ذخیره
                    </button>
                </div>
            </form>
        @endforeach
    </div>

    {{-- ─── ۴) تست ارسال ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 border-2 border-amber-200">
        <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-3">🧪 تست ارسال پیامک</h2>
        <form method="POST" action="{{ route('crm.sms-management.test') }}" class="grid grid-cols-1 md:grid-cols-3 gap-2">
            @csrf
            <input type="text" name="mobile" placeholder="09xxxxxxxxx" dir="ltr"
                   class="px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
            <input type="text" name="body" placeholder="متن پیامک تست"
                   class="md:col-span-2 px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded text-sm">
            <button type="submit" class="md:col-span-3 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded text-sm font-bold">
                ارسال تست
            </button>
        </form>
    </div>

</div>
@endsection
