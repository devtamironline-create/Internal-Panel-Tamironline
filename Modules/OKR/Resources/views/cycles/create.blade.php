@extends('layouts.admin')
@section('page-title', 'ایجاد دوره جدید')
@section('main')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">ایجاد دوره جدید OKR</h2>
            <p class="text-sm text-gray-500 mt-1">یک دوره زمانی جدید برای تعریف اهداف ایجاد کنید</p>
        </div>
        <form action="{{ route('okr.cycles.store') }}" method="POST" class="p-6 space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">عنوان دوره *</label>
                <input type="text" name="title" value="{{ old('title') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 @error('title') border-red-500 @enderror" placeholder="مثال: Q1 1404" required>
                @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تاریخ شروع *</label>
                    <input type="text" name="start_date" value="{{ old('start_date') }}" placeholder="مثال: 1404/01/01" class="jalali-datepicker w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 cursor-pointer bg-white @error('start_date') border-red-500 @enderror" required>
                    @error('start_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تاریخ پایان *</label>
                    <input type="text" name="end_date" value="{{ old('end_date') }}" placeholder="مثال: 1404/03/31" class="jalali-datepicker w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 cursor-pointer bg-white @error('end_date') border-red-500 @enderror" required>
                    @error('end_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">وضعیت *</label>
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    <option value="draft" {{ old('status') === 'draft' ? 'selected' : '' }}>پیش‌نویس</option>
                    <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>فعال (دوره فعال قبلی بسته می‌شود)</option>
                </select>
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                <button type="submit" class="px-6 py-2.5 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition">ایجاد دوره</button>
                <a href="{{ route('okr.cycles.index') }}" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">انصراف</a>
            </div>
        </form>
    </div>
</div>
@endsection
