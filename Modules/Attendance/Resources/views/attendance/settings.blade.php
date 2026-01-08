@extends('layouts.admin')
@section('page-title', 'تنظیمات حضور و غیاب')
@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">تنظیمات حضور و غیاب</h1>
            <p class="text-gray-600">پیکربندی ساعات کاری و روش‌های تایید</p>
        </div>
        <a href="{{ route('attendance.admin') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            بازگشت
        </a>
    </div>

    <form action="{{ route('attendance.settings.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Work Hours -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">ساعات کاری</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ساعت شروع کار</label>
                    <input type="time" name="work_start_time" value="{{ $settings->work_start_time }}"
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ساعت پایان کار</label>
                    <input type="time" name="work_end_time" value="{{ $settings->work_end_time }}"
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تلرانس تاخیر (دقیقه)</label>
                    <input type="number" name="late_tolerance_minutes" value="{{ $settings->late_tolerance_minutes }}" min="0"
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" required>
                    <p class="text-xs text-gray-500 mt-1">تاخیر تا این مدت ثبت نمی‌شود</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">مدت نهار (دقیقه)</label>
                    <input type="number" name="lunch_duration_minutes" value="{{ $settings->lunch_duration_minutes ?? 30 }}" min="0" max="120"
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500" required>
                    <p class="text-xs text-gray-500 mt-1">مدت زمان مجاز برای نهار</p>
                </div>
            </div>
        </div>

        <!-- Working Days -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">روزهای کاری</h3>
            <div class="flex flex-wrap gap-4">
                @php
                    $days = [0 => 'شنبه', 1 => 'یکشنبه', 2 => 'دوشنبه', 3 => 'سه‌شنبه', 4 => 'چهارشنبه', 5 => 'پنجشنبه', 6 => 'جمعه'];
                    $workingDays = $settings->working_days ?? [0, 1, 2, 3, 4];
                @endphp
                @foreach($days as $num => $name)
                    <label class="flex items-center gap-2 px-4 py-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" name="working_days[]" value="{{ $num }}"
                            {{ in_array($num, $workingDays) ? 'checked' : '' }}
                            class="rounded text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-700">{{ $name }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <!-- Verification Methods -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">روش‌های تایید حضور</h3>
            <div class="flex flex-wrap gap-4 mb-6">
                @php
                    $methods = $settings->verification_methods ?? ['trust'];
                @endphp
                <label class="flex items-center gap-2 px-4 py-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="checkbox" name="verification_methods[]" value="trust"
                        {{ in_array('trust', $methods) ? 'checked' : '' }}
                        class="rounded text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-gray-700">اعتماد (بدون بررسی)</span>
                </label>
                <label class="flex items-center gap-2 px-4 py-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="checkbox" name="verification_methods[]" value="ip"
                        {{ in_array('ip', $methods) ? 'checked' : '' }}
                        class="rounded text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-gray-700">آدرس IP</span>
                </label>
                <label class="flex items-center gap-2 px-4 py-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="checkbox" name="verification_methods[]" value="gps"
                        {{ in_array('gps', $methods) ? 'checked' : '' }}
                        class="rounded text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-gray-700">موقعیت GPS</span>
                </label>
                <label class="flex items-center gap-2 px-4 py-2 border rounded-lg cursor-pointer hover:bg-gray-50">
                    <input type="checkbox" name="verification_methods[]" value="selfie"
                        {{ in_array('selfie', $methods) ? 'checked' : '' }}
                        class="rounded text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-gray-700">تصویر سلفی</span>
                </label>
            </div>

            <!-- IP Settings -->
            <div class="border-t pt-6 mb-6">
                <h4 class="text-sm font-bold text-gray-700 mb-3">آدرس‌های IP مجاز</h4>
                <textarea name="allowed_ips" rows="3" placeholder="هر IP در یک خط..."
                    class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm font-mono"
                >{{ $settings->allowed_ips ? implode("\n", $settings->allowed_ips) : '' }}</textarea>
                <p class="text-xs text-gray-500 mt-1">هر آدرس IP را در یک خط جداگانه وارد کنید</p>
            </div>

            <!-- GPS Settings -->
            <div class="border-t pt-6">
                <h4 class="text-sm font-bold text-gray-700 mb-3">موقعیت GPS مجاز</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">عرض جغرافیایی (Latitude)</label>
                        <input type="text" name="allowed_location_lat" value="{{ $settings->allowed_location_lat }}"
                            placeholder="مثال: 35.6892"
                            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">طول جغرافیایی (Longitude)</label>
                        <input type="text" name="allowed_location_lng" value="{{ $settings->allowed_location_lng }}"
                            placeholder="مثال: 51.3890"
                            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">شعاع مجاز (متر)</label>
                        <input type="number" name="allowed_location_radius" value="{{ $settings->allowed_location_radius ?? 100 }}"
                            min="10" max="1000"
                            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm">
                    </div>
                </div>
            </div>
        </div>

        <!-- Salary Settings -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">تنظیمات حقوق</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع محاسبه حقوق</label>
                    <select name="salary_type" class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="monthly" {{ $settings->salary_type == 'monthly' ? 'selected' : '' }}>ماهانه</option>
                        <option value="hourly" {{ $settings->salary_type == 'hourly' ? 'selected' : '' }}>ساعتی</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ضریب اضافه‌کاری</label>
                    <input type="number" name="overtime_rate" value="{{ $settings->overtime_rate ?? 1.40 }}"
                        step="0.01" min="1" max="3"
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">مثال: 1.40 = ۱.۴ برابر</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">کسر تاخیر (ریال/دقیقه)</label>
                    <input type="number" name="late_deduction_per_minute" value="{{ $settings->late_deduction_per_minute ?? 0 }}"
                        min="0"
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">کسر غیبت (ریال/روز)</label>
                    <input type="number" name="absence_deduction_per_day" value="{{ $settings->absence_deduction_per_day ?? 0 }}"
                        min="0"
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end gap-4">
            <a href="{{ route('attendance.admin') }}" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                انصراف
            </a>
            <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                ذخیره تنظیمات
            </button>
        </div>
    </form>
</div>
@endsection
