@extends('layouts.admin')
@section('page-title', 'سرورها')
@section('main')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">مدیریت سرورها</h1>
        <p class="mt-1 text-sm text-gray-600">لیست سرورهای تعریف شده</p>
    </div>
    <a href="{{ route('admin.servers.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
        <span class="text-lg ml-1">+</span> سرور جدید
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm">
    <div class="p-6 border-b border-gray-100">
        <form method="GET" class="flex gap-4">
            <div class="flex-1">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="جستجو (نام، هاست، آی‌پی)..." class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900">جستجو</button>
                @if(request('search'))
                <a href="{{ route('admin.servers.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">پاک کردن</a>
                @endif
            </div>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">#</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">نام</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">هاست‌نیم</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">آی‌پی</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">نوع</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">سرویس‌ها</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($servers as $server)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-900">{{ $server->id }}</td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $server->name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 ltr">{{ $server->hostname ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 ltr">{{ $server->ip_address ?? '-' }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $server->type_label }}</td>
                    <td class="px-6 py-4 text-sm text-gray-900">{{ $server->services_count }}</td>
                    <td class="px-6 py-4">
                        @if($server->is_active)
                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">فعال</span>
                        @else
                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">غیرفعال</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.servers.edit', $server) }}" class="text-blue-600 hover:text-blue-800">ویرایش</a>
                            <form action="{{ route('admin.servers.destroy', $server) }}" method="POST" class="inline" onsubmit="return confirm('آیا مطمئن هستید؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                            <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
                            </svg>
                            <p class="text-lg font-medium">هیچ سروری یافت نشد</p>
                            <p class="mt-1 text-sm">برای شروع، اولین سرور را ایجاد کنید</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($servers->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $servers->links() }}
    </div>
    @endif
</div>

@if(session('success'))
<div class="fixed bottom-4 left-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)">
    {{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="fixed bottom-4 left-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)">
    {{ session('error') }}
</div>
@endif
@endsection
