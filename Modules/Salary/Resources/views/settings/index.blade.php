@extends('layouts.admin')
@section('page-title', 'تنظیمات حقوق')
@section('main')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">تنظیمات حقوق</h1>
            <p class="text-gray-600 mt-1">تنظیم مقادیر پایه محاسبه حقوق</p>
        </div>
        <a href="{{ route('salary.settings.employees') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            تنظیمات کارمندان
        </a>
    </div>

    <form action="{{ route('salary.settings.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Allowances -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-900">مزایای ماهانه (ریال)</h3>
                <p class="text-sm text-gray-500 mt-1">این مبالغ به صورت ماهانه محاسبه و بر اساس روزهای کارکرد تقسیم می‌شوند</p>
            </div>
            <div class="p-6 grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">بن مسکن</label>
                    <input type="number" name="housing_allowance" value="{{ old('housing_allowance', $settings->housing_allowance) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    @error('housing_allowance')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">حق خواروبار</label>
                    <input type="number" name="food_allowance" value="{{ old('food_allowance', $settings->food_allowance) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    @error('food_allowance')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">حق تأهل</label>
                    <input type="number" name="marriage_allowance" value="{{ old('marriage_allowance', $settings->marriage_allowance) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    @error('marriage_allowance')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">حق اولاد (به ازای هر فرزند)</label>
                    <input type="number" name="child_allowance" value="{{ old('child_allowance', $settings->child_allowance) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    @error('child_allowance')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">پایه سنوات روزانه</label>
                    <input type="number" name="seniority_daily_rate" value="{{ old('seniority_daily_rate', $settings->seniority_daily_rate) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    @error('seniority_daily_rate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <!-- Insurance Rates -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-900">نرخ‌های بیمه (%)</h3>
            </div>
            <div class="p-6 grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">سهم بیمه کارگر</label>
                    <input type="number" step="0.01" name="employee_insurance_rate" value="{{ old('employee_insurance_rate', $settings->employee_insurance_rate) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    @error('employee_insurance_rate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">سهم بیمه کارفرما</label>
                    <input type="number" step="0.01" name="employer_insurance_rate" value="{{ old('employer_insurance_rate', $settings->employer_insurance_rate) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    @error('employer_insurance_rate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <!-- Overtime Rates -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-900">ضرایب اضافه‌کاری (%)</h3>
            </div>
            <div class="p-6 grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اضافه‌کاری عادی</label>
                    <input type="number" step="0.01" name="overtime_regular_rate" value="{{ old('overtime_regular_rate', $settings->overtime_regular_rate) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    @error('overtime_regular_rate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اضافه‌کاری تعطیل</label>
                    <input type="number" step="0.01" name="overtime_holiday_rate" value="{{ old('overtime_holiday_rate', $settings->overtime_holiday_rate) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    @error('overtime_holiday_rate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <!-- Work Hours -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-900">ساعات کاری</h3>
            </div>
            <div class="p-6 grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ساعت کاری روزانه</label>
                    <input type="number" name="daily_work_hours" value="{{ old('daily_work_hours', $settings->daily_work_hours) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    @error('daily_work_hours')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">روز کاری ماهانه</label>
                    <input type="number" name="monthly_work_days" value="{{ old('monthly_work_days', $settings->monthly_work_days) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    @error('monthly_work_days')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2.5 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition">
                ذخیره تنظیمات
            </button>
        </div>
    </form>
</div>
@endsection
