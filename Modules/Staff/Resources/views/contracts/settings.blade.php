@extends('layouts.admin')

@section('page-title', 'تنظیمات جدول حقوق قرارداد')

@section('main')
<div class="p-6 space-y-4 max-w-3xl" dir="rtl">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-white">🧾 تنظیمات جدول حقوق قرارداد (نسخه ۲)</h1>
            <p class="text-sm text-gray-500 mt-1">
                این سه عدد، مبنای «جدول حقوق و مزایا» در قرارداد کار با مدت معین (کارمند) است.
                بقیهٔ جدول (جمع روزانه، ۳۰ و ۳۱ روزه و جمع کل) خودکار محاسبه می‌شود.
                مقدار ذخیره‌شده هم فرمِ صدور را پر می‌کند و هم در قراردادهای جدید به‌کار می‌رود.
            </p>
        </div>
        <a href="{{ route('admin.staff-contracts.index') }}"
           class="text-xs px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg whitespace-nowrap">← فهرست قراردادها</a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 text-sm">
            <ul class="list-disc pr-5 space-y-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.staff-contracts.settings.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-[11px] text-gray-500 mb-3">همهٔ مبالغ به <b>ریال</b>.</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">دستمزد روزانه (ریال)</label>
                    <input type="number" name="contract_v2_daily_wage" min="0" dir="ltr" required
                           value="{{ old('contract_v2_daily_wage', $settings['contract_v2_daily_wage'] ?? '') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">پایه سنوات روزانه (ریال)</label>
                    <input type="number" name="contract_v2_daily_seniority" min="0" dir="ltr" required
                           value="{{ old('contract_v2_daily_seniority', $settings['contract_v2_daily_seniority'] ?? '') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">مزایای ماهانه مشمول (ریال)</label>
                    <input type="number" name="contract_v2_monthly_benefits" min="0" dir="ltr" required
                           value="{{ old('contract_v2_monthly_benefits', $settings['contract_v2_monthly_benefits'] ?? '') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm">
                </div>
            </div>
            <div class="mt-4">
                <button class="px-5 py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">ذخیرهٔ تنظیمات</button>
            </div>
        </div>

        {{-- پیش‌نمایشِ جدولِ محاسبه‌شده با مقادیرِ فعلیِ ذخیره‌شده --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <h2 class="font-bold text-sm text-gray-800 dark:text-gray-100 mb-3">پیش‌نمایش جدول (بر اساس مقادیر ذخیره‌شدهٔ فعلی)</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-gray-500">
                        <tr>
                            <th class="px-3 py-2 text-right font-medium w-10">ردیف</th>
                            <th class="px-3 py-2 text-right font-medium">شرح</th>
                            <th class="px-3 py-2 text-left font-medium whitespace-nowrap">مبلغ (ریال)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($preview as $row)
                            <tr class="{{ $row['bold'] ? 'font-bold bg-gray-50/60 dark:bg-gray-700/30' : '' }}">
                                <td class="px-3 py-2 text-gray-400">{{ $row['no'] }}</td>
                                <td class="px-3 py-2 text-gray-800 dark:text-gray-100">{{ $row['label'] }}</td>
                                <td class="px-3 py-2 text-left tabular-nums" dir="ltr">{{ number_format($row['amount']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-[11px] text-gray-400 mt-2">پس از ذخیره، این جدول به‌روز می‌شود. حق تأهل و حق اولاد طبق شرایط قانونیِ هر کارمند جداگانه اعمال می‌شود.</p>
        </div>
    </form>
</div>
@endsection
