@extends('layouts.admin')
@section('page-title', 'فیش حقوقی ' . $salary->period_label)
@section('main')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('salary.history') }}" class="text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">فیش حقوقی {{ $salary->period_label }}</h1>
                <p class="text-gray-600 mt-1">{{ $salary->user->full_name }}</p>
            </div>
        </div>
        <a href="{{ route('salary.pdf', $salary) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            دانلود PDF
        </a>
    </div>

    <!-- Summary Card -->
    <div class="bg-gradient-to-r from-brand-600 to-brand-700 rounded-xl p-6 text-white">
        <div class="grid md:grid-cols-4 gap-6 text-center">
            <div>
                <p class="text-brand-200 text-sm">روز کارکرد</p>
                <p class="text-2xl font-bold mt-1">{{ $salary->work_days }}</p>
            </div>
            <div>
                <p class="text-brand-200 text-sm">جمع مزایا</p>
                <p class="text-2xl font-bold mt-1">{{ number_format($salary->total_benefits) }}</p>
            </div>
            <div>
                <p class="text-brand-200 text-sm">کسورات</p>
                <p class="text-2xl font-bold mt-1">{{ number_format($salary->total_deductions) }}</p>
            </div>
            <div>
                <p class="text-brand-200 text-sm">خالص پرداختی</p>
                <p class="text-2xl font-bold mt-1">{{ number_format($salary->total_net_salary) }}</p>
            </div>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <!-- Work Info -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-900">اطلاعات کارکرد</h3>
            </div>
            <div class="p-4 space-y-3">
                <div class="flex justify-between"><span class="text-gray-600">روز کارکرد</span><span class="font-medium">{{ $salary->work_days }} روز</span></div>
                <div class="flex justify-between"><span class="text-gray-600">ساعت کار</span><span class="font-medium">{{ $salary->work_hours }}</span></div>
                <div class="flex justify-between"><span class="text-gray-600">اضافه‌کاری</span><span class="font-medium">{{ $salary->overtime_hours }}</span></div>
                <div class="flex justify-between"><span class="text-gray-600">تاخیر</span><span class="font-medium text-red-600">{{ $salary->late_minutes }} دقیقه</span></div>
                <div class="flex justify-between"><span class="text-gray-600">غیبت</span><span class="font-medium text-red-600">{{ $salary->absent_days }} روز</span></div>
            </div>
        </div>

        <!-- Base Wages -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-900">حقوق پایه</h3>
            </div>
            <div class="p-4 space-y-3">
                <div class="flex justify-between"><span class="text-gray-600">حقوق روزانه توافقی</span><span class="font-medium">{{ number_format($salary->daily_agreed_wage) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-600">حقوق روزانه بیمه‌ای</span><span class="font-medium">{{ number_format($salary->daily_insurance_wage) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-600">حقوق روزانه اعلامی</span><span class="font-medium">{{ number_format($salary->daily_declared_wage) }}</span></div>
            </div>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <!-- Benefits -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b border-gray-100 bg-green-50">
                <h3 class="font-semibold text-green-800">مزایا</h3>
            </div>
            <div class="p-4 space-y-3">
                <div class="flex justify-between"><span class="text-gray-600">حقوق ثابت بیمه‌ای</span><span class="font-medium">{{ number_format($salary->fixed_insurance_salary) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-600">بن مسکن</span><span class="font-medium">{{ number_format($salary->housing_allowance) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-600">حق خواروبار</span><span class="font-medium">{{ number_format($salary->food_allowance) }}</span></div>
                @if($salary->marriage_allowance > 0)
                <div class="flex justify-between"><span class="text-gray-600">حق تأهل</span><span class="font-medium">{{ number_format($salary->marriage_allowance) }}</span></div>
                @endif
                @if($salary->child_allowance > 0)
                <div class="flex justify-between"><span class="text-gray-600">حق اولاد</span><span class="font-medium">{{ number_format($salary->child_allowance) }}</span></div>
                @endif
                @if($salary->seniority_monthly > 0)
                <div class="flex justify-between"><span class="text-gray-600">حق سنوات</span><span class="font-medium">{{ number_format($salary->seniority_monthly) }}</span></div>
                @endif
                @if($salary->monthly_non_insurance > 0)
                <div class="flex justify-between"><span class="text-gray-600">مابه‌التفاوت توافقی</span><span class="font-medium">{{ number_format($salary->monthly_non_insurance) }}</span></div>
                @endif
                @if($salary->total_overtime > 0)
                <div class="flex justify-between"><span class="text-gray-600">اضافه‌کاری</span><span class="font-medium">{{ number_format($salary->total_overtime) }}</span></div>
                @endif
                @if($salary->bonus > 0)
                <div class="flex justify-between"><span class="text-gray-600">پاداش</span><span class="font-medium">{{ number_format($salary->bonus) }}</span></div>
                @endif
                <div class="pt-3 border-t border-gray-200 flex justify-between">
                    <span class="font-semibold text-gray-900">جمع مزایا</span>
                    <span class="font-bold text-green-600">{{ number_format($salary->total_benefits + $salary->total_overtime + $salary->monthly_non_insurance + $salary->bonus) }}</span>
                </div>
            </div>
        </div>

        <!-- Deductions -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b border-gray-100 bg-red-50">
                <h3 class="font-semibold text-red-800">کسورات</h3>
            </div>
            <div class="p-4 space-y-3">
                <div class="flex justify-between"><span class="text-gray-600">بیمه سهم کارگر (۷٪)</span><span class="font-medium text-red-600">{{ number_format($salary->employee_insurance) }}</span></div>
                @if($salary->late_penalty > 0)
                <div class="flex justify-between"><span class="text-gray-600">جریمه تاخیر</span><span class="font-medium text-red-600">{{ number_format($salary->late_penalty) }}</span></div>
                @endif
                @if($salary->used_leave > 0)
                <div class="flex justify-between"><span class="text-gray-600">مرخصی استفاده شده</span><span class="font-medium text-red-600">{{ number_format($salary->used_leave) }}</span></div>
                @endif
                @if($salary->excess_leave > 0)
                <div class="flex justify-between"><span class="text-gray-600">مرخصی مازاد</span><span class="font-medium text-red-600">{{ number_format($salary->excess_leave) }}</span></div>
                @endif
                @if($salary->advance > 0)
                <div class="flex justify-between"><span class="text-gray-600">مساعده</span><span class="font-medium text-red-600">{{ number_format($salary->advance) }}</span></div>
                @endif
                @if($salary->other_deductions > 0)
                <div class="flex justify-between"><span class="text-gray-600">سایر کسورات</span><span class="font-medium text-red-600">{{ number_format($salary->other_deductions) }}</span></div>
                @endif
                <div class="pt-3 border-t border-gray-200 flex justify-between">
                    <span class="font-semibold text-gray-900">جمع کسورات</span>
                    <span class="font-bold text-red-600">{{ number_format($salary->total_deductions) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Net Salary -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="grid md:grid-cols-3 gap-6">
            <div class="text-center p-4 bg-gray-50 rounded-lg">
                <p class="text-gray-600 text-sm">خالص بیمه‌ای</p>
                <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format($salary->net_insurance_payment) }}</p>
            </div>
            <div class="text-center p-4 bg-gray-50 rounded-lg">
                <p class="text-gray-600 text-sm">خالص توافقی</p>
                <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format($salary->net_agreed_payment) }}</p>
            </div>
            <div class="text-center p-4 bg-brand-50 rounded-lg">
                <p class="text-brand-600 text-sm">جمع خالص پرداختی</p>
                <p class="text-2xl font-bold text-brand-600 mt-1">{{ number_format($salary->total_net_salary) }}</p>
                <p class="text-xs text-gray-500 mt-1">ریال</p>
            </div>
        </div>
    </div>

    <!-- Status -->
    <div class="bg-white rounded-xl shadow-sm p-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="px-3 py-1 text-sm font-medium bg-{{ $salary->status_color }}-100 text-{{ $salary->status_color }}-800 rounded-full">{{ $salary->status_label }}</span>
            @if($salary->paid_at)
            <span class="text-sm text-gray-500">پرداخت شده در {{ \Morilog\Jalali\Jalalian::fromDateTime($salary->paid_at)->format('Y/m/d') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
