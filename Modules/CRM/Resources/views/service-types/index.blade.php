@extends('layouts.admin')

@section('page-title', 'انواع خدمات')

@section('main')
<div class="p-4 md:p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">انواع خدمات</h1>
            <p class="text-xs text-gray-500 mt-1">نوع خدماتی که قابل ارائه به مشتری است. به‌عنوان picker در اپ موبایل نمایش داده می‌شود.</p>
        </div>
        <a href="{{ route('crm.service-types.create') }}" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">+ افزودن</a>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm mb-4">{{ session('error') }}</div>@endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2 text-start w-16">ترتیب</th>
                    <th class="px-4 py-2 text-start">عنوان</th>
                    <th class="px-4 py-2 text-start">slug</th>
                    <th class="px-4 py-2 text-center w-24">وضعیت</th>
                    <th class="px-4 py-2 text-center w-40">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $it)
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="px-4 py-2 text-gray-500">{{ $it->sort_order }}</td>
                        <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-100">{{ $it->name }}</td>
                        <td class="px-4 py-2 text-gray-500 font-mono text-xs" dir="ltr">{{ $it->slug }}</td>
                        <td class="px-4 py-2 text-center">
                            <form action="{{ route('crm.service-types.toggle-active', $it) }}" method="POST" class="inline">
                                @csrf @method('PUT')
                                <button class="text-[11px] px-2 py-0.5 rounded-full {{ $it->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600' }}">
                                    {{ $it->is_active ? 'فعال' : 'غیرفعال' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-2 text-center space-x-2 space-x-reverse">
                            <a href="{{ route('crm.service-types.edit', $it) }}" class="text-blue-600 hover:underline text-xs">ویرایش</a>
                            <form action="{{ route('crm.service-types.destroy', $it) }}" method="POST" class="inline" onsubmit="return confirm('حذف شود؟')">
                                @csrf @method('DELETE')
                                <button class="text-rose-600 hover:underline text-xs">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">موردی یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $items->links() }}</div>
</div>
@endsection
