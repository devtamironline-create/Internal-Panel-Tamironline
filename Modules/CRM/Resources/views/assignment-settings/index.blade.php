@extends('layouts.admin')

@section('page-title', 'پخش سفارش بین تکنسین‌ها')

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-3xl">

    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">⚙️ پخش سفارش بین تکنسین‌ها</h1>
        <p class="text-xs text-gray-500 mt-1">
            تعیین می‌کند سیستم فقط پیشنهاد بدهد یا خودش سفارش‌ها را تخصیص دهد. در هر دو حالت،
            سفارش‌های یک مشتری با یک آدرس در یک روز تا حد امکان به یک تکنسین می‌رسند.
        </p>
    </div>

    <form method="POST" action="{{ route('crm.assignment-settings.update') }}" class="space-y-4"
          x-data="{ mode: '{{ $settings['assign_mode'] }}' }">
        @csrf

        {{-- ─── کلید اصلی ─── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
            <h2 class="font-bold text-sm text-gray-800 dark:text-gray-100">حالت کار</h2>

            <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition"
                   :class="mode === 'suggest' ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-gray-200 dark:border-gray-700'">
                <input type="radio" name="assign_mode" value="suggest" x-model="mode" class="mt-1">
                <div>
                    <div class="text-sm font-bold">فقط پیشنهاد بده</div>
                    <div class="text-[11px] text-gray-500 leading-5 mt-0.5">
                        رفتار فعلی پنل. سیستم نقشهٔ تخصیص را نشان می‌دهد ولی تا وقتی اپراتور دکمه را نزند
                        هیچ سفارشی تخصیص داده نمی‌شود.
                    </div>
                </div>
            </label>

            <label class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition"
                   :class="mode === 'auto' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-700'">
                <input type="radio" name="assign_mode" value="auto" x-model="mode" class="mt-1">
                <div>
                    <div class="text-sm font-bold">خودکار پخش کن</div>
                    <div class="text-[11px] text-gray-500 leading-5 mt-0.5">
                        هر ۵ دقیقه سفارش‌های بدون تکنسین گروه‌بندی و تخصیص داده می‌شوند.
                        دلیل هر تخصیص در «گزارش تخصیص تکنسین» ثبت می‌شود.
                    </div>
                </div>
            </label>
        </div>

        {{-- ─── پارامترها ─── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
            <h2 class="font-bold text-sm text-gray-800 dark:text-gray-100">تنظیمات پخش خودکار</h2>

            <label class="block">
                <span class="text-xs text-gray-600 dark:text-gray-300">مهلت انتظار پیش از پخش (دقیقه)</span>
                <input type="number" name="assign_grace_minutes" min="0" max="1440" dir="ltr"
                       value="{{ old('assign_grace_minutes', $settings['assign_grace_minutes']) }}"
                       class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                <span class="text-[11px] text-gray-400 leading-5 block mt-1">
                    سفارش تازه‌ثبت‌شده بلافاصله پخش نمی‌شود تا اگر مشتری دارد چند دستگاه پشت سر هم ثبت می‌کند،
                    همه در یک نقشه کنار هم قرار بگیرند و به یک تکنسین برسند. صفر یعنی بدون انتظار.
                </span>
            </label>

            <label class="block">
                <span class="text-xs text-gray-600 dark:text-gray-300">حداقل امتیاز برای تخصیص خودکار</span>
                <input type="number" name="assign_min_score" min="0" max="100" dir="ltr"
                       value="{{ old('assign_min_score', $settings['assign_min_score']) }}"
                       class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                <span class="text-[11px] text-gray-400 leading-5 block mt-1">
                    اگر بهترین گزینه امتیازی کمتر از این داشته باشد، سیستم دست نمی‌زند و سفارش برای تصمیم اپراتور می‌ماند.
                    (تکنسینی که از قبل روی همان آدرس نشسته از این قاعده مستثناست.)
                </span>
            </label>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="block">
                    <span class="text-xs text-gray-600 dark:text-gray-300">سقف سفارش در هر اجرا</span>
                    <input type="number" name="assign_max_per_run" min="1" max="500" dir="ltr"
                           value="{{ old('assign_max_per_run', $settings['assign_max_per_run']) }}"
                           class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </label>
                <label class="block">
                    <span class="text-xs text-gray-600 dark:text-gray-300">حداکثر قدمت سفارش (روز)</span>
                    <input type="number" name="assign_max_age_days" min="1" max="90" dir="ltr"
                           value="{{ old('assign_max_age_days', $settings['assign_max_age_days']) }}"
                           class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    <span class="text-[11px] text-gray-400 leading-5 block mt-1">
                        سفارش‌های قدیمی‌تر از این معمولاً دلیل انسانی دارند و خودکار پخش نمی‌شوند.
                    </span>
                </label>
            </div>
        </div>

        {{-- ─── پیش‌نمایش ─── --}}
        <div class="bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <h2 class="font-bold text-sm text-gray-800 dark:text-gray-100 mb-2">اگر همین حالا اجرا می‌شد</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-center">
                <div class="bg-white dark:bg-gray-800 rounded-lg p-2.5 border border-gray-200 dark:border-gray-700">
                    <div class="text-[10px] text-gray-500">سفارش واجد شرایط</div>
                    <div class="text-lg font-bold">{{ number_format($pendingCount) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-2.5 border border-gray-200 dark:border-gray-700">
                    <div class="text-[10px] text-gray-500">گروه</div>
                    <div class="text-lg font-bold">{{ number_format($preview['groups']) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-2.5 border border-emerald-200">
                    <div class="text-[10px] text-gray-500">تخصیص می‌شد</div>
                    <div class="text-lg font-bold text-emerald-600">{{ number_format($preview['assigned']) }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-2.5 border border-amber-200">
                    <div class="text-[10px] text-gray-500">بدون تکنسین می‌ماند</div>
                    <div class="text-lg font-bold text-amber-600">{{ number_format($preview['unassignable'] + $preview['skipped_low_score']) }}</div>
                </div>
            </div>
            <p class="text-[11px] text-gray-400 mt-2 leading-5">
                این محاسبه فقط شبیه‌سازی است و هیچ سفارشی را تخصیص نداده.
            </p>
        </div>

        <button class="w-full md:w-auto px-8 py-3 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-sm font-bold">
            ذخیره تنظیمات
        </button>
    </form>
</div>
@endsection
