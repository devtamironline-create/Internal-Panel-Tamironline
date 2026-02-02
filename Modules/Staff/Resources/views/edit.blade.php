@extends('layouts.admin')
@section('page-title', isset($staff) ? 'ویرایش پرسنل' : 'افزودن پرسنل')
@section('main')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Basic Info Card -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">{{ isset($staff) ? 'ویرایش پرسنل' : 'افزودن پرسنل جدید' }}</h2>
            <p class="text-sm text-gray-500 mt-1">اطلاعات اصلی کاربر</p>
        </div>
        <form action="{{ isset($staff) ? route('admin.staff.update', $staff) : route('admin.staff.store') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
            @csrf
            @if(isset($staff)) @method('PUT') @endif

            <!-- Avatar Upload Section -->
            <div x-data="{ previewUrl: '{{ isset($staff) ? $staff->avatar_url : '' }}' }" class="flex items-center gap-6 pb-6 border-b border-gray-200">
                <div class="relative group">
                    <div class="w-24 h-24 rounded-full overflow-hidden bg-gradient-to-br from-brand-400 to-brand-600 flex items-center justify-center shadow-lg ring-4 ring-white">
                        <template x-if="previewUrl">
                            <img :src="previewUrl" class="w-full h-full object-cover" alt="تصویر پروفایل">
                        </template>
                        <template x-if="!previewUrl">
                            <span class="text-2xl font-bold text-white">{{ isset($staff) ? $staff->initials : '؟' }}</span>
                        </template>
                    </div>
                    <label class="absolute inset-0 flex items-center justify-center bg-black/50 rounded-full opacity-0 group-hover:opacity-100 cursor-pointer transition-opacity">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <input type="file" name="avatar" accept="image/*" class="hidden" @change="
                            if ($event.target.files[0]) {
                                const reader = new FileReader();
                                reader.onload = (e) => previewUrl = e.target.result;
                                reader.readAsDataURL($event.target.files[0]);
                            }
                        ">
                    </label>
                </div>
                <div>
                    <h3 class="font-medium text-gray-900">تصویر پروفایل</h3>
                    <p class="text-sm text-gray-500 mt-1">برای تغییر تصویر، روی آواتار کلیک کنید</p>
                    <p class="text-xs text-gray-400 mt-1">فرمت‌های مجاز: JPG, PNG, GIF (حداکثر 2MB)</p>
                    @error('avatar')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام *</label>
                    <input type="text" name="first_name" value="{{ old('first_name', $staff->first_name ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 @error('first_name') border-red-500 @enderror" required>
                    @error('first_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام خانوادگی *</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $staff->last_name ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 @error('last_name') border-red-500 @enderror" required>
                    @error('last_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">موبایل *</label>
                    <input type="tel" name="mobile" value="{{ old('mobile', $staff->mobile ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 @error('mobile') border-red-500 @enderror" dir="ltr" placeholder="09123456789" required>
                    @error('mobile')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ایمیل</label>
                    <input type="email" name="email" value="{{ old('email', $staff->email ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" dir="ltr">
                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تاریخ تولد</label>
                    <input type="text" name="birth_date" id="birth_date" value="{{ old('birth_date', isset($staff) && $staff->birth_date ? \Morilog\Jalali\Jalalian::fromCarbon($staff->birth_date)->format('Y/m/d') : '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 cursor-pointer" dir="ltr" placeholder="انتخاب تاریخ" readonly>
                    @error('birth_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">رمز عبور {{ isset($staff) ? '' : '*' }}</label>
                    <input type="password" name="password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" {{ isset($staff) ? '' : 'required' }}>
                    @if(isset($staff))<p class="mt-1 text-xs text-gray-500">برای عدم تغییر خالی بگذارید</p>@endif
                    @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تکرار رمز عبور</label>
                    <input type="password" name="password_confirmation" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                </div>
            </div>

            <div class="flex items-center gap-3">
                <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $staff->is_active ?? true) ? 'checked' : '' }} class="w-4 h-4 text-brand-600 border-gray-300 rounded focus:ring-brand-500">
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
                            {{ old('role', $userRole ?? 'staff') === $role->name ? 'checked' : '' }}>
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
                    {{ isset($staff) ? 'بروزرسانی' : 'ذخیره' }}
                </button>
                <a href="{{ route('admin.staff.index') }}" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">انصراف</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css">
@endpush

@push('scripts')
<script src="https://unpkg.com/@majidh1/jalalidatepicker/dist/jalalidatepicker.min.js"></script>
<script>
    jalaliDatepicker.startWatch({
        minDate: "1330/01/01",
        maxDate: "today",
        selector: '#birth_date',
        persianDigits: false
    });
</script>
@endpush
