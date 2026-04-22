@extends('layouts.admin')

@section('page-title', 'گزارش ارسال SMS')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">گزارش ارسال SMS</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">تاریخچه تمام پیامک‌های ارسال‌شده (خودکار یا دستی)</p>
        </div>
        <a href="{{ route('crm.sms.templates.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">بازگشت به قالب‌ها</a>
    </div>

    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 flex items-end gap-3">
        <div class="max-w-xs">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">وضعیت</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— همه —</option>
                <option value="success" @selected($filterStatus === 'success')>موفق</option>
                <option value="failed" @selected($filterStatus === 'failed')>ناموفق</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800">اعمال</button>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">زمان</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">گیرنده</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">رویداد</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">سفارش</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">متن</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($logs as $log)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-3 text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap" dir="ltr">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                    <td class="px-6 py-3 text-sm">
                        <div dir="ltr">{{ $log->recipient_mobile }}</div>
                        @if($log->recipient_role)
                        <div class="text-xs text-gray-500">{{ $log->recipient_role === 'technician' ? 'تکنسین' : 'مشتری' }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-xs text-gray-600 dark:text-gray-400" dir="ltr">{{ $log->trigger_key ?: 'manual' }}</td>
                    <td class="px-6 py-3 text-sm">
                        @if($log->order)
                        <a href="{{ route('crm.orders.show', $log->order) }}" class="text-brand-600 hover:underline" dir="ltr">{{ $log->order->order_code }}</a>
                        @else
                        —
                        @endif
                    </td>
                    <td class="px-6 py-3 text-xs text-gray-600 dark:text-gray-400 max-w-sm">
                        <div class="whitespace-pre-wrap line-clamp-2">{{ $log->body }}</div>
                        @if($log->status === 'failed' && $log->response)
                        <div class="text-xs text-red-600 mt-1">{{ $log->response }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-3">
                        @if($log->status === 'success')
                        <span class="px-2.5 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">موفق</span>
                        @else
                        <span class="px-2.5 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">ناموفق</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500">هنوز پیامکی ارسال نشده.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $logs->links() }}</div>
</div>
@endsection
