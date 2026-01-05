@extends('layouts.admin')
@section('page-title', 'افزودن دسته‌بندی')
@section('main')
<div class="max-w-2xl">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">افزودن دسته‌بندی جدید</h2>
        </div>
        <form action="{{ route('admin.customer-categories.store') }}" method="POST" class="p-6 space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">نام *</label>
                <input type="text" name="name" value="{{ old('name') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('name') border-red-500 @enderror" placeholder="VIP، Premium، ..." required>
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">شناسه (Slug)</label>
                <input type="text" name="slug" value="{{ old('slug') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" dir="ltr" placeholder="vip، premium، ... (اختیاری، خودکار ساخته می‌شود)">
                <p class="mt-1 text-xs text-gray-500">اگر خالی بگذارید، خودکار از روی نام ساخته می‌شود</p>
                @error('slug')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">رنگ</label>
                <div class="flex items-center gap-3">
                    <input type="color" name="color" value="{{ old('color', '#3B82F6') }}" class="w-20 h-10 border border-gray-300 rounded-lg cursor-pointer">
                    <input type="text" name="color_text" value="{{ old('color', '#3B82F6') }}" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" dir="ltr" placeholder="#3B82F6" readonly>
                </div>
                @error('color')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                <textarea name="description" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="توضیحات این دسته‌بندی...">{{ old('description') }}</textarea>
                @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-3">
                <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                <label for="is_active" class="text-gray-700">فعال باشد</label>
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">ذخیره</button>
                <a href="{{ route('admin.customer-categories.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">انصراف</a>
            </div>
        </form>
    </div>
</div>

<script>
// Sync color picker with text input
document.querySelector('input[type="color"]').addEventListener('input', function(e) {
    document.querySelector('input[name="color_text"]').value = e.target.value.toUpperCase();
    document.querySelector('input[name="color"]').value = e.target.value.toUpperCase();
});
</script>
@endsection
