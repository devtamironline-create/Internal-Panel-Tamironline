@extends('layouts.panel')
@section('page-title', 'ویرایش پروفایل')

@section('main')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">ویرایش پروفایل</h1>
        <a href="{{ route('panel.profile') }}" class="text-gray-500 hover:text-gray-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </a>
    </div>

    <!-- Form -->
    <form action="{{ route('panel.profile.update') }}" method="POST" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        @csrf
        @method('PUT')

        <div class="grid md:grid-cols-2 gap-6">
            <!-- First Name -->
            <div>
                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">نام <span class="text-red-500">*</span></label>
                <input type="text" name="first_name" id="first_name" value="{{ old('first_name', $customer->first_name) }}" required
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all @error('first_name') border-red-500 @enderror">
                @error('first_name')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <!-- Last Name -->
            <div>
                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">نام خانوادگی <span class="text-red-500">*</span></label>
                <input type="text" name="last_name" id="last_name" value="{{ old('last_name', $customer->last_name) }}" required
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all @error('last_name') border-red-500 @enderror">
                @error('last_name')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <!-- Mobile (readonly) -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">شماره موبایل</label>
                <input type="text" value="{{ $customer->mobile }}" readonly disabled
                    class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 cursor-not-allowed ltr">
                <p class="mt-1 text-xs text-gray-500">شماره موبایل قابل تغییر نیست</p>
            </div>

            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">ایمیل</label>
                <input type="email" name="email" id="email" value="{{ old('email', $customer->email) }}"
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all ltr @error('email') border-red-500 @enderror">
                @error('email')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <!-- National Code -->
            <div>
                <label for="national_code" class="block text-sm font-medium text-gray-700 mb-2">کد ملی</label>
                <input type="text" name="national_code" id="national_code" value="{{ old('national_code', $customer->national_code) }}" maxlength="10"
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all ltr @error('national_code') border-red-500 @enderror">
                @error('national_code')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <!-- Birth Date -->
            <div>
                <label for="birth_date" class="block text-sm font-medium text-gray-700 mb-2">تاریخ تولد</label>
                <input type="date" name="birth_date" id="birth_date" value="{{ old('birth_date', $customer->birth_date?->format('Y-m-d')) }}"
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all @error('birth_date') border-red-500 @enderror">
                @error('birth_date')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <!-- Business Name -->
            <div>
                <label for="business_name" class="block text-sm font-medium text-gray-700 mb-2">نام شرکت/کسب‌وکار</label>
                <input type="text" name="business_name" id="business_name" value="{{ old('business_name', $customer->business_name) }}"
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all @error('business_name') border-red-500 @enderror">
                @error('business_name')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <!-- Postal Code -->
            <div>
                <label for="postal_code" class="block text-sm font-medium text-gray-700 mb-2">کد پستی</label>
                <input type="text" name="postal_code" id="postal_code" value="{{ old('postal_code', $customer->postal_code) }}" maxlength="10"
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all ltr @error('postal_code') border-red-500 @enderror">
                @error('postal_code')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Address -->
        <div class="mt-6">
            <label for="address" class="block text-sm font-medium text-gray-700 mb-2">آدرس</label>
            <textarea name="address" id="address" rows="3"
                class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all @error('address') border-red-500 @enderror">{{ old('address', $customer->address) }}</textarea>
            @error('address')
            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Submit -->
        <div class="mt-6 flex items-center gap-4">
            <button type="submit" class="px-6 py-3 bg-brand-500 text-white font-medium rounded-xl hover:bg-brand-600 transition-colors">
                ذخیره تغییرات
            </button>
            <a href="{{ route('panel.profile') }}" class="px-6 py-3 text-gray-700 hover:text-gray-900 transition-colors">
                انصراف
            </a>
        </div>
    </form>
</div>
@endsection
