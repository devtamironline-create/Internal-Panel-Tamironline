@extends('layouts.admin')

@section('page-title', 'پرداخت‌ها')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">پرداخت‌ها</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">تراکنش‌های درگاه پرداخت آنلاین</p>
        </div>
        <a href="{{ route('crm.payments.settings') }}" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm">تنظیمات درگاه</a>
    </div>

    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 flex items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">وضعیت</label>
            <select name="status" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                <option value="">— همه —</option>
                <option value="pending" @selected($status === 'pending')>در انتظار</option>
                <option value="verified" @selected($status === 'verified')>تایید شده</option>
                <option value="failed" @selected($status === 'failed')>ناموفق</option>
                <option value="cancelled" @selected($status === 'cancelled')>لغو شده</option>
            </select>
        </div>
        <button class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm">فیلتر</button>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">زمان</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">فاکتور</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مبلغ</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">TrackID</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">شماره پیگیری</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">کارت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">پیام</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($payments as $payment)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-3 text-xs text-gray-500 whitespace-nowrap" dir="ltr">@jdatetime($payment->created_at)</td>
                    <td class="px-6 py-3 text-sm">
                        @if($payment->invoice)
                        <a href="{{ route('crm.invoices.show', $payment->invoice) }}" class="text-brand-600 hover:underline" dir="ltr">{{ $payment->invoice->invoice_code }}</a>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-sm font-medium">{{ number_format($payment->amount) }} ت</td>
                    <td class="px-6 py-3 text-xs" dir="ltr">{{ $payment->track_id ?: '—' }}</td>
                    <td class="px-6 py-3 text-xs" dir="ltr">{{ $payment->ref_number ?: '—' }}</td>
                    <td class="px-6 py-3 text-xs" dir="ltr">{{ $payment->card_number ?: '—' }}</td>
                    <td class="px-6 py-3"><span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $payment->statusBadge() }}">{{ $payment->statusLabel() }}</span></td>
                    <td class="px-6 py-3 text-xs text-gray-500 max-w-xs truncate">{{ $payment->result_message ?: '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-6 py-12 text-center text-gray-500">تراکنشی ثبت نشده.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $payments->links() }}</div>
</div>
@endsection
