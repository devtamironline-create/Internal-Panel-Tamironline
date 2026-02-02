@extends('layouts.admin')
@section('page-title', 'ویرایش دسترسی')
@section('main')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">ویرایش دسترسی</h1>
            <p class="text-gray-600 dark:text-gray-400">{{ $user->full_name }}</p>
        </div>
        <a href="{{ route('admin.permissions.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
            </svg>
            بازگشت
        </a>
    </div>

    <form action="{{ route('admin.permissions.update', $user) }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Roles -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">نقش کاربر</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">انتخاب نقش، دسترسی‌های پیش‌فرض آن نقش را اعمال می‌کند</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($roles as $role)
                <label class="relative cursor-pointer">
                    <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                        class="peer sr-only"
                        {{ in_array($role->name, $userRoles) ? 'checked' : '' }}>
                    <div class="p-4 border-2 border-gray-200 dark:border-gray-700 rounded-xl transition peer-checked:border-brand-500 peer-checked:bg-brand-50 dark:peer-checked:bg-brand-900/20 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <div class="font-medium text-gray-900 dark:text-white">{{ \App\Http\Controllers\Admin\PermissionController::getRoleLabel($role->name) }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $role->permissions->count() }} دسترسی</div>
                    </div>
                </label>
                @endforeach
            </div>
        </div>

        <!-- Direct Permissions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">دسترسی‌های اضافی</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">دسترسی‌های مستقیم به کاربر (علاوه بر دسترسی‌های نقش)</p>

            <div class="space-y-6">
                @foreach($permissions as $category => $categoryPermissions)
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-brand-500"></span>
                        {{ $category }}
                    </h4>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        @foreach($categoryPermissions as $permission)
                        <label class="flex items-center gap-3 p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                                class="w-4 h-4 text-brand-500 border-gray-300 dark:border-gray-600 rounded focus:ring-brand-500"
                                {{ in_array($permission->name, $userPermissions) ? 'checked' : '' }}>
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ \App\Http\Controllers\Admin\PermissionController::getPermissionLabel($permission->name) }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Submit -->
        <div class="flex justify-end gap-4">
            <a href="{{ route('admin.permissions.index') }}" class="px-6 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600">
                انصراف
            </a>
            <button type="submit" class="px-6 py-3 bg-brand-500 text-white rounded-lg hover:bg-brand-600">
                ذخیره تغییرات
            </button>
        </div>
    </form>
</div>
@endsection
