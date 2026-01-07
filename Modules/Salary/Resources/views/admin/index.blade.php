@extends('layouts.admin')
@section('page-title', 'مدیریت حقوق')
@section('main')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">مدیریت حقوق</h1>
            <p class="text-gray-600 mt-1">لیست حقوق کارمندان</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('salary.settings.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                تنظیمات
            </a>
        </div>
    </div>

    <!-- Filter and Actions -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <form action="" method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">سال</label>
                <select name="year" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500">
                    @for($y = 1400; $y <= 1410; $y++)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ماه</label>
                <select name="month" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500">
                    @foreach(['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'] as $i => $m)
                    <option value="{{ $i + 1 }}" {{ $month == $i + 1 ? 'selected' : '' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">فیلتر</button>
            <div class="flex-1"></div>
            <form action="{{ route('salary.admin.calculate-all') }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="month" value="{{ $month }}">
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700" onclick="return confirm('آیا از محاسبه حقوق همه کارمندان اطمینان دارید؟')">
                    <svg class="w-5 h-5 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    محاسبه همه
                </button>
            </form>
            <form action="{{ route('salary.admin.approve-all') }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="month" value="{{ $month }}">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700" onclick="return confirm('آیا از تایید همه حقوق‌ها اطمینان دارید؟')">
                    <svg class="w-5 h-5 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    تایید همه
                </button>
            </form>
            <a href="{{ route('salary.admin.export', ['year' => $year, 'month' => $month]) }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                <svg class="w-5 h-5 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                خروجی CSV
            </a>
        </form>
    </div>

    <!-- Salaries Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کارمند</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کارکرد</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">حقوق بیمه‌ای</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مزایا</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">اضافه‌کاری</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کسورات</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">خالص</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($salaries as $salary)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-brand-100 flex items-center justify-center text-brand-600 font-medium text-sm">
                                {{ mb_substr($salary->user->first_name, 0, 1) }}
                            </div>
                            <span class="font-medium text-gray-900">{{ $salary->user->full_name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $salary->work_days }} روز</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ number_format($salary->fixed_insurance_salary) }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ number_format($salary->total_benefits) }}</td>
                    <td class="px-6 py-4 text-sm text-green-600">{{ number_format($salary->total_overtime) }}</td>
                    <td class="px-6 py-4 text-sm text-red-600">{{ number_format($salary->total_deductions) }}</td>
                    <td class="px-6 py-4 font-medium text-brand-600">{{ number_format($salary->total_net_salary) }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 text-xs font-medium bg-{{ $salary->status_color }}-100 text-{{ $salary->status_color }}-800 rounded-full">{{ $salary->status_label }}</span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-1">
                            <a href="{{ route('salary.admin.show', $salary) }}" class="p-2 text-gray-600 hover:text-brand-600 hover:bg-brand-50 rounded-lg" title="مشاهده">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                            <a href="{{ route('salary.admin.edit', $salary) }}" class="p-2 text-gray-600 hover:text-brand-600 hover:bg-brand-50 rounded-lg" title="ویرایش">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            @if($salary->status === 'calculated')
                            <form action="{{ route('salary.admin.approve', $salary) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="p-2 text-gray-600 hover:text-green-600 hover:bg-green-50 rounded-lg" title="تایید">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                        <p>هنوز حقوقی برای این دوره محاسبه نشده</p>
                        <p class="text-sm mt-2">از دکمه «محاسبه همه» استفاده کنید</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($salaries->hasPages())
    <div class="flex justify-center">{{ $salaries->links() }}</div>
    @endif
</div>
@endsection
