@extends('layouts.admin')

@section('page-title', 'ایرادات قابل انتخاب')

@section('main')
<div class="p-4 md:p-6 max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">ایرادات قابل انتخاب</h1>
            <p class="text-xs text-gray-500 mt-1">لیست ایرادات/مشکلات قابل انتخاب توسط مشتری در فرم ثبت سفارش اپ موبایل. هر ایراد به یک یا چند دستگاه متصل می‌شود.</p>
        </div>
        <a href="{{ route('crm.objections.create') }}" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">+ افزودن</a>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>@endif

    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 mb-4 flex items-end gap-3">
        <div class="flex-1 max-w-xs">
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">فیلتر بر اساس دستگاه</label>
            <select name="device_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                <option value="">— همه —</option>
                @foreach($devices as $d)
                    <option value="{{ $d->id }}" @selected($deviceId === $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded-lg text-sm">اعمال</button>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2 text-start w-16">ترتیب</th>
                    <th class="px-4 py-2 text-start">ایراد</th>
                    <th class="px-4 py-2 text-start">دستگاه‌ها</th>
                    <th class="px-4 py-2 text-center w-24">وضعیت</th>
                    <th class="px-4 py-2 text-center w-40">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $it)
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="px-4 py-2 text-gray-500">{{ $it->sort_order }}</td>
                        <td class="px-4 py-2">
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $it->name }}</div>
                            <div class="text-[10px] text-gray-500 font-mono" dir="ltr">{{ $it->slug }}</div>
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-600 dark:text-gray-300">
                            {{ $it->devices->pluck('name')->implode('، ') ?: '—' }}
                        </td>
                        <td class="px-4 py-2 text-center">
                            <form action="{{ route('crm.objections.toggle-active', $it) }}" method="POST" class="inline">
                                @csrf @method('PUT')
                                <button class="text-[11px] px-2 py-0.5 rounded-full {{ $it->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600' }}">
                                    {{ $it->is_active ? 'فعال' : 'غیرفعال' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-2 text-center space-x-2 space-x-reverse">
                            <a href="{{ route('crm.objections.edit', $it) }}" class="text-blue-600 hover:underline text-xs">ویرایش</a>
                            <form action="{{ route('crm.objections.destroy', $it) }}" method="POST" class="inline" onsubmit="return confirm('حذف شود؟')">
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
