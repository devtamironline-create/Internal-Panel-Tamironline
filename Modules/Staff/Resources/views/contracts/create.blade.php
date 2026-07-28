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

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <label class="block text-xs text-gray-500">تاریخ قرارداد *
                    <input type="date" name="contract_date" required value="{{ old('contract_date', now()->toDateString()) }}" dir="ltr"
                           class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                </label>
                <label class="block text-xs text-gray-500">تاریخ شروع *
                    <input type="date" name="start_date" required value="{{ old('start_date', now()->toDateString()) }}" dir="ltr"
                           class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                </label>
                <label class="block text-xs text-gray-500">تاریخ پایان
                    <input type="date" name="end_date" value="{{ old('end_date') }}" dir="ltr"
                           class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                </label>
                <label class="block text-xs text-gray-500">مزد ماهانه (تومان)
                    <input type="number" name="monthly_wage" min="0" value="{{ old('monthly_wage') }}" dir="ltr" placeholder="22000000"
                           class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                </label>
                <label class="block text-xs text-gray-500">نرخ ساعتی روز تعطیل (تومان)
                    <input type="number" name="holiday_hourly_rate" min="0" value="{{ old('holiday_hourly_rate') }}" dir="ltr" placeholder="150000"
                           class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                </label>
                <label class="block text-xs text-gray-500">مبلغ سفته ضمانت (تومان)
                    <input type="number" name="promissory_amount" min="0" value="{{ old('promissory_amount') }}" dir="ltr" placeholder="100000000"
                           class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                </label>
                <label class="block text-xs text-gray-500">مدت عدم جذب (ماه)
                    <input type="number" name="non_solicit_months" min="0" max="120" value="{{ old('non_solicit_months', 24) }}" dir="ltr"
                           class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                </label>
                <label class="block text-xs text-gray-500">مهلت بررسی تأخیر پرداخت (روز کاری)
                    <input type="number" name="payment_grace_days" min="0" max="60" value="{{ old('payment_grace_days', 3) }}" dir="ltr"
                           class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                </label>
                <label class="block text-xs text-gray-500">مدت محرمانگی پس از پایان (سال)
                    <input type="number" name="confidentiality_years" min="0" max="50" value="{{ old('confidentiality_years', 5) }}" dir="ltr"
                           class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                </label>
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
