@extends('layouts.admin')

@section('page-title', 'محدودهٔ سرویس‌دهی اپلیکیشن')

@section('main')
<div class="p-4 md:p-6 max-w-5xl mx-auto">
    <div class="mb-4">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">محدودهٔ سرویس‌دهی اپلیکیشن</h1>
        <p class="text-xs text-gray-500 mt-1 leading-6">
            نمایش هر استان، شهر و منطقه در اپلیکیشن موبایل مشتری فقط با تاگل
            <span class="font-medium">«نمایش در اپ»</span> کنترل می‌شود. استانی که اینجا فعال باشد در اپ ظاهر می‌شود؛
            برای مدیریت شهرها روی لینک «شهرها» و سپس برای هر شهر روی «مناطق» بروید.
        </p>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm mb-4">{{ session('error') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">استان</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">تعداد شهر</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">نمایش در اپ</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">مدیریت</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($provinces as $province)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">{{ $province->name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $province->cities_count }}</td>
                    <td class="px-6 py-4">
                        @can('manage-crm-provinces')
                        <form action="{{ route('crm.provinces.toggle-active', $province) }}" method="POST" class="inline">
                            @csrf @method('PUT')
                            @if($province->is_active)
                                <button type="submit" title="غیرفعال کردن نمایش در اپلیکیشن"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 hover:bg-emerald-200 text-emerald-700 text-xs font-medium">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> فعال
                                </button>
                            @else
                                <button type="submit" title="فعال کردن نمایش در اپلیکیشن"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-500 text-xs font-medium">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> غیرفعال
                                </button>
                            @endif
                        </form>
                        @else
                            <span class="text-xs {{ $province->is_active ? 'text-emerald-600' : 'text-gray-400' }}">{{ $province->is_active ? 'فعال' : 'غیرفعال' }}</span>
                        @endcan
                    </td>
                    <td class="px-6 py-4">
                        <a href="{{ route('crm.cities.index', ['province_id' => $province->id]) }}"
                           class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 text-sm">
                            شهرها
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">هنوز استانی ثبت نشده است.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
