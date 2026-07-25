@extends('layouts.admin')

@section('page-title', 'قالب‌های پیام مشتری')

@section('main')
<div class="p-6 space-y-4">
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">📨 قالب‌های پیامِ اطلاع‌رسانیِ مشتری</h1>
            <p class="text-sm text-gray-500 mt-1 leading-6">
                این پیام‌ها هم در <b>نوتیفیکیشنِ اپ</b> و هم در <b>پیام‌رسانِ بله</b> برای مشتری ارسال می‌شوند.
                هر رویداد را می‌توانید خاموش کنید یا متنش را شخصی‌سازی کنید.
            </p>
        </div>
        <form method="POST" action="{{ route('crm.notify-templates.reset') }}"
              onsubmit="return confirm('همهٔ قالب‌ها به پیش‌فرضِ حرفه‌ایِ سیستم برگردد؟');">
            @csrf
            <button type="submit" class="px-3 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">↺ بازگشت به پیش‌فرض</button>
        </form>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-800 rounded-xl p-3 text-xs text-sky-800 dark:text-sky-300 leading-6">
        <b>placeholderهای قابل استفاده:</b>
        <code dir="ltr">{order_code}</code> کد پیگیری،
        <code dir="ltr">{customer_name}</code> نام مشتری،
        <code dir="ltr">{device}</code> دستگاه،
        <code dir="ltr">{brand}</code> برند,
        <code dir="ltr">{technician}</code> نام تکنسین،
        <code dir="ltr">{status}</code> برچسب وضعیت.
    </div>

    <form method="POST" action="{{ route('crm.notify-templates.update') }}" class="space-y-3">
        @csrf
        @foreach($templates as $key => $tpl)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4"
                 x-data="{ on: @js((bool) $tpl['enabled']) }">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div class="font-bold text-sm text-gray-800 dark:text-gray-100">{{ $labels[$key] ?? $key }}</div>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <span class="text-xs" :class="on ? 'text-emerald-600 font-bold' : 'text-gray-400'" x-text="on ? 'فعال' : 'خاموش'"></span>
                        <input type="hidden" name="templates[{{ $key }}][enabled]" :value="on ? 1 : 0">
                        <input type="checkbox" x-model="on" class="sr-only peer">
                        <div class="relative w-11 h-6 bg-gray-300 peer-checked:bg-emerald-500 rounded-full transition-colors">
                            <div class="absolute top-0.5 start-0.5 bg-white w-5 h-5 rounded-full transition-transform peer-checked:translate-x-5 rtl:peer-checked:-translate-x-5"></div>
                        </div>
                    </label>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3" :class="on ? '' : 'opacity-40 pointer-events-none'">
                    <div>
                        <label class="block text-[11px] text-gray-500 mb-1">تیترِ پیام</label>
                        <input type="text" name="templates[{{ $key }}][title]" value="{{ $tpl['title'] }}" maxlength="200"
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[11px] text-gray-500 mb-1">متنِ پیام</label>
                        <textarea name="templates[{{ $key }}][body]" rows="2" maxlength="1000"
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm leading-6">{{ $tpl['body'] }}</textarea>
                        @if(($defaults[$key]['body'] ?? '') !== $tpl['body'])
                            <p class="text-[10px] text-gray-400 mt-1">پیش‌فرض: {{ $defaults[$key]['body'] ?? '' }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach

        <div class="sticky bottom-4">
            <button type="submit" class="w-full md:w-auto px-8 py-3 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-sm font-bold shadow-lg">
                💾 ذخیرهٔ همهٔ قالب‌ها
            </button>
        </div>
    </form>
</div>
@endsection
