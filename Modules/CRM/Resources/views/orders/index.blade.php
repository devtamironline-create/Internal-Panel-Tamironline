@extends('layouts.admin')

@section('page-title', 'سفارش‌های تعمیر')

@section('main')
@php use Modules\CRM\Enums\OrderStatus; @endphp
<div class="p-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">سفارش‌های تعمیر</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">لیست و مدیریت سفارش‌های خدمات تعمیرات</p>
        </div>
        @can('create-crm-order')
        <a href="{{ route('crm.orders.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            ثبت سفارش
        </a>
        @endcan
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif

    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">جستجو</label>
            <input type="text" name="q" value="{{ $search }}" placeholder="کد سفارش، موبایل، نام مشتری، عنوان مشکل..."
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">وضعیت</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— همه —</option>
                @foreach(OrderStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تکنسین</label>
            <select name="technician_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— همه —</option>
                @foreach($technicians as $t)
                <option value="{{ $t->id }}" @selected($technicianId === $t->id)>{{ trim($t->first_name . ' ' . ($t->last_name ?? '')) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">استان</label>
            <select name="province_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                <option value="">— همه —</option>
                @foreach($provinces as $p)
                <option value="{{ $p->id }}" @selected($provinceId === $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-4 flex items-center gap-2">
            <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800">اعمال فیلتر</button>
            @if($search || $status || $technicianId || $provinceId)
            <a href="{{ route('crm.orders.index') }}" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">پاک کردن</a>
            @endif
        </div>
    </form>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">کد</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">مشتری</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">دستگاه</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">تکنسین</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">استان</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">تاریخ ثبت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($orders as $order)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4">
                        <a href="{{ route('crm.orders.show', $order) }}" class="font-medium text-gray-900 dark:text-gray-100 hover:text-brand-600" dir="ltr">
                            {{ $order->order_code }}
                        </a>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <div class="text-gray-900 dark:text-gray-100">{{ $order->customer_name ?: $order->customer?->display_name }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ $order->customer_mobile }}</div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                        {{ $order->brand?->name }}{{ $order->device ? ' / ' . $order->device->name : '' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                        {{ $order->technician ? trim($order->technician->first_name . ' ' . $order->technician->last_name) : '—' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $order->province?->name ?: '—' }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $order->created_at?->format('Y-m-d') }}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('crm.orders.show', $order) }}" class="text-gray-600 hover:text-gray-900 text-sm">جزئیات</a>
                            @can('edit-crm-order')
                            <a href="{{ route('crm.orders.edit', $order) }}" class="text-blue-600 hover:text-blue-800 text-sm">ویرایش</a>
                            @endcan
                            @can('delete-crm-order')
                            <form action="{{ route('crm.orders.destroy', $order) }}" method="POST" class="inline" onsubmit="return confirm('حذف این سفارش انجام شود؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">حذف</button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">سفارشی یافت نشد.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $orders->links() }}</div>
</div>
@endsection
