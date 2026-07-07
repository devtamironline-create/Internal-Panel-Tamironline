@extends('layouts.admin')

@section('page-title', 'مشتری‌ها')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">مشتری‌ها</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">مدیریت مشتری‌های CRM</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.customers.export', ['format' => 'xlsx'] + request()->query()) }}"
               class="inline-flex items-center gap-1 px-3 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Excel
            </a>
            <a href="{{ route('crm.customers.export', ['format' => 'csv'] + request()->query()) }}"
               class="inline-flex items-center gap-1 px-3 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                CSV
            </a>
            @can('create-crm-customer')
            <a href="{{ route('crm.customers.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                افزودن مشتری
            </a>
            @endcan
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    {{-- جستجوی WP-style: شماره موبایل (دقیق) یا شماره اشتراک --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 flex items-end gap-3">
        <div class="flex-1 max-w-md">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">جستجو</label>
            <input type="text" name="q" value="{{ $search }}" placeholder="شماره موبایل (دقیق) یا شماره اشتراک"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg" dir="ltr">
            <p class="text-xs text-gray-500 mt-1">عددی از {{ \Modules\CRM\Models\Customer::SUBSCRIPTION_OFFSET }} به بالا = شماره اشتراک. در غیر این صورت = موبایل (تطابق دقیق).</p>
        </div>
        @if($trashed ?? false)<input type="hidden" name="trashed" value="1">@endif
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800">جستجو</button>
        @if($search)
        <a href="{{ route('crm.customers.index', ($trashed ?? false) ? ['trashed' => 1] : []) }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">پاک کردن</a>
        @endif
    </form>

    {{-- فیلترِ حساب‌های حذف‌شده (delete-account از اپ) — برای بازبینی و بازگردانی --}}
    <div class="flex items-center gap-2 text-sm">
        <a href="{{ route('crm.customers.index') }}"
           class="px-3 py-1.5 rounded-lg {{ ($trashed ?? false) ? 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200' : 'bg-brand-600 text-white' }}">
            فعال‌ها
        </a>
        <a href="{{ route('crm.customers.index', ['trashed' => 1]) }}"
           class="px-3 py-1.5 rounded-lg flex items-center gap-1.5 {{ ($trashed ?? false) ? 'bg-rose-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200' }}">
            حساب‌های حذف‌شده
            @if(($trashedCount ?? 0) > 0)
                <span class="text-[10px] px-1.5 py-0.5 rounded-full {{ ($trashed ?? false) ? 'bg-white/20' : 'bg-rose-100 text-rose-700' }}">{{ $trashedCount }}</span>
            @endif
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">شماره اشتراک</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">نام</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">موبایل</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">تلفن ثابت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($customers as $customer)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4 text-sm font-medium" dir="ltr">{{ $customer->subscription }}</td>
                    <td class="px-6 py-4">
                        <a href="{{ route('crm.customers.show', $customer) }}" class="font-medium text-gray-900 dark:text-gray-100 hover:text-brand-600">
                            {{ $customer->display_name }}
                        </a>
                    </td>
                    <td class="px-6 py-4 text-sm">@tel($customer->mobile)</td>
                    <td class="px-6 py-4 text-sm">@tel($customer->phone)</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('crm.customers.show', $customer) }}" class="text-gray-600 hover:text-gray-900 text-sm">جزئیات</a>
                            @if($customer->trashed())
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">حذف‌شده {{ \Morilog\Jalali\Jalalian::fromCarbon($customer->deleted_at)->format('Y/m/d') }}</span>
                                @can('delete-crm-customer')
                                <form action="{{ route('crm.customers.restore', $customer->id) }}" method="POST" class="inline" onsubmit="return confirm('حسابِ این مشتری بازگردانی شود؟');">
                                    @csrf
                                    <button type="submit" class="text-emerald-600 hover:text-emerald-800 text-sm font-medium">بازگردانی</button>
                                </form>
                                @endcan
                            @else
                                @can('edit-crm-customer')
                                <a href="{{ route('crm.customers.edit', $customer) }}" class="text-blue-600 hover:text-blue-800 text-sm">ویرایش</a>
                                @endcan
                                @can('delete-crm-customer')
                                <form action="{{ route('crm.customers.destroy', $customer) }}" method="POST" class="inline" onsubmit="return confirm('حذف این مشتری انجام شود؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm">حذف</button>
                                </form>
                                @endcan
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">مشتری‌ای یافت نشد.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $customers->links() }}</div>
</div>
@endsection
