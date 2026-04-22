@extends('layouts.admin')

@section('page-title', 'برندها')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">برندها</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">مدیریت برندهای دستگاه‌ها</p>
        </div>
        @can('manage-crm-brands')
        <a href="{{ route('crm.brands.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            افزودن برند
        </a>
        @endcan
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">نام</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Slug</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">ترتیب</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($brands as $brand)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            @if($brand->logo)
                            <img src="{{ $brand->logo }}" alt="{{ $brand->name }}" class="w-8 h-8 rounded object-contain bg-gray-100">
                            @endif
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $brand->name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $brand->slug }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $brand->sort_order }}</td>
                    <td class="px-6 py-4">
                        @if($brand->is_active)
                        <span class="px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">فعال</span>
                        @else
                        <span class="px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">غیرفعال</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            @can('manage-crm-brands')
                            <a href="{{ route('crm.brands.edit', $brand) }}" class="text-blue-600 hover:text-blue-800 text-sm">ویرایش</a>
                            <form action="{{ route('crm.brands.destroy', $brand) }}" method="POST" class="inline" onsubmit="return confirm('حذف این برند انجام شود؟');">
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
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">برندی ثبت نشده.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $brands->links() }}</div>
</div>
@endsection
