@extends('layouts.admin')
@section('page-title', 'مدیریت دسترسی‌ها')
@section('main')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">مدیریت دسترسی‌ها</h1>
            <p class="text-gray-600 dark:text-gray-400">تعیین سطح دسترسی برای هر پرسنل</p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">نام</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">نقش</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">دسترسی‌ها</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($staff as $user)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-brand-500 flex items-center justify-center text-white">
                                {{ mb_substr($user->first_name ?? 'U', 0, 1) }}
                            </div>
                            <div>
                                <div class="font-medium text-gray-900 dark:text-white">{{ $user->full_name }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $user->mobile }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @foreach($user->roles as $role)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            {{ $role->name === 'admin' ? 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-400' :
                               ($role->name === 'manager' ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-800 dark:text-purple-400' :
                               ($role->name === 'supervisor' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-400' : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300')) }}">
                            {{ \App\Http\Controllers\Admin\PermissionController::getRoleLabel($role->name) }}
                        </span>
                        @endforeach
                        @if($user->roles->isEmpty())
                        <span class="text-gray-400 dark:text-gray-500 text-sm">بدون نقش</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex flex-wrap gap-1 max-w-md">
                            @foreach($user->getAllPermissions()->take(5) as $permission)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">
                                {{ \App\Http\Controllers\Admin\PermissionController::getPermissionLabel($permission->name) }}
                            </span>
                            @endforeach
                            @if($user->getAllPermissions()->count() > 5)
                            <span class="text-xs text-gray-400 dark:text-gray-500">+{{ $user->getAllPermissions()->count() - 5 }} مورد دیگر</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <a href="{{ route('admin.permissions.edit', $user) }}"
                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-brand-50 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 rounded-lg hover:bg-brand-100 dark:hover:bg-brand-900/50 text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            ویرایش
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
