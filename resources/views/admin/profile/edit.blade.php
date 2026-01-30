@extends('layouts.admin')
@section('page-title', 'ویرایش پروفایل')
@section('main')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">ویرایش پروفایل</h1>
            <p class="text-gray-600 dark:text-gray-400">اطلاعات کاربری خود را بروزرسانی کنید</p>
        </div>
        <a href="{{ route('admin.profile') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            بازگشت
        </a>
    </div>

    <form action="{{ route('admin.profile.update') }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">نام *</label>
                <input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}" required
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-brand-500 focus:ring-brand-500">
                @error('first_name')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">نام خانوادگی *</label>
                <input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}" required
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-brand-500 focus:ring-brand-500">
                @error('last_name')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">ایمیل</label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}"
                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-brand-500 focus:ring-brand-500">
            @error('email')
            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">تاریخ تولد</label>
            <input type="text" name="birth_date" value="{{ old('birth_date', $user->birth_date) }}"
                placeholder="مثال: 1370/05/15"
                class="jalali-datepicker w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-brand-500 focus:ring-brand-500">
            @error('birth_date')
            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('admin.profile') }}" class="px-6 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                انصراف
            </a>
            <button type="submit" class="px-6 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition">
                ذخیره تغییرات
            </button>
        </div>
    </form>
</div>
@endsection
