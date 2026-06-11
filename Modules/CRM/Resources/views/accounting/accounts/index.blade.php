@extends('layouts.admin')

@section('page-title', 'حساب‌های پرداخت')

@section('main')
<div class="max-w-4xl mx-auto space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">حساب‌های پرداخت</h1>
            <p class="text-sm text-gray-500 mt-1">هر سند هزینه دقیقاً از یک حساب پرداخت می‌شود.</p>
        </div>
        <a href="{{ route('crm.costs.index') }}" class="px-3 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">← هزینه‌ها</a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif

    {{-- ساخت حساب جدید --}}
    <form method="POST" action="{{ route('crm.costs.accounts.store') }}"
          class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">عنوان حساب *</label>
            <input type="text" name="title" maxlength="190" required placeholder="مثلاً: بانک ملت شرکت"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نوع حساب *</label>
            <select name="type" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                @foreach($types as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">توضیحات</label>
            <input type="text" name="description" maxlength="500"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <button class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 text-sm font-bold">+ افزودن حساب</button>
    </form>

    {{-- لیست حساب‌ها — ویرایش inline با اتریبیوت form (فرم‌ها بعد از جدول‌اند
         تا HTML معتبر بماند؛ input ها با form="acc-upd-N" به آن وصل می‌شوند) --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="p-3 text-start">عنوان</th>
                    <th class="p-3 text-start">نوع</th>
                    <th class="p-3 text-start">توضیحات</th>
                    <th class="p-3 text-start">هزینه‌ها</th>
                    <th class="p-3 text-start">وضعیت</th>
                    <th class="p-3 text-start w-44"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($accounts as $acc)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="p-3">
                            <input type="text" name="title" value="{{ $acc->title }}" maxlength="190" required form="acc-upd-{{ $acc->id }}"
                                   class="w-full px-2 py-1.5 border border-transparent hover:border-gray-300 focus:border-gray-300 bg-transparent dark:text-gray-100 rounded-lg text-sm font-medium">
                        </td>
                        <td class="p-3">
                            <select name="type" form="acc-upd-{{ $acc->id }}"
                                    class="px-2 py-1.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-xs">
                                @foreach($types as $key => $label)
                                    <option value="{{ $key }}" @selected($acc->type === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="p-3">
                            <input type="text" name="description" value="{{ $acc->description }}" maxlength="500" form="acc-upd-{{ $acc->id }}"
                                   class="w-full px-2 py-1.5 border border-transparent hover:border-gray-300 focus:border-gray-300 bg-transparent dark:text-gray-100 rounded-lg text-xs">
                        </td>
                        <td class="p-3 text-xs text-gray-500">{{ number_format($acc->expenses_count) }}</td>
                        <td class="p-3">
                            <label class="inline-flex items-center gap-1.5 text-xs">
                                <input type="checkbox" name="is_active" value="1" @checked($acc->is_active) form="acc-upd-{{ $acc->id }}"
                                       class="w-4 h-4 text-brand-600 border-gray-300 rounded">
                                فعال
                            </label>
                        </td>
                        <td class="p-3">
                            <div class="flex items-center gap-2">
                                <button form="acc-upd-{{ $acc->id }}"
                                        class="px-2.5 py-1.5 rounded-lg bg-blue-100 text-blue-700 hover:bg-blue-200 text-xs font-medium">ذخیره</button>
                                <button form="acc-del-{{ $acc->id }}"
                                        onclick="return confirm('حساب «{{ $acc->title }}» حذف شود؟');"
                                        class="px-2.5 py-1.5 rounded-lg bg-rose-100 text-rose-700 hover:bg-rose-200 text-xs font-medium">حذف</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-8 text-center text-sm text-gray-400">
                            هنوز حسابی تعریف نشده — اولین حساب را از فرم بالا بسازید.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- فرم‌های inline (خارج از جدول برای HTML معتبر) --}}
    @foreach($accounts as $acc)
        <form id="acc-upd-{{ $acc->id }}" method="POST" action="{{ route('crm.costs.accounts.update', $acc) }}" class="hidden">
            @csrf @method('PUT')
        </form>
        <form id="acc-del-{{ $acc->id }}" method="POST" action="{{ route('crm.costs.accounts.destroy', $acc) }}" class="hidden">
            @csrf @method('DELETE')
        </form>
    @endforeach
</div>
@endsection
