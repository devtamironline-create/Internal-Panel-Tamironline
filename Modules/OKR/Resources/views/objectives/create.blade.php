@extends('layouts.admin')
@section('page-title', 'ایجاد هدف جدید')
@section('main')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">ایجاد هدف جدید</h2>
            <p class="text-sm text-gray-500 mt-1">یک هدف جدید برای سازمان، تیم یا فرد تعریف کنید</p>
        </div>
        <form action="{{ route('okr.objectives.store') }}" method="POST" class="p-6 space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">دوره *</label>
                <select name="cycle_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 @error('cycle_id') border-red-500 @enderror" required>
                    <option value="">انتخاب کنید...</option>
                    @foreach($cycles as $cycle)
                    <option value="{{ $cycle->id }}" {{ old('cycle_id', $selectedCycle) == $cycle->id ? 'selected' : '' }}>{{ $cycle->title }} @if($cycle->status === 'active')(فعال)@endif</option>
                    @endforeach
                </select>
                @error('cycle_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">عنوان هدف *</label>
                <input type="text" name="title" value="{{ old('title') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 @error('title') border-red-500 @enderror" placeholder="مثال: افزایش رضایت مشتریان" required>
                @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">سطح هدف *</label>
                    <select name="level" id="level" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                        <option value="organization" {{ old('level') === 'organization' ? 'selected' : '' }}>سازمانی</option>
                        <option value="team" {{ old('level', 'team') === 'team' ? 'selected' : '' }}>تیمی</option>
                        <option value="individual" {{ old('level') === 'individual' ? 'selected' : '' }}>فردی</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">مالک/مسئول *</label>
                    <select name="owner_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500" required>
                        @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ old('owner_id', auth()->id()) == $user->id ? 'selected' : '' }}>{{ $user->full_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div id="team-field">
                <label class="block text-sm font-medium text-gray-700 mb-1">تیم</label>
                <select name="team_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    <option value="">انتخاب کنید...</option>
                    @foreach($teams as $team)
                    <option value="{{ $team->id }}" {{ old('team_id') == $team->id ? 'selected' : '' }}>{{ $team->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">هدف والد (اختیاری)</label>
                <select name="parent_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    <option value="">بدون والد</option>
                    @foreach($parentObjectives as $parent)
                    <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>{{ $parent->title }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">برای ایجاد سلسله مراتب OKR می‌توانید یک هدف سازمانی را به عنوان والد انتخاب کنید</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">وضعیت *</label>
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    <option value="draft" {{ old('status') === 'draft' ? 'selected' : '' }}>پیش‌نویس</option>
                    <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>فعال</option>
                </select>
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                <button type="submit" class="px-6 py-2.5 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition">ایجاد هدف</button>
                <a href="{{ route('okr.objectives.index') }}" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">انصراف</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('level').addEventListener('change', function() {
    document.getElementById('team-field').style.display = this.value === 'organization' ? 'none' : 'block';
});
document.getElementById('level').dispatchEvent(new Event('change'));
</script>
@endsection
