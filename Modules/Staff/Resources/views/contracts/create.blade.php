@extends('layouts.admin')

@section('page-title', 'صدور قرارداد کارمندان')

@section('main')
<div class="p-6 space-y-4" dir="rtl" x-data="{ selected: [] }">
    <div>
        <h1 class="text-xl font-bold text-gray-800 dark:text-white">➕ صدور قرارداد کارمندان</h1>
        <p class="text-sm text-gray-500 mt-1">
            کارمندانی را که این قرارداد برایشان صادر می‌شود تیک بزنید. برای هر نفر یک قرارداد جداگانه با شمارهٔ اختصاصی ساخته می‌شود
            و پس از صدور، خودِ کارمند در بخش «قرارداد من» مدارک را بارگذاری و امضا می‌کند.
        </p>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">
            <ul class="list-disc pr-5 space-y-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.staff-contracts.store') }}" class="space-y-4">
        @csrf

        {{-- انتخاب نسخهٔ قرارداد --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4"
             x-data="{ version: '{{ old('version', 1) }}' }">
            <h2 class="font-bold text-sm text-gray-800 dark:text-gray-100 mb-3">نسخهٔ قرارداد</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach(\Modules\Staff\Models\StaffContract::VERSIONS as $vNum => $vLabel)
                    <label class="flex items-start gap-2 p-3 border rounded-lg cursor-pointer border-gray-300 dark:border-gray-600"
                           :class="version === '{{ $vNum }}' ? 'bg-brand-50 border-brand-400 dark:bg-brand-900/20' : ''">
                        <input type="radio" name="version" value="{{ $vNum }}" class="mt-1" x-model="version"
                               @checked((int) old('version', 1) === $vNum) required>
                        <span>
                            <span class="block text-sm font-medium text-gray-800 dark:text-gray-100">{{ $vLabel }}</span>
                            <span class="block text-[11px] text-gray-500 mt-0.5">
                                {{ $vNum === 1 ? 'مشاوره‌ای/پروژه‌ای — بدون رابطهٔ کارگری، بیمه و سنوات.' : 'قرارداد کار با مدت معین طبق قانون کار (کارمند کال‌سنتر).' }}
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- انتخاب کارمندان --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
                <h2 class="font-bold text-sm text-gray-800 dark:text-gray-100">۱) انتخاب کارمندان</h2>
                <div class="flex items-center gap-2 text-xs">
                    <span class="text-gray-500">انتخاب‌شده: <b x-text="selected.length"></b> نفر</span>
                    <button type="button" @click="selected = $refs.list ? Array.from($refs.list.querySelectorAll('input[name=\'user_ids[]\']')).map(i => i.value) : []"
                            class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-gray-600 dark:text-gray-300">انتخاب همه</button>
                    <button type="button" @click="selected = []" class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-gray-600 dark:text-gray-300">لغو انتخاب</button>
                </div>
            </div>

            @if($staff->isEmpty())
                <p class="text-sm text-gray-400 py-6 text-center">هیچ کارمندی ثبت نشده است. ابتدا از بخش «مدیریت کارمندان» کارمند اضافه کنید.</p>
            @else
                <div x-ref="list" class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-[420px] overflow-y-auto">
                    @foreach($staff as $person)
                        <label @class([
                            'flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition',
                            'border-gray-200 dark:border-gray-700 hover:border-brand-400',
                        ]) :class="selected.includes('{{ $person->id }}') ? 'bg-brand-50 dark:bg-brand-900/20 border-brand-400' : ''">
                            <input type="checkbox" name="user_ids[]" value="{{ $person->id }}" x-model="selected"
                                   class="mt-1 rounded border-gray-300 text-brand-600">
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-sm text-gray-800 dark:text-gray-100">{{ $person->full_name }}</div>
                                <div class="text-[11px] text-gray-400" dir="ltr">{{ $person->mobile }}</div>

                                {{-- مشخصات اختصاصی این قرارداد (نمایش فقط وقتی تیک خورده) --}}
                                <div x-show="selected.includes('{{ $person->id }}')" x-cloak class="mt-2 grid grid-cols-2 gap-2" @click.stop>
                                    <select name="party[{{ $person->id }}][title]" class="px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-[11px]">
                                        <option value="آقای">آقای</option>
                                        <option value="خانم">خانم</option>
                                    </select>
                                    <input name="party[{{ $person->id }}][father_name]" placeholder="نام پدر"
                                           class="px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-[11px]">
                                    <input name="party[{{ $person->id }}][national_code]" value="{{ $person->national_code }}" placeholder="کد ملی" dir="ltr"
                                           class="px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-[11px]">
                                    <input name="party[{{ $person->id }}][promissory_serial]" placeholder="شماره سفته" dir="ltr"
                                           class="px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-[11px]">
                                    <input name="party[{{ $person->id }}][address]" value="{{ $person->address }}" placeholder="نشانی محل سکونت"
                                           class="col-span-2 px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-[11px]">
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
                <p class="text-[11px] text-gray-400 mt-2">مواردی که خالی بمانند از پروفایل همان کارمند پر می‌شوند. این مشخصات در لحظهٔ صدور در قرارداد ثبت (snapshot) می‌شود و تغییر بعدی پروفایل، سند امضاشده را عوض نمی‌کند.</p>
            @endif
        </div>

        {{-- شرایط قرارداد --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
            <h2 class="font-bold text-sm text-gray-800 dark:text-gray-100">۲) شرایط قرارداد</h2>

            <label class="block text-xs text-gray-500">شرح خدمات *
                <textarea name="service_description" rows="2" required
                          class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm"
                          placeholder="مثال: نظارت و بررسی و ارتباط با مشتریان به منظور کنترل کیفیت">{{ old('service_description') }}</textarea>
            </label>

            @php
                $today = \App\Support\JalaliDate::toJalali(now()->toDateString());
                $hint = 'mt-1 text-[11px] text-gray-400 leading-5';
                $box = 'mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm';
            @endphp

            {{-- تاریخ‌ها شمسی وارد می‌شوند و پیش از ذخیره به میلادی تبدیل
                 می‌شوند؛ ستون دیتابیس میلادی می‌ماند. --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-gray-500">تاریخ قرارداد *</label>
                    <input type="text" name="contract_date" required dir="ltr" placeholder="۱۴۰۵/۰۵/۱۱"
                           value="{{ old('contract_date', $today) }}"
                           class="jalali-datepicker cursor-pointer bg-white {{ $box }}">
                    <p class="{{ $hint }}">تاریخ درجِ سند — در سرآغازِ قرارداد می‌نشیند.</p>
                    @error('contract_date')<p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500">تاریخ شروع *</label>
                    <input type="text" name="start_date" required dir="ltr" placeholder="۱۴۰۵/۰۵/۱۱"
                           value="{{ old('start_date', $today) }}"
                           class="jalali-datepicker cursor-pointer bg-white {{ $box }}">
                    <p class="{{ $hint }}">بند ۳-۱ — آغازِ مدت همکاری.</p>
                    @error('start_date')<p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500">تاریخ پایان</label>
                    <input type="text" name="end_date" dir="ltr" placeholder="خالی = بدون تاریخ پایان"
                           value="{{ old('end_date') }}"
                           class="jalali-datepicker cursor-pointer bg-white {{ $box }}">
                    <p class="{{ $hint }}">بند ۳-۱ — خاتمهٔ مدت. اگر خالی بماند در متن «—» درج می‌شود.</p>
                    @error('end_date')<p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- تنها مقادیرِ متغیر به‌ازای هر نفر. بندهای ثابتِ قرارداد (عدم
                 جذب، مهلت پرداخت، محرمانگی، نرخ روز تعطیل) از تنظیماتِ مجموعه
                 می‌آیند و این‌جا قابل تغییر نیستند — متنِ قراردادِ پایه آن‌ها را
                 ثابت اعلام کرده. --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-500">مزد ماهانه (تومان)</label>
                    <input type="number" name="monthly_wage" min="0" dir="ltr" placeholder="22000000"
                           value="{{ old('monthly_wage') }}" class="{{ $box }}">
                    <p class="{{ $hint }}">بند ۵-۱ — حق‌الزحمهٔ ماهانهٔ همین کارمند.</p>
                </div>
                <div>
                    <label class="block text-xs text-gray-500">مبلغ سفته ضمانت (تومان)</label>
                    <input type="number" name="promissory_amount" min="0" dir="ltr" placeholder="100000000"
                           value="{{ old('promissory_amount') }}" class="{{ $box }}">
                    <p class="{{ $hint }}">بند ۱۴-۱ — مبلغِ سفتهٔ تضمین. شمارهٔ سفته را بالاتر، جلوی نامِ هر کارمند وارد کنید.</p>
                </div>
            </div>

            <div class="text-[11px] text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700/40 rounded-lg p-3 leading-6">
                <b class="text-gray-600 dark:text-gray-300">بندهای ثابتِ قرارداد</b> — این‌ها برای همهٔ کارمندان یکسان‌اند و
                از تنظیماتِ مجموعه خوانده می‌شوند، چون متنِ قراردادِ پایه آن‌ها را ثابت اعلام کرده:
                <span class="whitespace-nowrap">مدت عدم جذب (بند ۱۳-۱): <b>{{ $party1['contract_non_solicit_months'] ?: '۲۴' }} ماه</b></span> ·
                <span class="whitespace-nowrap">مهلت بررسی تأخیر پرداخت (بند ۷-۱): <b>{{ $party1['contract_payment_grace_days'] ?: '۳' }} روز کاری</b></span> ·
                <span class="whitespace-nowrap">مدت محرمانگی (بند ۱۰-۵): <b>{{ $party1['contract_confidentiality_years'] ?: '۵' }} سال</b></span> ·
                <span class="whitespace-nowrap">نرخ ساعتی روز تعطیل (بند ۵-۲):
                    <b>{{ $party1['contract_holiday_hourly_rate'] ? number_format((int) $party1['contract_holiday_hourly_rate']).' تومان' : 'تعیین‌نشده' }}</b></span>
            </div>
        </div>

        {{-- طرف اول --}}
        <div class="bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700 p-4 text-xs text-gray-600 dark:text-gray-300">
            <b class="text-gray-700 dark:text-gray-200">طرف اول (مجموعه):</b>
            {{ $party1['contract_first_party_name'] }} — کد ملی {{ $party1['contract_first_party_national_code'] }} — {{ $party1['contract_first_party_phone'] }}
            <div class="text-[11px] text-gray-400 mt-1">این مقادیر از تنظیمات سیستم خوانده می‌شوند و در همهٔ قراردادها یکسان‌اند.</div>
        </div>

        <div class="sticky bottom-4">
            <button type="submit" :disabled="selected.length === 0"
                    class="w-full md:w-auto px-8 py-3 bg-brand-600 hover:bg-brand-700 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-xl text-sm font-bold shadow-lg">
                📄 صدور قرارداد برای <span x-text="selected.length"></span> کارمند
            </button>
        </div>
    </form>
</div>
@endsection
