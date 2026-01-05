@extends('layouts.admin')
@section('page-title', 'ویرایش تیم')
@section('main')
<div class="max-w-3xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">ویرایش تیم</h1>
            <p class="text-gray-600">{{ $team->name }}</p>
        </div>
        <a href="{{ route('teams.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            بازگشت
        </a>
    </div>

    <!-- Form -->
    <form action="{{ route('teams.update', $team) }}" method="POST" class="bg-white rounded-xl shadow-sm p-6 space-y-6">
        @csrf
        @method('PUT')

        <!-- Team Name -->
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">نام تیم <span class="text-red-500">*</span></label>
            <input type="text" name="name" id="name" value="{{ old('name', $team->name) }}" required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent @error('name') border-red-500 @enderror"
                placeholder="مثال: تیم فنی">
            @error('name')
            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Description -->
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">توضیحات</label>
            <textarea name="description" id="description" rows="3"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-transparent @error('description') border-red-500 @enderror"
                placeholder="توضیح کوتاه درباره تیم...">{{ old('description', $team->description) }}</textarea>
            @error('description')
            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <!-- Color & Icon -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Color -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">رنگ تیم <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-5 gap-2">
                    @foreach($colors as $colorKey => $colorName)
                    <label class="cursor-pointer">
                        <input type="radio" name="color" value="{{ $colorKey }}" class="sr-only peer" {{ old('color', $team->color) == $colorKey ? 'checked' : '' }}>
                        <div class="w-full aspect-square rounded-lg bg-{{ $colorKey }}-500 peer-checked:ring-4 peer-checked:ring-{{ $colorKey }}-300 peer-checked:ring-offset-2 transition" title="{{ $colorName }}"></div>
                    </label>
                    @endforeach
                </div>
                @error('color')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <!-- Icon -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">آیکون تیم <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-4 gap-2">
                    @foreach($icons as $iconKey => $iconName)
                    <label class="cursor-pointer">
                        <input type="radio" name="icon" value="{{ $iconKey }}" class="sr-only peer" {{ old('icon', $team->icon) == $iconKey ? 'checked' : '' }}>
                        <div class="w-full aspect-square rounded-lg bg-gray-100 flex items-center justify-center peer-checked:bg-brand-500 peer-checked:text-white text-gray-600 transition" title="{{ $iconName }}">
                            @include('task::teams.partials.icon', ['icon' => $iconKey])
                        </div>
                    </label>
                    @endforeach
                </div>
                @error('icon')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Active Status -->
        <div>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="is_active" value="1"
                    class="w-5 h-5 text-brand-500 border-gray-300 rounded focus:ring-brand-500"
                    {{ old('is_active', $team->is_active) ? 'checked' : '' }}>
                <span class="font-medium text-gray-900">تیم فعال است</span>
            </label>
            <p class="mt-1 text-xs text-gray-500 mr-8">تیم‌های غیرفعال در لیست انتخاب تسک نمایش داده نمی‌شوند</p>
        </div>

        <!-- Team Members -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">اعضای تیم</label>
            <div class="border border-gray-200 rounded-lg p-4 max-h-64 overflow-y-auto">
                @if($users->count() > 0)
                <div class="space-y-2">
                    @foreach($users as $user)
                    <label class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-lg cursor-pointer">
                        <input type="checkbox" name="members[]" value="{{ $user->id }}"
                            class="w-4 h-4 text-brand-500 border-gray-300 rounded focus:ring-brand-500"
                            {{ in_array($user->id, old('members', $teamMemberIds)) ? 'checked' : '' }}>
                        <div class="w-8 h-8 rounded-full bg-brand-500 flex items-center justify-center text-white text-sm">
                            {{ mb_substr($user->first_name ?? 'U', 0, 1) }}
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">{{ $user->full_name }}</p>
                            <p class="text-xs text-gray-500">{{ $user->email }}</p>
                        </div>
                    </label>
                    @endforeach
                </div>
                @else
                <p class="text-center text-gray-500 py-4">کاربری یافت نشد</p>
                @endif
            </div>
        </div>

        <!-- Submit -->
        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
            <!-- Delete Button -->
            <button type="button" onclick="confirmDelete()" class="px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg transition">
                <svg class="w-5 h-5 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                حذف تیم
            </button>

            <div class="flex items-center gap-3">
                <a href="{{ route('teams.index') }}" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    انصراف
                </a>
                <button type="submit" class="px-6 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition">
                    ذخیره تغییرات
                </button>
            </div>
        </div>
    </form>

    <!-- Delete Form (Hidden) -->
    <form id="deleteForm" action="{{ route('teams.destroy', $team) }}" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>
</div>

<script>
function confirmDelete() {
    if (confirm('آیا از حذف این تیم اطمینان دارید؟\nاین عمل قابل بازگشت نیست.')) {
        document.getElementById('deleteForm').submit();
    }
}
</script>
@endsection
