@extends('layouts.admin')
@section('page-title', 'ویرایش دسته‌بندی')
@section('main')
<div class="max-w-3xl">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">ویرایش دسته‌بندی: {{ $productCategory->name }}</h2>
        </div>
        <form action="{{ route('admin.product-categories.update', $productCategory) }}" method="POST" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">نام دسته‌بندی *</label>
                <input type="text" name="name" value="{{ old('name', $productCategory->name) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('name') border-red-500 @enderror" placeholder="مثال: هاست اشتراکی" required>
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Slug (اختیاری)</label>
                <input type="text" name="slug" value="{{ old('slug', $productCategory->slug) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('slug') border-red-500 @enderror" placeholder="مثال: shared-hosting" dir="ltr">
                <p class="mt-1 text-xs text-gray-500">اگر خالی بگذارید، به صورت خودکار ایجاد می‌شود</p>
                @error('slug')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('description') border-red-500 @enderror" placeholder="توضیحات مختصر درباره این دسته‌بندی...">{{ old('description', $productCategory->description) }}</textarea>
                @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">آیکون (Emoji)</label>
                    <input type="text" name="icon" value="{{ old('icon', $productCategory->icon) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('icon') border-red-500 @enderror" placeholder="🖥️">
                    <p class="mt-1 text-xs text-gray-500">می‌توانید از ایموجی استفاده کنید</p>
                    @error('icon')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ترتیب نمایش</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $productCategory->sort_order) }}" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('sort_order') border-red-500 @enderror">
                    <p class="mt-1 text-xs text-gray-500">عدد کمتر، بالاتر نمایش داده می‌شود</p>
                    @error('sort_order')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $productCategory->is_active) ? 'checked' : '' }} class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <span class="text-sm font-medium text-gray-700">فعال</span>
                </label>
            </div>

            @if($productCategory->products_count > 0)
            <div class="p-4 bg-blue-50 rounded-lg">
                <p class="text-sm text-blue-800">این دسته‌بندی شامل {{ $productCategory->products_count }} محصول است</p>
            </div>
            @endif

            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">بروزرسانی دسته‌بندی</button>
                <a href="{{ route('admin.product-categories.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">انصراف</a>
            </div>
        </form>
    </div>
</div>
@endsection
