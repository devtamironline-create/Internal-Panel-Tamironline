@extends('layouts.admin')
@section('page-title', 'سوابق حضور و غیاب')
@section('main')
<div class="space-y-6">
    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <form action="{{ route('attendance.history') }}" method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">سال</label>
                <select name="year" class="rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    @for($y = \Morilog\Jalali\Jalalian::now()->getYear(); $y >= \Morilog\Jalali\Jalalian::now()->getYear() - 5; $y--)
                        <option value="{{ $y }}" {{ $jalaliYear == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ماه</label>
                <select name="month" class="rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
                    @foreach($months as $num => $name)
                        <option value="{{ $num }}" {{ $jalaliMonth == $num ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                فیلتر
            </button>
        </form>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-4 text-center">
            <span class="block text-2xl font-bold text-green-600">{{ $stats['present_days'] }}</span>
            <span class="text-sm text-gray-600">روز حضور</span>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center">
            <span class="block text-2xl font-bold text-red-600">{{ $stats['absent_days'] }}</span>
            <span class="text-sm text-gray-600">روز غیبت</span>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center">
            <span class="block text-2xl font-bold text-blue-600">{{ $stats['leave_days'] }}</span>
            <span class="text-sm text-gray-600">روز مرخصی</span>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center">
            <span class="block text-2xl font-bold text-blue-600">{{ $stats['total_work_hours'] }}</span>
            <span class="text-sm text-gray-600">ساعت کارکرد</span>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center">
            <span class="block text-2xl font-bold text-yellow-600">{{ $stats['total_late_minutes'] }}</span>
            <span class="text-sm text-gray-600">دقیقه تاخیر</span>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center">
            <span class="block text-2xl font-bold text-purple-600">{{ $stats['total_overtime_minutes'] }}</span>
            <span class="text-sm text-gray-600">دقیقه اضافه‌کاری</span>
        </div>
    </div>

    <!-- Attendance Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900">سوابق {{ $months[$jalaliMonth] }} {{ $jalaliYear }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاریخ</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">روز</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">ورود</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">خروج</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کارکرد</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاخیر</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">زودرفت</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">اضافه‌کاری</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($attendances as $attendance)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $attendance->jalali_date }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ \Morilog\Jalali\Jalalian::fromDateTime($attendance->date)->format('l') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $attendance->check_in ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $attendance->check_out ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $attendance->work_hours }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm {{ $attendance->late_minutes > 0 ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                            {{ $attendance->late_minutes > 0 ? $attendance->late_time : '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm {{ $attendance->early_leave_minutes > 0 ? 'text-orange-600 font-medium' : 'text-gray-600' }}">
                            {{ $attendance->early_leave_minutes > 0 ? $attendance->early_leave_minutes . ' دقیقه' : '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm {{ $attendance->overtime_minutes > 0 ? 'text-green-600 font-medium' : 'text-gray-600' }}">
                            {{ $attendance->overtime_minutes > 0 ? $attendance->overtime_minutes . ' دقیقه' : '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $attendance->status_color }}-100 text-{{ $attendance->status_color }}-800">
                                {{ $attendance->status_label }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                            هیچ رکوردی یافت نشد
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
