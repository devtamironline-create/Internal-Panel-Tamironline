@extends('layouts.admin')

@section('page-title', 'پاسخ‌های HappyCall')

@section('main')
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">پاسخ‌های HappyCall</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">بازخوردهای ثبت‌شده در تماس‌های پیگیری</p>
        </div>
        @can('manage-crm-happycall')
        <a href="{{ route('crm.happycall.questions.index') }}" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm">مدیریت قالب سوالات</a>
        @endcan
    </div>

    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 flex items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">مخاطب</label>
            <select name="audience" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                <option value="">— همه —</option>
                <option value="customer" @selected($audience === 'customer')>مشتری</option>
                <option value="technician" @selected($audience === 'technician')>تکنسین</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">امتیاز</label>
            <select name="rating" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                <option value="">— همه —</option>
                @for($i = 5; $i >= 1; $i--)
                <option value="{{ $i }}" @selected($rating === $i)>{{ $i }} ستاره</option>
                @endfor
            </select>
        </div>
        <button class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm">فیلتر</button>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">زمان</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">سفارش</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مخاطب</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مشتری</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">امتیاز</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">یادداشت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">ثبت‌کننده</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($responses as $r)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-3 text-xs text-gray-500 whitespace-nowrap" dir="ltr">{{ $r->called_at?->format('Y-m-d H:i') }}</td>
                    <td class="px-6 py-3 text-sm">
                        @if($r->order)
                        <a href="{{ route('crm.happycall.responses.show', $r) }}" class="text-brand-600 hover:underline" dir="ltr">{{ $r->order->order_code }}</a>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-sm">
                        <span class="px-2 py-0.5 text-xs rounded-full {{ $r->audience === 'technician' ? 'bg-indigo-100 text-indigo-800' : 'bg-blue-100 text-blue-800' }}">{{ $r->audienceLabel() }}</span>
                    </td>
                    <td class="px-6 py-3 text-sm">{{ $r->order?->customer?->display_name ?? '—' }}</td>
                    <td class="px-6 py-3 text-sm text-yellow-500">{{ $r->ratingStars() }}</td>
                    <td class="px-6 py-3 text-xs text-gray-600 max-w-xs truncate">{{ $r->note ?: '—' }}</td>
                    <td class="px-6 py-3 text-xs text-gray-500">{{ $r->caller?->name ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-6 py-12 text-center text-gray-500">پاسخی ثبت نشده.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $responses->links() }}</div>
</div>
@endsection
