@extends('layouts.admin')
@section('page-title', 'تنظیمات حقوقی کارمندان')
@section('main')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">تنظیمات حقوقی کارمندان</h1>
            <p class="text-gray-600 mt-1">تنظیم حقوق پایه هر کارمند</p>
        </div>
        <a href="{{ route('salary.settings.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
            تنظیمات عمومی
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کارمند</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">ساعت کاری</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">حقوق توافقی</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">حقوق بیمه‌ای</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">متأهل</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">فرزند</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">سنوات</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($employees as $employee)
                @php $es = $employee->employeeSetting; @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-brand-100 flex items-center justify-center text-brand-600 font-medium text-sm">
                                {{ mb_substr($employee->first_name, 0, 1) }}
                            </div>
                            <span class="font-medium text-gray-900">{{ $employee->full_name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        @if($es && ($es->work_start_time || $es->work_end_time))
                            {{ $es->work_start_time ?? '-' }} - {{ $es->work_end_time ?? '-' }}
                        @else
                            <span class="text-gray-400">پیش‌فرض</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        {{ $es && $es->daily_agreed_wage ? number_format($es->daily_agreed_wage) : '-' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        {{ $es && $es->daily_insurance_wage ? number_format($es->daily_insurance_wage) : '-' }}
                    </td>
                    <td class="px-6 py-4">
                        @if($es && $es->is_married)
                        <span class="text-green-600"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></span>
                        @else
                        <span class="text-gray-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $es->children_count ?? 0 }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $es->seniority_years ?? 0 }} سال</td>
                    <td class="px-6 py-4">
                        <a href="{{ route('salary.settings.edit-employee', $employee) }}" class="p-2 text-gray-600 hover:text-brand-600 hover:bg-brand-50 rounded-lg inline-block" title="ویرایش">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">کارمندی یافت نشد</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($employees->hasPages())
    <div class="flex justify-center">{{ $employees->links() }}</div>
    @endif
</div>
@endsection
