@extends('layouts.admin')
@section('page-title', 'گزارش عملکرد')
@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">گزارش عملکرد کارکنان</h1>
            <p class="text-gray-600">آمار تسک‌های هر کاربر</p>
        </div>
        <a href="{{ route('tasks.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            بازگشت
        </a>
    </div>

    <!-- Reports Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کاربر</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">کل تسک</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">در حال انجام</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">تکمیل شده</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">تکمیل این ماه</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">تاخیر دار</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">نرخ تکمیل</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($reports as $report)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-brand-500 flex items-center justify-center text-white">
                                {{ mb_substr($report['user']->first_name ?? 'U', 0, 1) }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">{{ $report['user']->full_name }}</p>
                                <p class="text-sm text-gray-500">{{ $report['user']->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center text-sm text-gray-900">{{ $report['stats']['total'] }}</td>
                    <td class="px-6 py-4 text-center text-sm text-yellow-600">{{ $report['stats']['in_progress'] }}</td>
                    <td class="px-6 py-4 text-center text-sm text-green-600">{{ $report['stats']['completed'] }}</td>
                    <td class="px-6 py-4 text-center text-sm text-blue-600">{{ $report['completed_this_month'] }}</td>
                    <td class="px-6 py-4 text-center text-sm {{ $report['stats']['overdue'] > 0 ? 'text-red-600' : 'text-gray-500' }}">
                        {{ $report['stats']['overdue'] }}
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <div class="w-16 bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" style="width: {{ $report['completion_rate'] }}%"></div>
                            </div>
                            <span class="text-sm text-gray-600">{{ $report['completion_rate'] }}%</span>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
