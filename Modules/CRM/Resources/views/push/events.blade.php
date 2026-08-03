@extends('layouts.admin')

@section('page-title', 'رویدادهای پوش')

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-6xl">

    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">🔔 رویدادهای پوش</h1>
        <p class="text-xs text-gray-500 mt-1">
            قالب، ماندگاری و ساعت مجاز هر رویداد. فیلد خالی یعنی «از پیش‌فرض کد استفاده کن» —
            پس اگر بعداً متن پیش‌فرض بهتر شد، خودبه‌خود اعمال می‌شود.
        </p>
    </div>

    @include('crm::push._nav')

    <div class="bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-800 rounded-lg p-3 text-xs text-sky-800 dark:text-sky-200">
        پنجرهٔ ساعت مجاز سراسری: <b>{{ $window['start'] }}</b> تا <b>{{ $window['end'] }}</b>.
        رویدادهایی که «مهلت‌دار» نشانه‌گذاری نشده‌اند در این بازه ارسال می‌شوند؛
        بقیه هر ساعتی می‌روند چون اعلانی که تا صبح نگه داشته شود بی‌معنا می‌شود.
    </div>

    @foreach($rows as $row)
        @php
            $event = $row['event'];
            $setting = $row['setting'];
            $enabled = $setting?->is_enabled ?? true;
        @endphp

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border-2 {{ $enabled ? 'border-gray-100 dark:border-gray-700' : 'border-gray-300 dark:border-gray-600 opacity-70' }} p-5 space-y-4">

            <div class="flex items-start justify-between flex-wrap gap-3">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">
                        {{ $event->label() }}
                        @unless($enabled)<span class="text-xs font-normal text-rose-600">— غیرفعال</span>@endunless
                    </h2>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <span class="text-[11px] px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 font-mono">{{ $event->value }}</span>
                        @if($row['governed'])
                            <span class="text-[11px] px-2 py-0.5 rounded bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300">تابع ساعت مجاز</span>
                        @else
                            <span class="text-[11px] px-2 py-0.5 rounded bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300">مهلت‌دار — هر ساعت</span>
                        @endif
                        @if($row['deduped'])
                            <span class="text-[11px] px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300">ضدتکرار فعال</span>
                        @endif
                        <span class="text-[11px] px-2 py-0.5 rounded bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">
                            {{ number_format($row['sent_30d']) }} ارسال در ۳۰ روز
                        </span>
                    </div>
                </div>

                <form method="POST" action="{{ route('crm.push.events.toggle', $event->value) }}">
                    @csrf
                    <button type="submit" class="text-xs px-3 py-2 rounded-lg {{ $enabled
                        ? 'bg-rose-100 hover:bg-rose-200 text-rose-700'
                        : 'bg-emerald-100 hover:bg-emerald-200 text-emerald-700' }}">
                        {{ $enabled ? 'غیرفعال کن' : 'فعال کن' }}
                    </button>
                </form>
            </div>

            <form method="POST" action="{{ route('crm.push.events.update', $event->value) }}" class="space-y-3">
                @csrf

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-medium text-gray-700 dark:text-gray-200">عنوان</label>
                        <span class="text-[11px] text-gray-400">حداکثر {{ $titleMax }} کاراکتر</span>
                    </div>
                    <input type="text" name="title" maxlength="{{ $titleMax }}"
                           value="{{ old('title', $setting?->title) }}"
                           placeholder="{{ $row['default_title'] }}"
                           class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-medium text-gray-700 dark:text-gray-200">متن</label>
                        <span class="text-[11px] text-gray-400">حداکثر {{ $bodyMax }} کاراکتر</span>
                    </div>
                    <textarea name="body" rows="2" maxlength="{{ $bodyMax }}"
                              placeholder="{{ $row['default_body'] }}"
                              class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">{{ old('body', $setting?->body) }}</textarea>
                    <p class="text-[11px] text-gray-500 mt-1">
                        متغیرهای مجاز:
                        @foreach($row['variables'] as $variable)
                            <code class="px-1 rounded bg-gray-100 dark:bg-gray-700">{{ '{'.$variable.'}' }}</code>
                        @endforeach
                        @if($row['variables'] === [])<span class="text-gray-400">— ندارد</span>@endif
                    </p>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">ماندگاری (ساعت)</label>
                        <input type="number" name="ttl" min="1" max="720"
                               value="{{ old('ttl', $setting?->ttl) }}"
                               placeholder="{{ $row['default_ttl'] }}"
                               class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">ساعت مجاز — از</label>
                        <input type="number" name="quiet_start" min="0" max="23"
                               value="{{ old('quiet_start', $setting?->quiet_start) }}"
                               placeholder="{{ $row['governed'] ? $window['start'] : 'بدون محدودیت' }}"
                               class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">تا</label>
                        <input type="number" name="quiet_end" min="0" max="23"
                               value="{{ old('quiet_end', $setting?->quiet_end) }}"
                               placeholder="{{ $row['governed'] ? $window['end'] : 'بدون محدودیت' }}"
                               class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-200 pb-2">
                            <input type="hidden" name="send_sms" value="0">
                            <input type="checkbox" name="send_sms" value="1" @checked($setting?->send_sms)
                                   class="rounded border-gray-300 text-indigo-600">
                            پیامک هم بفرست
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <p class="text-[11px] text-gray-400">
                        فیلد خالی = پیش‌فرض کد. برای پنجرهٔ ساعت باید هر دو مقدار پر یا هر دو خالی باشند.
                    </p>
                    <button type="submit" class="text-xs px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold">
                        ذخیره
                    </button>
                </div>
            </form>
        </div>
    @endforeach

    <div class="bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-lg p-3 text-[11px] text-gray-600 dark:text-gray-400 leading-relaxed">
        سوئیچ «پیامک هم بفرست» فقط ثبت می‌شود و امروز مسیرهای پیامک را تغییر نمی‌دهد؛
        رویدادهای «تخصیص سفارش» و «سررسید مهلت» از قبل هر دو کانال را دارند.
    </div>
</div>
@endsection
