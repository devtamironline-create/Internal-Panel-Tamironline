@extends('layouts.admin')

@section('page-title', 'فاکتورها')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">فاکتورها</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">فاکتورهای صادر شده از سفارش‌های تکمیل‌شده</p>
        </div>
        <div class="flex items-center gap-2">
            @can('manage-crm-settings')
                <a href="{{ route('crm.invoices.settings') }}"
                   class="inline-flex items-center gap-1 px-3 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg text-sm">
                    تنظیمات صورتحساب
                </a>
            @endcan
            <a href="{{ route('crm.invoices.export', ['format' => 'xlsx'] + request()->query()) }}"
               class="inline-flex items-center gap-1 px-3 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Excel
            </a>
            <a href="{{ route('crm.invoices.export', ['format' => 'csv'] + request()->query()) }}"
               class="inline-flex items-center gap-1 px-3 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                CSV
            </a>
        </div>
    </div>

    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 max-w-md">
            <label class="block text-xs font-medium text-gray-500 mb-1">جستجو</label>
            <input type="text" name="q" value="{{ $search }}" placeholder="کد فاکتور / کد سفارش / موبایل مشتری"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">وضعیت</label>
            <select name="status" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                <option value="">— همه —</option>
                <option value="issued" @selected($status === 'issued')>صادر شده</option>
                <option value="paid" @selected($status === 'paid')>پرداخت شده</option>
                <option value="cancelled" @selected($status === 'cancelled')>لغو شده</option>
            </select>
        </div>
        <button class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm">فیلتر</button>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کد</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">سفارش</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مشتری</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تکنسین</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مبلغ</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">سهم تکنسین</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">زمان</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($invoices as $invoice)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-3">
                        <a href="{{ route('crm.invoices.show', $invoice) }}" class="font-medium text-brand-600 hover:underline" dir="ltr">{{ $invoice->invoice_code }}</a>
                    </td>
                    <td class="px-6 py-3 text-sm">
                        @if($invoice->order)
                        <a href="{{ route('crm.orders.show', $invoice->order) }}" class="text-gray-700 hover:underline" dir="ltr">{{ $invoice->order->order_code }}</a>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-sm">{{ $invoice->customer?->display_name ?? '—' }}</td>
                    <td class="px-6 py-3 text-sm">{{ $invoice->technician ? trim($invoice->technician->first_name . ' ' . $invoice->technician->last_name) : '—' }}</td>
                    <td class="px-6 py-3 text-sm font-medium">{{ number_format($invoice->total_amount) }} ت</td>
                    <td class="px-6 py-3 text-sm text-green-600">{{ number_format($invoice->tech_share) }} ت</td>
                    <td class="px-6 py-3"><span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $invoice->statusBadge() }}">{{ $invoice->statusLabel() }}</span></td>
                    <td class="px-6 py-3 text-xs text-gray-500" dir="ltr">@jdatetime($invoice->issued_at)</td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-6 py-12 text-center text-gray-500">فاکتوری یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $invoices->links() }}</div>
</div>
@endsection
