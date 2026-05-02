@extends('layouts.admin')

@section('page-title', 'سفارش‌های تعمیر')

@section('main')
@php use Modules\CRM\Enums\OrderStatus; @endphp
<div class="p-6 space-y-4">
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

    {{-- ─── فرم فیلتر/جستجوی پیشرفته ─────────────────────────── --}}
    @php
        $hasAnyFilter = $search || $technicianId || $provinceId || $cityId || $brandId
            || $deviceId || $orderType || $introduction || $hasInvoice !== ''
            || $fromDate || $toDate || $visitFrom || $visitTo;
    @endphp
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4"
          x-data="{ open: {{ $hasAnyFilter ? 'true' : 'false' }} }">

        {{-- ردیف اصلی: جستجو + برند/استان همیشه نمایش، بقیه قابل نمایش/پنهان --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">جستجو</label>
                <input type="text" name="q" value="{{ $search }}" placeholder="کد سفارش، موبایل، نام مشتری، عنوان مشکل..."
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
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
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تکنسین</label>
                <select name="technician_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                    <option value="">— همه —</option>
                    @foreach($technicians as $t)
                        @php $tname = trim($t->firstname_tech ?: ($t->first_name . ' ' . ($t->last_name ?? ''))); @endphp
                        <option value="{{ $t->id }}" @selected($technicianId === $t->id)>{{ $tname ?: '—' }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- ردیف فیلترهای پیشرفته (collapsible) --}}
        <div x-show="open" x-cloak x-transition class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شهر</label>
                <select name="city_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg" @disabled(!$provinceId)>
                    <option value="">— {{ $provinceId ? 'همه' : 'ابتدا استان' }} —</option>
                    @foreach($cities as $c)
                        <option value="{{ $c->id }}" @selected($cityId === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">برند</label>
                <select name="brand_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                    <option value="">— همه —</option>
                    @foreach($brands as $b)
                        <option value="{{ $b->id }}" @selected($brandId === $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">دستگاه</label>
                <select name="device_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                    <option value="">— همه —</option>
                    @foreach($devices as $d)
                        <option value="{{ $d->id }}" @selected($deviceId === $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نوع سفارش</label>
                <select name="order_type" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                    <option value="">— همه —</option>
                    <option value="repair" @selected($orderType === 'repair')>تعمیر</option>
                    <option value="service" @selected($orderType === 'service')>سرویس</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">معرف</label>
                @if(count($introductionList))
                    <select name="introduction" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                        <option value="">— همه —</option>
                        @foreach($introductionList as $opt)
                            <option value="{{ $opt }}" @selected($introduction === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="text" name="introduction" value="{{ $introduction }}" placeholder="مثلاً اینستاگرام"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                @endif
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">فاکتور</label>
                <select name="has_invoice" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                    <option value="">— همه —</option>
                    <option value="1" @selected($hasInvoice === '1')>دارد</option>
                    <option value="0" @selected($hasInvoice === '0')>ندارد</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تاریخ ثبت — از</label>
                <input type="date" name="from_date" value="{{ $fromDate }}" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تاریخ ثبت — تا</label>
                <input type="date" name="to_date" value="{{ $toDate }}" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">زمان مراجعه — از</label>
                <input type="date" name="visit_from" value="{{ $visitFrom }}" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">زمان مراجعه — تا</label>
                <input type="date" name="visit_to" value="{{ $visitTo }}" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
            </div>
        </div>

        {{-- وضعیت روی تب‌ها رفته است — اینجا hidden نگه می‌داریم --}}
        <input type="hidden" name="status" value="{{ $status }}">

        <div class="flex items-center gap-2 mt-3">
            <button type="submit" class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm font-medium">
                اعمال فیلتر
            </button>
            <button type="button" @click="open = !open"
                    class="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-200 text-sm font-medium inline-flex items-center gap-1">
                <span x-show="!open">+ فیلتر پیشرفته</span>
                <span x-show="open">− بستن فیلتر پیشرفته</span>
            </button>
            @if($hasAnyFilter || $status)
                <a href="{{ route('crm.orders.index') }}" class="px-3 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm font-medium">
                    پاک کردن همه
                </a>
            @endif
        </div>
    </form>

    {{-- ─── تب‌های وضعیت با تعداد ────────────────────────────── --}}
    @php
        $tabColors = [
            ''            => 'gray',
            'new'         => 'gray',
            'coordinated' => 'blue',
            'open'        => 'indigo',
            'suspended'   => 'yellow',
            'completed'   => 'green',
            'transit'     => 'amber',
            'returned'    => 'orange',
            'cancelled'   => 'red',
            'declined'    => 'red',
        ];
        $baseQuery = request()->except('status', 'page');
    @endphp
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm px-2 overflow-x-auto">
        <div class="flex items-center gap-1 min-w-max">
            {{-- تب همه --}}
            @php $isAll = $status === ''; @endphp
            <a href="{{ route('crm.orders.index', $baseQuery) }}"
               class="relative px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors
                      {{ $isAll ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-200' }}">
                همه
                <span class="ms-1 inline-flex items-center justify-center min-w-[1.5rem] h-5 px-1.5 text-xs font-bold rounded-full
                            {{ $isAll ? 'bg-brand-100 text-brand-700' : ($statusCounts['all'] > 0 ? 'bg-gray-100 text-gray-700' : 'bg-gray-50 text-gray-400') }}">
                    {{ number_format($statusCounts['all']) }}
                </span>
            </a>
            @foreach(OrderStatus::cases() as $case)
                @php
                    $isActive = $status === $case->value;
                    $count = (int) ($statusCounts[$case->value] ?? 0);
                    $url = route('crm.orders.index', array_merge($baseQuery, ['status' => $case->value]));
                @endphp
                <a href="{{ $url }}"
                   class="relative px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors
                          {{ $isActive ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-200' }}">
                    {{ $case->label() }}
                    <span class="ms-1 inline-flex items-center justify-center min-w-[1.5rem] h-5 px-1.5 text-xs font-bold rounded-full
                                {{ $isActive ? 'bg-brand-100 text-brand-700' : ($count > 0 ? 'bg-gray-100 text-gray-700' : 'bg-gray-50 text-gray-400') }}">
                        {{ number_format($count) }}
                    </span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- ─── جدول ────────────────────────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">کد</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">مشتری</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">دستگاه</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">تکنسین</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">شهر</th>
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
                        @if($order->order_type)
                            <div class="text-xs text-gray-400 mt-0.5">{{ $order->order_type === 'service' ? 'سرویس' : 'تعمیر' }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <div class="text-gray-900 dark:text-gray-100">{{ $order->customer_name ?: $order->customer?->display_name }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ $order->customer_mobile }}</div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                        {{ $order->brand?->name }}{{ $order->device ? ' / ' . $order->device->name : '' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                        {{ $order->technician ? trim($order->technician->firstname_tech ?: ($order->technician->first_name . ' ' . $order->technician->last_name)) : '—' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                        @php
                            $loc = trim(implode(' / ', array_filter([$order->province?->name, $order->city?->name])));
                        @endphp
                        {{ $loc !== '' ? $loc : '—' }}
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400" dir="ltr">@jdate($order->created_at)</td>
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
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">سفارشی با این فیلترها یافت نشد.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $orders->links() }}</div>
</div>
@endsection
