@extends('layouts.admin')
@section('page-title', 'افزودن مشتری')
@section('main')
<div class="max-w-3xl">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">افزودن مشتری جدید</h2>
        </div>
        <form action="{{ route('admin.customers.store') }}" method="POST" class="p-6 space-y-6">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام *</label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('first_name') border-red-500 @enderror" required>
                    @error('first_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام خانوادگی *</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('last_name') border-red-500 @enderror" required>
                    @error('last_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">موبایل *</label>
                    <input type="tel" name="mobile" value="{{ old('mobile') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('mobile') border-red-500 @enderror" dir="ltr" placeholder="09123456789" required>
                    @error('mobile')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">کد ملی</label>
                    <input type="text" name="national_code" value="{{ old('national_code') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('national_code') border-red-500 @enderror" dir="ltr" placeholder="1234567890" maxlength="10">
                    @error('national_code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام کسب و کار</label>
                    <input type="text" name="business_name" value="{{ old('business_name') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    @error('business_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تاریخ تولد (شمسی)</label>
                    <input type="text" name="birth_date" value="{{ old('birth_date') }}" class="jalali-datepicker w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="مثال: 1370/01/01">
                    <p class="mt-1 text-xs text-gray-500">فرمت: سال/ماه/روز (مثلاً 1370/01/01)</p>
                    @error('birth_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">دسته‌بندی</label>
                <select name="customer_category_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">انتخاب کنید...</option>
                    @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ old('customer_category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('customer_category_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">یادداشت‌های داخلی</label>
                <textarea name="notes" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="توضیحات، یادداشت‌ها...">{{ old('notes') }}</textarea>
                @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3">
                <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <label for="is_active" class="text-gray-700">حساب فعال باشد</label>
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">ذخیره</button>
                <a href="{{ route('admin.customers.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">انصراف</a>
            </div>
        </form>
    </div>
</div>
@endsection
