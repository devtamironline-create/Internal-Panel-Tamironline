@extends('layouts.admin')

@section('page-title', 'تعطیلات')

@section('main')
<div class="p-4 md:p-6 max-w-6xl mx-auto space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">مدیریت تعطیلات</h1>
            <p class="text-xs text-gray-500 mt-1">روزهای تعطیل برای picker تاریخ در اپ موبایل و گزارش‌های داخلی.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('customer-app.holidays.create') }}" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">+ افزودن</a>
            <a href="{{ route('customer-app.dashboard') }}" class="text-xs px-3 py-1.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg">← هاب اپ</a>
        </div>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif

    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 flex items-end gap-3 text-sm">
        <div>
            <label class="block text-xs text-gray-700 dark:text-gray-200 mb-1">سال (میلادی)</label>
            <input type="number" name="year" value="{{ $year }}" min="2020" max="2099" class="px-3 py-2 w-28 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg" dir="ltr">
        </div>
        <div>
            <label class="block text-xs text-gray-700 dark:text-gray-200 mb-1">نوع</label>
            <select name="type" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg">
                <option value="">— همه —</option>
                @foreach($types as $key => $label)<option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <button class="px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded-lg">اعمال</button>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2 text-start">تاریخ</th>
                    <th class="px-4 py-2 text-start">عنوان</th>
                    <th class="px-4 py-2 text-start">نوع</th>
                    <th class="px-4 py-2 text-center w-24">وضعیت</th>
                    <th class="px-4 py-2 text-center w-40">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $h)
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="px-4 py-2 font-mono text-xs" dir="ltr">
                            {{ $h->date->format('Y-m-d') }}
                            <span class="text-gray-400 text-[10px] mr-2">({{ \Morilog\Jalali\Jalalian::fromCarbon($h->date)->format('Y/m/d') }})</span>
                        </td>
                        <td class="px-4 py-2">
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $h->label }}</div>
                            @if($h->description)<div class="text-[10px] text-gray-500 mt-0.5">{{ $h->description }}</div>@endif
                        </td>
                        <td class="px-4 py-2 text-xs">{{ $types[$h->type] ?? $h->type }}</td>
                        <td class="px-4 py-2 text-center">
                            <form action="{{ route('customer-app.holidays.toggle-active', $h) }}" method="POST" class="inline">
                                @csrf @method('PUT')
                                <button class="text-[11px] px-2 py-0.5 rounded-full {{ $h->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600' }}">
                                    {{ $h->is_active ? 'فعال' : 'غیرفعال' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-2 text-center space-x-2 space-x-reverse">
                            <a href="{{ route('customer-app.holidays.edit', $h) }}" class="text-blue-600 hover:underline text-xs">ویرایش</a>
                            <form action="{{ route('customer-app.holidays.destroy', $h) }}" method="POST" class="inline" onsubmit="return confirm('حذف شود؟')">
                                @csrf @method('DELETE')
                                <button class="text-rose-600 hover:underline text-xs">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">تعطیلی در این فیلتر یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $items->links() }}</div>
</div>
@endsection
