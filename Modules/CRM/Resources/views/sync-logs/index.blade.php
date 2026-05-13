@extends('layouts.admin')

@section('page-title', 'لاگ سینک WordPress')

@section('main')
@php
    $entityLabels = [
        'order' => 'سفارش',
        'technician' => 'تکنسین',
        'customer' => 'مشتری',
        'wallet_tx' => 'تراکنش کیف‌پول',
        'invoice' => 'فاکتور',
        'taxonomy' => 'تاکسونومی',
        'setting' => 'تنظیمات',
        'other' => 'سایر',
    ];
    $statusColors = [
        'success' => 'bg-green-100 text-green-700',
        'failed' => 'bg-rose-100 text-rose-700',
        'skipped' => 'bg-gray-100 text-gray-700',
    ];
    $directionColors = [
        'inbound' => 'bg-blue-100 text-blue-700',
        'outbound' => 'bg-purple-100 text-purple-700',
    ];
@endphp

<div class="p-4 md:p-6 space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">لاگ سینک WordPress</h1>
            <p class="text-xs text-gray-500 mt-1">رخدادهای ورودی (WP→Laravel) و خروجی (Laravel→WP) — برای دیباگ.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ request()->fullUrlWithoutQuery([]) }}" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-sm">به‌روزرسانی</a>
            <form method="POST" action="{{ route('crm.sync-logs.destroy') }}" onsubmit="return confirm('پاکسازی کامل لاگ‌ها؟ این عملیات قابل برگشت نیست.')" class="inline">
                @csrf @method('DELETE')
                <input type="hidden" name="confirm" value="yes">
                <button type="submit" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg text-sm">پاک کردن همه</button>
            </form>
        </div>
    </div>

    {{-- آمار --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-3 shadow-sm">
            <div class="text-xs text-gray-500">کل لاگ‌ها</div>
            <div class="text-lg font-bold mt-1">{{ number_format($counts['total']) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-3 shadow-sm">
            <div class="text-xs text-gray-500">موفق</div>
            <div class="text-lg font-bold text-green-600 mt-1">{{ number_format($counts['success']) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-3 shadow-sm">
            <div class="text-xs text-gray-500">ناموفق</div>
            <div class="text-lg font-bold text-rose-600 mt-1">{{ number_format($counts['failed']) }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-3 shadow-sm">
            <div class="text-xs text-gray-500">رد شده</div>
            <div class="text-lg font-bold text-gray-500 mt-1">{{ number_format($counts['skipped']) }}</div>
        </div>
    </div>

    {{-- فیلتر --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-lg p-3 shadow-sm grid grid-cols-2 md:grid-cols-5 gap-2 text-sm">
        <select name="direction" class="px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded">
            <option value="">جهت (همه)</option>
            <option value="inbound"  @selected(request('direction') === 'inbound')>ورودی (WP→Lar)</option>
            <option value="outbound" @selected(request('direction') === 'outbound')>خروجی (Lar→WP)</option>
        </select>
        <select name="entity_type" class="px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded">
            <option value="">موجودیت (همه)</option>
            @foreach ($entityLabels as $k => $v)
                <option value="{{ $k }}" @selected(request('entity_type') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="status" class="px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded">
            <option value="">وضعیت (همه)</option>
            <option value="success" @selected(request('status') === 'success')>موفق</option>
            <option value="failed"  @selected(request('status') === 'failed')>ناموفق</option>
            <option value="skipped" @selected(request('status') === 'skipped')>رد شده</option>
        </select>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجو endpoint / wp_id / id / خطا"
               class="px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded md:col-span-1">
        <button type="submit" class="px-3 py-1.5 bg-brand-500 hover:bg-brand-600 text-white rounded">اعمال فیلتر</button>
    </form>

    {{-- جدول --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs">
                    <tr>
                        <th class="px-3 py-2 text-right">#</th>
                        <th class="px-3 py-2 text-right">زمان</th>
                        <th class="px-3 py-2 text-right">جهت</th>
                        <th class="px-3 py-2 text-right">موجودیت</th>
                        <th class="px-3 py-2 text-right">Endpoint</th>
                        <th class="px-3 py-2 text-right">id / wp_id</th>
                        <th class="px-3 py-2 text-right">HTTP</th>
                        <th class="px-3 py-2 text-right">وضعیت</th>
                        <th class="px-3 py-2 text-right">خطا</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-3 py-2 text-gray-500">{{ $log->id }}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-xs">
                                {{ optional($log->created_at)->format('Y-m-d') }}<br>
                                <span class="text-gray-500">{{ optional($log->created_at)->format('H:i:s') }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-0.5 rounded text-xs {{ $directionColors[$log->direction] ?? '' }}">
                                    {{ $log->direction === 'inbound' ? '← WP' : '→ WP' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ $entityLabels[$log->entity_type] ?? $log->entity_type }}</td>
                            <td class="px-3 py-2 font-mono text-xs" dir="ltr">{{ $log->endpoint }}</td>
                            <td class="px-3 py-2 text-xs">
                                @if ($log->entity_id) <span title="Laravel id">#{{ $log->entity_id }}</span> @endif
                                @if ($log->wp_id) <span class="text-gray-400" title="WP id">/ wp:{{ $log->wp_id }}</span> @endif
                            </td>
                            <td class="px-3 py-2 text-xs">{{ $log->http_status ?? '—' }}</td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-0.5 rounded text-xs {{ $statusColors[$log->status] ?? '' }}">
                                    {{ ['success' => 'موفق', 'failed' => 'ناموفق', 'skipped' => 'رد'][$log->status] ?? $log->status }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-xs text-rose-600 max-w-xs truncate" title="{{ $log->error_message }}">
                                {{ $log->error_message }}
                            </td>
                            <td class="px-3 py-2 text-left">
                                <a href="{{ route('crm.sync-logs.show', $log->id) }}" class="text-brand-600 hover:underline text-xs">جزئیات</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-3 py-8 text-center text-gray-500">هیچ لاگی ثبت نشده.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="p-3 border-t border-gray-100 dark:border-gray-700">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
@endsection
