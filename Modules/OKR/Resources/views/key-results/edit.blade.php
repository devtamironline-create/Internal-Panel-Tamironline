@extends('layouts.admin')
@section('page-title', 'ویرایش نتیجه کلیدی')
@section('main')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">ویرایش نتیجه کلیدی</h2>
            <p class="text-sm text-gray-500 mt-1">{{ $keyResult->title }}</p>
        </div>
        <form action="{{ route('okr.key-results.update', $keyResult) }}" method="POST" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">عنوان نتیجه کلیدی *</label>
                <input type="text" name="title" value="{{ old('title', $keyResult->title) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                <textarea name="description" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">{{ old('description', $keyResult->description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع سنجش *</label>
                    <select name="metric_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                        <option value="number" {{ old('metric_type', $keyResult->metric_type) === 'number' ? 'selected' : '' }}>عددی</option>
                        <option value="percentage" {{ old('metric_type', $keyResult->metric_type) === 'percentage' ? 'selected' : '' }}>درصدی</option>
                        <option value="currency" {{ old('metric_type', $keyResult->metric_type) === 'currency' ? 'selected' : '' }}>مالی</option>
                        <option value="boolean" {{ old('metric_type', $keyResult->metric_type) === 'boolean' ? 'selected' : '' }}>بله/خیر</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">واحد</label>
                    <input type="text" name="unit" value="{{ old('unit', $keyResult->unit) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">مقدار شروع *</label>
                    <input type="number" step="0.01" name="start_value" value="{{ old('start_value', $keyResult->start_value) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">مقدار هدف *</label>
                    <input type="number" step="0.01" name="target_value" value="{{ old('target_value', $keyResult->target_value) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">مسئول *</label>
                <select name="owner_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                    @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ old('owner_id', $keyResult->owner_id) == $user->id ? 'selected' : '' }}>{{ $user->full_name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                <button type="submit" class="px-6 py-2.5 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition">بروزرسانی</button>
                <a href="{{ route('okr.objectives.show', $keyResult->objective_id) }}" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">انصراف</a>
            </div>
        </form>
    </div>
</div>
@endsection
