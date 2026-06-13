@extends('layouts.admin')

@section('page-title', 'استان‌ها')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">استان‌ها</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">مدیریت استان‌ها</p>
        </div>
        @can('manage-crm-provinces')
        <a href="{{ route('crm.provinces.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            افزودن استان
        </a>
        @endcan
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">نام</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Slug</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">تعداد شهر</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">ترتیب</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">نمایش در اپ</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($provinces as $province)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">{{ $province->name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $province->slug }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $province->cities_count }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $province->sort_order }}</td>
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
                        <div class="flex items-center gap-2">
                            @can('manage-crm-provinces')
                            <a href="{{ route('crm.provinces.edit', $province) }}" class="text-blue-600 hover:text-blue-800 text-sm">ویرایش</a>
                            <form action="{{ route('crm.provinces.destroy', $province) }}" method="POST" class="inline" onsubmit="return confirm('حذف این استان انجام شود؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">حذف</button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">استانی ثبت نشده.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $provinces->links() }}</div>
</div>
@endsection
