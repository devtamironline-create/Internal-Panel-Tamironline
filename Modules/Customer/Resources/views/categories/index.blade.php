@extends('layouts.admin')
@section('page-title', 'دسته‌بندی مشتریان')
@section('main')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">دسته‌بندی مشتریان</h2>
            <p class="text-gray-600 mt-1">مدیریت دسته‌بندی‌های مشتریان (VIP، Premium و ...)</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.customers.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                بازگشت به مشتریان
            </a>
            <a href="{{ route('admin.customer-categories.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                افزودن دسته‌بندی
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">نام</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">شناسه</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">رنگ</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تعداد مشتری</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($categories as $category)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-4 h-4 rounded-full" style="background-color: {{ $category->color }};"></div>
                            <span class="font-medium text-gray-900">{{ $category->name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-gray-600 font-mono text-sm">{{ $category->slug }}</td>
                    <td class="px-6 py-4">
                        <code class="px-2 py-1 bg-gray-100 rounded text-sm">{{ $category->color }}</code>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            {{ $category->customers_count }} مشتری
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        @if($category->is_active)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">فعال</span>
                        @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">غیرفعال</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.customer-categories.edit', $category) }}" class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg" title="ویرایش">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <form action="{{ route('admin.customer-categories.destroy', $category) }}" method="POST" onsubmit="return confirm('آیا مطمئن هستید؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-2 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg" title="حذف">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">دسته‌بندی یافت نشد</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
