@extends('layouts.admin')
@section('page-title', 'سرور جدید')
@section('main')
<div class="mb-6">
    <a href="{{ route('admin.servers.index') }}" class="text-blue-600 hover:text-blue-800 flex items-center gap-1">
        <svg class="w-5 h-5 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        بازگشت به لیست سرورها
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm">
    <div class="p-6 border-b border-gray-100">
        <h2 class="text-lg font-semibold text-gray-900">ایجاد سرور جدید</h2>
    </div>

    <form action="{{ route('admin.servers.store') }}" method="POST" class="p-6">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">نام سرور <span class="text-red-500">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('name') border-red-500 @enderror"
                    placeholder="مثال: سرور 5">
                @error('name')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="type" class="block text-sm font-medium text-gray-700 mb-2">نوع سرور <span class="text-red-500">*</span></label>
                <select name="type" id="type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="shared" {{ old('type') == 'shared' ? 'selected' : '' }}>اشتراکی</option>
                    <option value="vps" {{ old('type') == 'vps' ? 'selected' : '' }}>سرور مجازی</option>
                    <option value="dedicated" {{ old('type') == 'dedicated' ? 'selected' : '' }}>اختصاصی</option>
                    <option value="reseller" {{ old('type') == 'reseller' ? 'selected' : '' }}>نمایندگی</option>
                </select>
                @error('type')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="hostname" class="block text-sm font-medium text-gray-700 mb-2">هاست‌نیم</label>
                <input type="text" name="hostname" id="hostname" value="{{ old('hostname') }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ltr @error('hostname') border-red-500 @enderror"
                    placeholder="server5.example.com">
                @error('hostname')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="ip_address" class="block text-sm font-medium text-gray-700 mb-2">آی‌پی آدرس</label>
                <input type="text" name="ip_address" id="ip_address" value="{{ old('ip_address') }}"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent ltr @error('ip_address') border-red-500 @enderror"
                    placeholder="192.168.1.1">
                @error('ip_address')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">یادداشت</label>
                <textarea name="notes" id="notes" rows="3"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('notes') border-red-500 @enderror">{{ old('notes') }}</textarea>
                @error('notes')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <span class="text-sm text-gray-700">سرور فعال باشد</span>
                </label>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">ذخیره سرور</button>
            <a href="{{ route('admin.servers.index') }}" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">انصراف</a>
        </div>
    </form>
</div>
@endsection
