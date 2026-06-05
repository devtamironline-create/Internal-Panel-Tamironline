@extends('layouts.admin')

@section('page-title', 'دلایل عدم امکان سفارش')

@section('main')
<div class="p-4 md:p-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">دلایل عدم امکان سفارش</h1>
            <p class="text-xs text-gray-500 mt-1">برای تماس‌هایی که منجر به ثبت سفارش نمی‌شوند ولی اطلاعاتشان نگه داشته می‌شود.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm mb-4">{{ session('error') }}</div>
    @endif

    {{-- فرم افزودن --}}
    <form action="{{ route('crm.lead-reasons.store') }}" method="POST" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-4">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-8">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">عنوان دلیل جدید</label>
                <input type="text" name="name" required maxlength="120" value="{{ old('name') }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">ترتیب</label>
                <input type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', 99) }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="w-full px-3 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">
                    + افزودن
                </button>
            </div>
        </div>
        @error('name')<p class="text-xs text-rose-600 mt-2">{{ $message }}</p>@enderror
    </form>

    {{-- جدول --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-2 text-start w-16">ترتیب</th>
                    <th class="px-4 py-2 text-start">عنوان</th>
                    <th class="px-4 py-2 text-start w-24">تعداد لید</th>
                    <th class="px-4 py-2 text-start w-28">وضعیت</th>
                    <th class="px-4 py-2 w-40"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($reasons as $r)
                    <tr>
                        <form action="{{ route('crm.lead-reasons.update', $r) }}" method="POST" id="upd-{{ $r->id }}">
                            @csrf @method('PUT')
                        </form>
                        <td class="px-4 py-2">
                            <input type="number" name="sort_order" form="upd-{{ $r->id }}" value="{{ $r->sort_order }}" min="0" max="9999"
                                   class="w-16 px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm">
                        </td>
                        <td class="px-4 py-2">
                            <input type="text" name="name" form="upd-{{ $r->id }}" value="{{ $r->name }}" maxlength="120"
                                   class="w-full px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded text-sm">
                        </td>
                        <td class="px-4 py-2 text-gray-600 text-xs">{{ number_format($r->leads_count) }}</td>
                        <td class="px-4 py-2">
                            <label class="inline-flex items-center gap-1 text-xs">
                                <input type="checkbox" name="is_active" value="1" form="upd-{{ $r->id }}" @checked($r->is_active)
                                       class="w-4 h-4 rounded text-brand-600">
                                <span>فعال</span>
                            </label>
                        </td>
                        <td class="px-4 py-2 text-end">
                            <button type="submit" form="upd-{{ $r->id }}"
                                    class="px-3 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-xs">ذخیره</button>
                            <form action="{{ route('crm.lead-reasons.destroy', $r) }}" method="POST" class="inline"
                                  onsubmit="return confirm('حذف «{{ $r->name }}»؟');">
                                @csrf @method('DELETE')
                                <button type="submit" class="px-3 py-1 rounded bg-rose-100 hover:bg-rose-200 text-rose-700 text-xs">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">دلیلی ثبت نشده.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
