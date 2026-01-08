@extends('layouts.admin')
@section('page-title', 'افزودن پرسنل')
@section('main')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Basic Info Card -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">افزودن پرسنل جدید</h2>
            <p class="text-sm text-gray-500 mt-1">اطلاعات اصلی کاربر</p>
        </div>
        <form action="{{ route('admin.staff.store') }}" method="POST" class="p-6 space-y-6">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام *</label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 @error('first_name') border-red-500 @enderror" required>
                    @error('first_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام خانوادگی *</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 @error('last_name') border-red-500 @enderror" required>
                    @error('last_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">موبایل *</label>
                    <input type="tel" name="mobile" value="{{ old('mobile') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 @error('mobile') border-red-500 @enderror" dir="ltr" placeholder="09123456789" required>
                    @error('mobile')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ایمیل</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" dir="ltr">
                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">رمز عبور *</label>
                    <input type="password" name="password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                    @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تکرار رمز عبور</label>
                    <input type="password" name="password_confirmation" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                </div>
            </div>

            <div class="flex items-center gap-3">
                <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500">
                <label for="is_active" class="text-gray-700">حساب فعال باشد</label>
            </div>

            @if(isset($roles))
            <!-- Role Section -->
            <div class="pt-6 border-t border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">سطح دسترسی</h3>
                <p class="text-sm text-gray-500 mb-4">نقش کاربر در سیستم</p>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach($roles as $role)
                    @php
                        $roleLabels = [
                            'admin' => 'مدیر سیستم',
                            'manager' => 'مدیر',
                            'supervisor' => 'سرپرست',
                            'staff' => 'کارمند',
                        ];
                    @endphp
                    <label class="relative cursor-pointer">
                        <input type="radio" name="role" value="{{ $role->name }}"
                            class="peer sr-only"
                            {{ old('role', 'staff') === $role->name ? 'checked' : '' }}>
                        <div class="p-4 border-2 rounded-xl transition peer-checked:border-brand-500 peer-checked:bg-brand-50 hover:bg-gray-50">
                            <div class="font-medium text-gray-900">{{ $roleLabels[$role->name] ?? $role->name }}</div>
                            <div class="text-xs text-gray-500 mt-1">{{ $role->permissions->count() }} دسترسی</div>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="flex items-center gap-4 pt-6 border-t border-gray-100">
                <button type="submit" class="px-6 py-2.5 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition">
                    ذخیره
                </button>
                <a href="{{ route('admin.staff.index') }}" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">انصراف</a>
            </div>
        </form>
    </div>
</div>
@endsection
