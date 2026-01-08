@extends('layouts.admin')
@section('page-title', 'ایجاد نقش جدید')
@section('main')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">ایجاد نقش جدید</h1>
            <p class="text-gray-600">تعریف نقش و تعیین دسترسی‌ها</p>
        </div>
        <a href="{{ route('admin.roles.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            بازگشت
        </a>
    </div>

    <form action="{{ route('admin.roles.store') }}" method="POST" class="space-y-6">
        @csrf

        <!-- Role Info -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">اطلاعات نقش</h3>
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام نقش (انگلیسی)</label>
                    <input type="text" name="name" value="{{ old('name') }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
                        placeholder="مثال: accountant" required>
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">عنوان نقش (فارسی)</label>
                    <input type="text" name="label" value="{{ old('label') }}"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500"
                        placeholder="مثال: حسابدار" required>
                    @error('label')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <!-- Permissions -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">دسترسی‌های نقش</h3>
            <p class="text-sm text-gray-500 mb-4">کاربران با این نقش، دسترسی‌های انتخاب شده را خواهند داشت</p>

            <div class="space-y-6">
                @foreach($permissions as $category => $categoryPermissions)
                <div class="border rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-brand-500"></span>
                            {{ $category }}
                        </h4>
                        <label class="flex items-center gap-2 text-xs text-gray-500 cursor-pointer">
                            <input type="checkbox" class="select-all-category rounded text-brand-500" data-category="{{ $loop->index }}">
                            انتخاب همه
                        </label>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        @foreach($categoryPermissions as $permission)
                        <label class="flex items-center gap-3 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                                class="w-4 h-4 text-brand-500 border-gray-300 rounded focus:ring-brand-500 category-{{ $loop->parent->index }}"
                                {{ in_array($permission->name, old('permissions', [])) ? 'checked' : '' }}>
                            <span class="text-sm text-gray-700">{{ \App\Http\Controllers\Admin\PermissionController::getPermissionLabel($permission->name) }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end gap-4">
            <a href="{{ route('admin.roles.index') }}" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                انصراف
            </a>
            <button type="submit" class="px-6 py-3 bg-brand-500 text-white rounded-lg hover:bg-brand-600">
                ایجاد نقش
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.querySelectorAll('.select-all-category').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const category = this.dataset.category;
        document.querySelectorAll(`.category-${category}`).forEach(cb => {
            cb.checked = this.checked;
        });
    });
});
</script>
@endpush
@endsection
