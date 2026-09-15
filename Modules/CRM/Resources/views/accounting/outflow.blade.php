@extends('layouts.admin')

@section('page-title', 'خروج وجه')

@section('main')
<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">خروج وجه</h1>
        <p class="text-sm text-gray-500 mt-1">این خروجِ وجه از چه نوعی است؟ یکی را انتخاب کنید.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @can('view-crm-costs')
            <a href="{{ route('crm.costs.create') }}"
               class="group block bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-700 hover:border-rose-400 p-5 transition">
                <div class="text-3xl mb-2">🧾</div>
                <div class="text-base font-bold text-gray-900 dark:text-gray-100">هزینهٔ شرکت</div>
                <p class="text-xs text-gray-500 mt-1 leading-6">هزینه‌های عملیاتی، حقوق، خرید، اجاره و… . این مبلغ در «هزینهٔ ماه» و «سود» لحاظ می‌شود.</p>
                <span class="inline-block mt-3 text-xs text-rose-600 font-medium group-hover:underline">ثبت هزینه ←</span>
            </a>
        @endcan

        @can('manage-owner-withdrawals')
            <a href="{{ route('crm.withdrawals.create') }}"
               class="group block bg-white dark:bg-gray-800 rounded-xl border-2 border-gray-200 dark:border-gray-700 hover:border-indigo-400 p-5 transition">
                <div class="text-3xl mb-2">🏦</div>
                <div class="text-base font-bold text-gray-900 dark:text-gray-100">برداشت سرمایه (مالک/شرکا)</div>
                <p class="text-xs text-gray-500 mt-1 leading-6">خروجِ وجه توسط مالک یا شریک؛ این مبلغ <b>هزینهٔ شرکت محسوب نمی‌شود</b> و سود را کاهش نمی‌دهد.</p>
                <span class="inline-block mt-3 text-xs text-indigo-600 font-medium group-hover:underline">ثبت برداشت سرمایه ←</span>
            </a>
        @endcan
    </div>

    @cannot('view-crm-costs')
        @cannot('manage-owner-withdrawals')
            <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-4 text-sm">
                برای ثبتِ خروجِ وجه دسترسیِ لازم را ندارید.
            </div>
        @endcannot
    @endcannot
</div>
@endsection
