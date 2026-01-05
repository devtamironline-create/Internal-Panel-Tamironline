@extends('layouts.admin')
@section('page-title', 'دسته‌بندی محصولات')
@section('main')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">دسته‌بندی محصولات</h1>
        <p class="mt-1 text-sm text-gray-600">مدیریت دسته‌بندی‌های محصولات</p>
    </div>
    <a href="{{ route('admin.product-categories.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
        <span class="text-lg ml-1">+</span> دسته‌بندی جدید
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">ترتیب</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">نام دسته‌بندی</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Slug</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">آیکون</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تعداد محصولات</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($categories as $category)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $category->sort_order }}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            @if($category->icon)
                            <span class="text-2xl">{{ $category->icon }}</span>
                            @endif
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $category->name }}</p>
                                @if($category->description)
                                <p class="text-xs text-gray-600 mt-1">{{ Str::limit($category->description, 50) }}</p>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 font-mono">{{ $category->slug }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $category->icon ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <span class="px-2 py-1 bg-gray-100 rounded-full">{{ $category->products_count }}</span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs font-medium rounded-full
                            {{ $category->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $category->is_active ? 'فعال' : 'غیرفعال' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm space-x-2 space-x-reverse">
                        <a href="{{ route('admin.product-categories.edit', $category) }}" class="text-blue-600 hover:text-blue-800">ویرایش</a>
                        @if($category->products_count == 0)
                        <form action="{{ route('admin.product-categories.destroy', $category) }}" method="POST" class="inline" onsubmit="return confirm('آیا مطمئن هستید؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800">حذف</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                            <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                            <p class="text-lg font-medium">هیچ دسته‌بندی یافت نشد</p>
                            <a href="{{ route('admin.product-categories.create') }}" class="mt-3 text-blue-600 hover:text-blue-800">اولین دسته‌بندی را ایجاد کنید</a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($categories->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $categories->links() }}
    </div>
    @endif
</div>
@endsection
