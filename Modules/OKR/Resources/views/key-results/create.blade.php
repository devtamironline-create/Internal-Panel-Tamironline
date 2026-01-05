@extends('layouts.admin')
@section('page-title', 'افزودن نتیجه کلیدی')
@section('main')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                <a href="{{ route('okr.objectives.show', $objective) }}" class="hover:text-brand-600">{{ $objective->title }}</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
            <h2 class="text-lg font-semibold text-gray-900">افزودن نتیجه کلیدی</h2>
            <p class="text-sm text-gray-500 mt-1">یک نتیجه کلیدی قابل اندازه‌گیری تعریف کنید</p>
        </div>
        <form action="{{ route('okr.key-results.store') }}" method="POST" class="p-6 space-y-6">
            @csrf
            <input type="hidden" name="objective_id" value="{{ $objective->id }}">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">عنوان نتیجه کلیدی *</label>
                <input type="text" name="title" value="{{ old('title') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 @error('title') border-red-500 @enderror" placeholder="مثال: رسیدن به NPS بالای 50" required>
                @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                <textarea name="description" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع سنجش *</label>
                    <select name="metric_type" id="metric_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                        <option value="number" {{ old('metric_type') === 'number' ? 'selected' : '' }}>عددی</option>
                        <option value="percentage" {{ old('metric_type') === 'percentage' ? 'selected' : '' }}>درصدی</option>
                        <option value="currency" {{ old('metric_type') === 'currency' ? 'selected' : '' }}>مالی</option>
                        <option value="boolean" {{ old('metric_type') === 'boolean' ? 'selected' : '' }}>بله/خیر</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">واحد</label>
                    <input type="text" name="unit" value="{{ old('unit') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" placeholder="مثال: نفر، مشتری، ...">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4" id="value-fields">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">مقدار شروع *</label>
                    <input type="number" step="0.01" name="start_value" value="{{ old('start_value', 0) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">مقدار هدف *</label>
                    <input type="number" step="0.01" name="target_value" value="{{ old('target_value', 100) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">مسئول *</label>
                <select name="owner_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                    @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ old('owner_id', auth()->id()) == $user->id ? 'selected' : '' }}>{{ $user->full_name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                <button type="submit" class="px-6 py-2.5 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition">ایجاد نتیجه کلیدی</button>
                <a href="{{ route('okr.objectives.show', $objective) }}" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">انصراف</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('metric_type').addEventListener('change', function() {
    const valueFields = document.getElementById('value-fields');
    if (this.value === 'boolean') {
        valueFields.style.display = 'none';
    } else {
        valueFields.style.display = 'grid';
    }
});
</script>
@endsection
