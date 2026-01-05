@extends('layouts.admin')
@section('page-title', 'مدیریت پرسنل')
@section('main')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">پرسنل</h2>
            <p class="text-gray-600 mt-1">مدیریت کاربران با دسترسی ادمین</p>
        </div>
        <a href="{{ route('admin.staff.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            افزودن پرسنل
        </a>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm p-4">
        <form action="{{ route('admin.staff.index') }}" method="GET" class="flex flex-col sm:flex-row gap-4">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="جستجو..." class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            <select name="role" class="px-4 py-2 border border-gray-300 rounded-lg">
                <option value="">همه نقش‌ها</option>
                @foreach($roles as $role)
                <option value="{{ $role->name }}" {{ request('role') === $role->name ? 'selected' : '' }}>{{ $role->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">فیلتر</button>
        </form>
    </div>
    
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">پرسنل</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">موبایل</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">نقش</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($staff as $member)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-medium">{{ mb_substr($member->first_name ?? 'U', 0, 1) }}</div>
                            <div>
                                <p class="font-medium text-gray-900">{{ $member->full_name }}</p>
                                @if($member->email)<p class="text-sm text-gray-500">{{ $member->email }}</p>@endif
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-gray-900" dir="ltr">{{ $member->mobile }}</td>
                    <td class="px-6 py-4">
                        @foreach($member->roles as $role)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ $role->name }}</span>
                        @endforeach
                    </td>
                    <td class="px-6 py-4">
                        @if($member->is_active)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">فعال</span>
                        @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">غیرفعال</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.staff.edit', $member) }}" class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            @if($member->id !== auth()->id())
                            <form action="{{ route('admin.staff.destroy', $member) }}" method="POST" onsubmit="return confirm('آیا مطمئن هستید؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-2 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">پرسنلی یافت نشد</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($staff->hasPages())
    <div class="flex justify-center">{{ $staff->links() }}</div>
    @endif
</div>
@endsection
