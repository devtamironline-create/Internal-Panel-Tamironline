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

    {{-- ─── هشدار آمادگی: تکنسین بدون «نوع خدمات» کنار گذاشته می‌شود ─── --}}
    @if($withoutServiceTypes->isNotEmpty())
        <div class="bg-amber-50 border border-amber-300 rounded-xl p-4">
            <div class="font-bold text-sm text-amber-800">
                ⚠ {{ number_format($withoutServiceTypes->count()) }} تکنسین از {{ number_format($activeTechnicianCount) }} تکنسین فعال، «نوع خدمات قابل ارائه» ندارند
            </div>
            <p class="text-[11px] text-amber-700 leading-6 mt-1">
                این‌ها نه در پیشنهاد هوشمند دیده می‌شوند و نه سفارش خودکار می‌گیرند، چون معلوم نیست تعمیر انجام می‌دهند یا نصب.
                برای هرکدام وارد پروفایلش شوید و نوع خدماتش را تیک بزنید.
            </p>
            <div class="flex flex-wrap gap-1.5 mt-2">
                @foreach($withoutServiceTypes->take(30) as $t)
                    <a href="{{ route('crm.technicians.edit', $t) }}"
                       class="text-[11px] bg-white border border-amber-300 rounded px-2 py-1 text-amber-800 hover:bg-amber-100">
                        {{ trim($t->firstname_tech ?: $t->first_name) ?: 'تکنسین #'.$t->id }}
                    </a>
                @endforeach
                @if($withoutServiceTypes->count() > 30)
                    <span class="text-[11px] text-amber-700 self-center">و {{ $withoutServiceTypes->count() - 30 }} نفر دیگر…</span>
                @endif
            </div>
        </div>
    @endif

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

        {{-- ─── تکنسین سابق ─── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
            <h2 class="font-bold text-sm text-gray-800 dark:text-gray-100">تکنسین قبلیِ همان دستگاه</h2>

            <label class="flex items-start gap-3 cursor-pointer">
                <input type="hidden" name="assign_history_enabled" value="0">
                <input type="checkbox" name="assign_history_enabled" value="1" class="mt-1"
                       @checked($settings['assign_history_enabled'])>
                <div>
                    <div class="text-sm font-bold">اگر مشتری همان دستگاه را دوباره ثبت کرد، همان تکنسین قبلی</div>
                    <div class="text-[11px] text-gray-500 leading-5 mt-0.5">
                        شرط‌ها: سفارش قبلی «انجام کار» شده باشد، تکنسین هنوز فعال باشد، و قیدهای عملیاتی
                        (ظرفیت، آمادهٔ دریافت بودن، پوشش شهر/برند/دستگاه) برقرار باشد. اگر روی آن دستگاه سابقهٔ
                        «برگشتی گارانتی» وجود داشته باشد این قاعده اعمال نمی‌شود.
                    </div>
                </div>
            </label>

            <label class="block">
                <span class="text-xs text-gray-600 dark:text-gray-300">اعتبار سابقه (ماه)</span>
                <input type="number" name="assign_history_months" min="1" max="60" dir="ltr"
                       value="{{ old('assign_history_months', $settings['assign_history_months']) }}"
                       class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                <span class="text-[11px] text-gray-400 leading-5 block mt-1">
                    سفارش‌های قدیمی‌تر از این بازه دیگر ملاک نیستند.
                </span>
            </label>

            <div class="text-[11px] text-gray-500 bg-gray-50 dark:bg-gray-900/40 rounded-lg p-2.5 leading-6">
                <b>ترتیب اولویت در انتخاب تکنسین:</b>
                ۱) تکنسینی که همین امروز روی همین آدرس سفارش دارد ←
                ۲) کم‌کردن تعداد مراجعه (کسی که بیشترین سفارش این آدرس را پوشش دهد) ←
                ۳) تکنسین قبلیِ همان دستگاه ←
                ۴) امتیاز کیفی.
            </div>
        </div>

        {{-- ─── وزن‌های امتیازدهی ─── --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3"
             x-data="{
                 w: {{ Illuminate\Support\Js::from($settings['weights_raw']) }},
                 defaults: {{ Illuminate\Support\Js::from($weightDefaults) }},
                 get total() { return Object.values(this.w).reduce((a, b) => a + (Number(b) || 0), 0) },
                 share(key) {
                     const t = this.total
                     return t > 0 ? Math.round((Number(this.w[key]) || 0) / t * 100) : 0
                 },
                 reset() { this.w = Object.assign({}, this.defaults) }
             }">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <h2 class="font-bold text-sm text-gray-800 dark:text-gray-100">وزن معیارهای انتخاب تکنسین</h2>
                <button type="button" @click="reset()"
                        class="text-[11px] px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-gray-600 dark:text-gray-300">
                    بازگردانی به پیش‌فرض
                </button>
            </div>

            <p class="text-[11px] text-gray-500 leading-6">
                این‌ها تعیین می‌کنند امتیاز ۰ تا ۱۰۰ هر تکنسین چطور ساخته شود. لازم نیست جمعشان ۱۰۰ باشد —
                سیستم نسبت‌ها را خودش به ۱۰۰ تبدیل می‌کند و «سهم مؤثر» را همین‌جا نشان می‌دهد.
                اگر همه را صفر بگذارید، پیش‌فرض‌ها اعمال می‌شوند.
            </p>

            <div class="space-y-2">
                @foreach($weightLabels as $key => $label)
                    <div class="flex items-center gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="text-xs text-gray-700 dark:text-gray-200">{{ $label }}</div>
                            <div class="h-1.5 mt-1 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full bg-brand-500 transition-all"
                                     :style="`width: ${share('{{ $key }}')}%`"></div>
                            </div>
                        </div>
                        <input type="number" name="weights[{{ $key }}]" min="0" max="100" dir="ltr"
                               x-model.number="w['{{ $key }}']"
                               class="w-20 px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm text-center">
                        <span class="w-16 text-[11px] text-gray-500 text-left" dir="ltr">
                            <span x-text="share('{{ $key }}')"></span>٪
                        </span>
                    </div>
                @endforeach
            </div>

            <div class="text-[11px] text-gray-500 border-t border-gray-100 dark:border-gray-700 pt-2">
                مجموع واردشده: <b x-text="total"></b>
                <span x-show="total !== 100" class="text-amber-600">
                    — به نسبت به ۱۰۰ تبدیل می‌شود
                </span>
            </div>

            <div class="text-[11px] text-gray-500 bg-gray-50 dark:bg-gray-900/40 rounded-lg p-2.5 leading-6">
                <b>سطح‌بندی امتیاز:</b> ۸۰ به بالا «پیشنهاد ویژه» · ۶۰ به بالا «مناسب» · ۴۰ به بالا «قابل بررسی» ·
                ۲۰ به بالا «احتیاط» · کمتر «غیرقابل توصیه». همین امتیاز است که با «حداقل امتیاز برای تخصیص خودکار» سنجیده می‌شود.
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
            @if($preview['skipped_no_type'] > 0)
                <div class="mt-2 text-[11px] text-rose-700 bg-rose-50 border border-rose-200 rounded-lg px-2.5 py-2 leading-6">
                    {{ number_format($preview['skipped_no_type']) }} سفارش «نوع خدمت» ندارند و اصلاً وارد پخش خودکار نمی‌شوند —
                    بدون آن نمی‌شود فهمید تعمیر است یا نصب. نوعشان را در صفحهٔ سفارش تعیین کنید.
                </div>
            @endif
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
