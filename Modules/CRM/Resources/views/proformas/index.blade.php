@extends('layouts.admin')

@section('page-title', 'پیش‌فاکتورها')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">پیش‌فاکتورها</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">برآوردِ قیمت پیش از انجام کار — قابل ارسال به مشتری</p>
        </div>
        <a href="{{ route('crm.proformas.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            پیش‌فاکتور جدید
        </a>
    </div>

    @if(session('success'))<div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>@endif

    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">جستجو</label>
            <input type="text" name="q" value="{{ $search }}" placeholder="کد پیش‌فاکتور / موبایل / نام مشتری"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">وضعیت</label>
            <select name="status" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                <option value="">همه</option>
                @foreach(['draft'=>'پیش‌نویس','sent'=>'ارسال‌شده','accepted'=>'تأیید مشتری','rejected'=>'رد مشتری','expired'=>'منقضی','converted'=>'تبدیل به فاکتور'] as $k=>$lbl)
                    <option value="{{ $k }}" @selected($status === $k)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm">فیلتر</button>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">کد</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">مشتری</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">دستگاه</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">مبلغ</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">وضعیت</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">تاریخ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($proformas as $pf)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-4 py-3 font-mono font-medium" dir="ltr">
                        <a href="{{ route('crm.proformas.show', $pf) }}" class="text-brand-600 hover:underline">{{ $pf->proforma_code }}</a>
                    </td>
                    <td class="px-4 py-3">{{ $pf->customer_name ?: '—' }}<div class="text-xs text-gray-400" dir="ltr">{{ $pf->customer_mobile }}</div></td>
                    <td class="px-4 py-3 text-xs">{{ $pf->device_name }} {{ $pf->brand_name }}</td>
                    <td class="px-4 py-3 font-medium" dir="ltr">{{ number_format($pf->total) }}</td>
                    <td class="px-4 py-3"><span class="text-[11px] px-2 py-0.5 rounded-full {{ $pf->statusBadge() }}">{{ $pf->statusLabel() }}</span></td>
                    <td class="px-4 py-3 text-xs text-gray-500" dir="ltr">{{ \Morilog\Jalali\Jalalian::fromCarbon($pf->created_at)->format('Y/m/d') }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">پیش‌فاکتوری یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $proformas->links() }}</div>
</div>
@endsection
