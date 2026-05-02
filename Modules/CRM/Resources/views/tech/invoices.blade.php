@extends('layouts.admin')

@section('page-title', 'فاکتورهای من')

@section('main')
<div class="p-6 space-y-6">
    <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">فاکتورهای من</h1>
        <p class="text-gray-600 dark:text-gray-400 mt-1">فاکتورهایی که در آن‌ها به‌عنوان تکنسین ثبت شده‌اید</p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کد</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">سفارش</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مشتری</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مبلغ نهایی</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">سهم شما</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاریخ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($invoices as $invoice)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-3 font-medium" dir="ltr">{{ $invoice->invoice_code }}</td>
                    <td class="px-6 py-3 text-sm">
                        @if($invoice->order)
                        <a href="{{ route('crm.tech.orders.show', $invoice->order) }}" class="text-brand-600 hover:underline" dir="ltr">{{ $invoice->order->order_code }}</a>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-sm">{{ $invoice->customer?->display_name ?? '—' }}</td>
                    <td class="px-6 py-3 text-sm">{{ number_format($invoice->total_amount) }} ت</td>
                    <td class="px-6 py-3 text-sm text-green-600 font-bold">{{ number_format($invoice->tech_share) }} ت</td>
                    <td class="px-6 py-3"><span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $invoice->statusBadge() }}">{{ $invoice->statusLabel() }}</span></td>
                    <td class="px-6 py-3 text-xs text-gray-500" dir="ltr">@jdatetime($invoice->issued_at)</td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">فاکتوری یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $invoices->links() }}</div>
</div>
@endsection
