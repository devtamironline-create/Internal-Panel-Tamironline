@extends('layouts.admin')
@section('page-title', 'گزارش‌ها')

@section('main')
<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-gray-900">گزارش‌ها</h2>
        <p class="text-gray-600 mt-1">مشاهده گزارش‌های مختلف سیستم</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Monthly Renewals Report -->
        <a href="{{ route('admin.reports.monthly-renewals') }}" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition group">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">تمدیدهای ماهانه</h3>
                    <p class="text-sm text-gray-500 mt-1">تعداد و مبلغ تمدید در هر ماه شمسی</p>
                </div>
            </div>
        </a>

        <!-- Placeholder for future reports -->
        <div class="bg-gray-50 rounded-xl border-2 border-dashed border-gray-200 p-6 flex items-center justify-center">
            <div class="text-center text-gray-400">
                <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                <p class="text-sm">گزارش‌های بیشتر به زودی...</p>
            </div>
        </div>
    </div>
</div>
@endsection
