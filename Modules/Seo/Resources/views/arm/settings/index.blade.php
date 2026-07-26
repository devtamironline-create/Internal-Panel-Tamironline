@extends('layouts.admin')

@section('page-title', 'بازوی سئو — تنظیمات')

@section('main')
<div class="p-6 space-y-4 max-w-3xl" dir="rtl">
    <div>
        <h1 class="text-xl font-bold text-gray-800 dark:text-white">⚙️ تنظیمات بازوی سئو</h1>
        <p class="text-sm text-gray-500 mt-1">پیکربندی پروژه، اهداف تولید محتوا و وضعیت یکپارچه‌سازی‌ها.</p>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif

    <form method="POST" action="{{ route('seo.arm.settings.update') }}" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4">
        @csrf @method('PUT')
        <h2 class="font-bold text-sm text-gray-800 dark:text-gray-100">پروژه</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <div class="text-xs text-gray-500">
                <div class="mb-1">نام / دامنه</div>
                <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg text-gray-700 dark:text-gray-200">{{ $project->name }} — {{ $project->domain }}</div>
            </div>
            <div class="text-xs text-gray-500">
                <div class="mb-1">شروع برنامه / منطقهٔ زمانی</div>
                <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg text-gray-700 dark:text-gray-200" dir="ltr">
                    {{ $project->start_date?->format('Y-m-d') }} · {{ $project->timezone }}
                </div>
            </div>
            <label class="block text-xs text-gray-500">تاریخ هدف (پایان دوره)
                <input type="date" name="target_date" value="{{ $project->target_date?->format('Y-m-d') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm" dir="ltr">
            </label>
            <div class="grid grid-cols-2 gap-2">
                <label class="block text-xs text-gray-500">هدف محتوای جدید/هفته
                    <input type="number" min="0" max="20" name="weekly_new_content_target" value="{{ ($project->settings['weekly_new_content_target'] ?? 2) }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm" dir="ltr">
                </label>
                <label class="block text-xs text-gray-500">هدف به‌روزرسانی/هفته
                    <input type="number" min="0" max="20" name="weekly_refresh_target" value="{{ ($project->settings['weekly_refresh_target'] ?? 2) }}" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm" dir="ltr">
                </label>
            </div>
        </div>
        <label class="block text-xs text-gray-500">توضیح استراتژی
            <textarea name="description" rows="3" class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm leading-6">{{ $project->description }}</textarea>
        </label>
        <button class="px-6 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">ذخیرهٔ تنظیمات</button>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-3">
        <h2 class="font-bold text-sm text-gray-800 dark:text-gray-100">وزن‌های امتیازدهی</h2>
        <p class="text-[11px] text-gray-400">این وزن‌ها از config خوانده می‌شوند (<code class="font-mono ltr">seo.arm.*</code>) و بدون تغییر کد از طریق env/config قابل تنظیم‌اند.</p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-center text-xs">
            @foreach($weights as $key => $val)
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2">
                    <div class="font-black text-gray-800 dark:text-gray-100" dir="ltr">{{ $val }}</div>
                    <div class="text-[10px] text-gray-500" dir="ltr">{{ $key }}</div>
                </div>
            @endforeach
        </div>
        <div class="grid grid-cols-2 md:grid-cols-6 gap-2 text-center text-xs">
            @foreach($healthWeights as $key => $val)
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2">
                    <div class="font-black text-gray-800 dark:text-gray-100" dir="ltr">{{ $val }}٪</div>
                    <div class="text-[10px] text-gray-500" dir="ltr">{{ $key }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="font-bold text-sm text-gray-800 dark:text-gray-100 mb-3">یکپارچه‌سازی‌ها</h2>
        <div class="space-y-2 text-sm">
            <div class="flex items-center justify-between py-2 border-b border-gray-50 dark:border-gray-700/50">
                <span class="text-gray-600 dark:text-gray-300">منبع دادهٔ فعال</span>
                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 rounded-full text-[11px] font-bold" dir="ltr">{{ $provider }}</span>
            </div>
            @foreach(['search_console' => 'Google Search Console (از طریق Windsor)', 'ga4' => 'GA4 (لید و تبدیل)', 'semrush' => 'SEMrush (حجم جستجو و رقبا)', 'ai' => 'دستیار هوش مصنوعی'] as $key => $label)
                <div class="flex items-center justify-between py-2 border-b border-gray-50 dark:border-gray-700/50 last:border-0">
                    <span class="text-gray-600 dark:text-gray-300">{{ $label }}</span>
                    @if(($integrations[$key] ?? false))
                        <span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-full text-[11px] font-bold">فعال</span>
                    @else
                        <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-300 rounded-full text-[11px]">فاز بعد</span>
                    @endif
                </div>
            @endforeach
        </div>
        <p class="text-[11px] text-gray-400 mt-3">در فاز ۱ هیچ اتصال خارجی‌ای برقرار نمی‌شود؛ معماری provider آماده است و با روشن‌کردن هر سوییچ در config و افزودن آداپتور مربوطه، داده بدون تغییر UI زنده می‌شود.</p>
    </div>
</div>
@endsection
