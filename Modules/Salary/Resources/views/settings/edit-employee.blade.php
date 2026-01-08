@extends('layouts.admin')
@section('page-title', 'تنظیمات حقوقی ' . $user->full_name)
@section('main')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <a href="{{ route('salary.settings.employees') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                </a>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">تنظیمات حقوقی</h2>
                    <p class="text-sm text-gray-500">{{ $user->full_name }}</p>
                </div>
            </div>
        </div>
        <form action="{{ route('salary.settings.update-employee', $user) }}" method="POST" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <!-- Work Schedule -->
            <div class="space-y-4">
                <h3 class="font-medium text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    ساعت کاری (اختیاری)
                </h3>
                <p class="text-xs text-gray-500 -mt-2">در صورت خالی بودن، از تنظیمات پیش‌فرض استفاده می‌شود</p>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ساعت شروع کار</label>
                        <input type="time" name="work_start_time" value="{{ old('work_start_time', $employeeSetting->work_start_time) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ساعت پایان کار</label>
                        <input type="time" name="work_end_time" value="{{ old('work_end_time', $employeeSetting->work_end_time) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    </div>
                </div>
            </div>

            <!-- Wages -->
            <div class="space-y-4 pt-4 border-t border-gray-100">
                <h3 class="font-medium text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    حقوق پایه (روزانه)
                </h3>
                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">حقوق توافقی</label>
                        <input type="number" name="daily_agreed_wage" value="{{ old('daily_agreed_wage', $employeeSetting->daily_agreed_wage) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                        <p class="text-xs text-gray-500 mt-1">H - حقوق واقعی توافقی</p>
                        @error('daily_agreed_wage')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">حقوق بیمه‌ای</label>
                        <input type="number" name="daily_insurance_wage" value="{{ old('daily_insurance_wage', $employeeSetting->daily_insurance_wage) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                        <p class="text-xs text-gray-500 mt-1">I - اعلام به بیمه</p>
                        @error('daily_insurance_wage')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">حقوق اعلامی بیمه</label>
                        <input type="number" name="daily_declared_wage" value="{{ old('daily_declared_wage', $employeeSetting->daily_declared_wage) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                        <p class="text-xs text-gray-500 mt-1">J - دریافتی از سازمان بیمه</p>
                        @error('daily_declared_wage')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <!-- Personal Info -->
            <div class="space-y-4 pt-4 border-t border-gray-100">
                <h3 class="font-medium text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    اطلاعات شخصی
                </h3>
                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_married" value="1" {{ old('is_married', $employeeSetting->is_married) ? 'checked' : '' }} class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500">
                            <span class="text-sm font-medium text-gray-700">متأهل</span>
                        </label>
                        <p class="text-xs text-gray-500 mt-1">برای دریافت حق تأهل</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">تعداد فرزند</label>
                        <input type="number" name="children_count" value="{{ old('children_count', $employeeSetting->children_count) }}" min="0" max="10" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                        @error('children_count')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">سنوات (سال)</label>
                        <input type="number" name="seniority_years" value="{{ old('seniority_years', $employeeSetting->seniority_years) }}" min="0" max="50" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                        @error('seniority_years')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <!-- Bank Info -->
            <div class="space-y-4 pt-4 border-t border-gray-100">
                <h3 class="font-medium text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    اطلاعات بانکی
                </h3>
                <div class="grid md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نام بانک</label>
                        <input type="text" name="bank_name" value="{{ old('bank_name', $employeeSetting->bank_name) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">شماره حساب</label>
                        <input type="text" name="bank_account" value="{{ old('bank_account', $employeeSetting->bank_account) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">شماره شبا</label>
                        <input type="text" name="sheba_number" value="{{ old('sheba_number', $employeeSetting->sheba_number) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" placeholder="IR">
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                <button type="submit" class="px-6 py-2.5 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition">ذخیره</button>
                <a href="{{ route('salary.settings.employees') }}" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">انصراف</a>
            </div>
        </form>
    </div>
</div>
@endsection
