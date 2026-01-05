@extends('layouts.admin')
@section('page-title', 'ویرایش هدف')
@section('main')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">ویرایش هدف</h2>
            <p class="text-sm text-gray-500 mt-1">{{ $objective->title }}</p>
        </div>
        <form action="{{ route('okr.objectives.update', $objective) }}" method="POST" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">دوره *</label>
                <select name="cycle_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                    @foreach($cycles as $cycle)
                    <option value="{{ $cycle->id }}" {{ old('cycle_id', $objective->cycle_id) == $cycle->id ? 'selected' : '' }}>{{ $cycle->title }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">عنوان هدف *</label>
                <input type="text" name="title" value="{{ old('title', $objective->title) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">{{ old('description', $objective->description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">سطح هدف *</label>
                    <select name="level" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                        <option value="organization" {{ old('level', $objective->level) === 'organization' ? 'selected' : '' }}>سازمانی</option>
                        <option value="team" {{ old('level', $objective->level) === 'team' ? 'selected' : '' }}>تیمی</option>
                        <option value="individual" {{ old('level', $objective->level) === 'individual' ? 'selected' : '' }}>فردی</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">مالک/مسئول *</label>
                    <select name="owner_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                        @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ old('owner_id', $objective->owner_id) == $user->id ? 'selected' : '' }}>{{ $user->full_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">تیم</label>
                <select name="team_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    <option value="">انتخاب کنید...</option>
                    @foreach($teams as $team)
                    <option value="{{ $team->id }}" {{ old('team_id', $objective->team_id) == $team->id ? 'selected' : '' }}>{{ $team->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">هدف والد</label>
                <select name="parent_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    <option value="">بدون والد</option>
                    @foreach($parentObjectives as $parent)
                    <option value="{{ $parent->id }}" {{ old('parent_id', $objective->parent_id) == $parent->id ? 'selected' : '' }}>{{ $parent->title }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">وضعیت *</label>
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    <option value="draft" {{ old('status', $objective->status) === 'draft' ? 'selected' : '' }}>پیش‌نویس</option>
                    <option value="active" {{ old('status', $objective->status) === 'active' ? 'selected' : '' }}>فعال</option>
                    <option value="completed" {{ old('status', $objective->status) === 'completed' ? 'selected' : '' }}>تکمیل شده</option>
                    <option value="cancelled" {{ old('status', $objective->status) === 'cancelled' ? 'selected' : '' }}>لغو شده</option>
                </select>
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                <button type="submit" class="px-6 py-2.5 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition">بروزرسانی</button>
                <a href="{{ route('okr.objectives.show', $objective) }}" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">انصراف</a>
            </div>
        </form>
    </div>
</div>
@endsection
