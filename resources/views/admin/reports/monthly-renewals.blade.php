@extends('layouts.admin')
@section('page-title', 'گزارش تمدیدهای ماهانه')

@section('main')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">تمدیدهای ماهانه</h2>
            <p class="text-gray-600 mt-1">تعداد و مبلغ تمدید سرویس‌ها در هر ماه شمسی</p>
        </div>
        <a href="{{ route('admin.reports.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            بازگشت
        </a>
    </div>

    <!-- Year Selector -->
    <div class="bg-white rounded-xl shadow-sm p-4">
        <form action="{{ route('admin.reports.monthly-renewals') }}" method="GET" class="flex items-center gap-4">
            <label class="text-sm font-medium text-gray-700">انتخاب سال:</label>
            <select name="year" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                @for($y = $year - 2; $y <= $year + 2; $y++)
                <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">نمایش</button>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">مجموع تمدیدها در سال {{ $year }}</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($totalCount) }} سرویس</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">مجموع مبالغ در سال {{ $year }}</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($totalAmount) }} تومان</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">ماه</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تعداد تمدید</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مبلغ کل</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">نمودار</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @php $maxAmount = max(array_column($monthlyData, 'amount')) ?: 1; @endphp
                @foreach($monthlyData as $data)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <span class="font-medium text-gray-900">{{ $data['month_name'] }}</span>
                        <span class="text-gray-500 text-sm mr-1">({{ $data['month'] }})</span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $data['count'] > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                            {{ number_format($data['count']) }} سرویس
                        </span>
                    </td>
                    <td class="px-6 py-4 text-gray-900 font-medium">
                        {{ number_format($data['amount']) }} تومان
                    </td>
                    <td class="px-6 py-4 w-64">
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div class="bg-blue-600 h-3 rounded-full transition-all duration-500" style="width: {{ ($data['amount'] / $maxAmount) * 100 }}%"></div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 font-bold">
                <tr>
                    <td class="px-6 py-4 text-gray-900">مجموع</td>
                    <td class="px-6 py-4 text-gray-900">{{ number_format($totalCount) }} سرویس</td>
                    <td class="px-6 py-4 text-gray-900">{{ number_format($totalAmount) }} تومان</td>
                    <td class="px-6 py-4"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
