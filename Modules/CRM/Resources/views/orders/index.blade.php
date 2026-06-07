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
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.orders.export', ['format' => 'xlsx'] + request()->query()) }}"
               class="inline-flex items-center gap-1 px-3 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm" title="خروجی Excel با فیلترهای فعلی">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Excel
            </a>
            <a href="{{ route('crm.orders.export', ['format' => 'csv'] + request()->query()) }}"
               class="inline-flex items-center gap-1 px-3 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm" title="خروجی CSV با فیلترهای فعلی">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                CSV
            </a>
            @can('create-crm-order')
            <a href="{{ route('crm.orders.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                ثبت سفارش
            </a>
            @endcan
        </div>
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
          x-data="{
              open: {{ $hasAnyFilter ? 'true' : 'false' }},
              provinceId: '{{ $provinceId }}',
              cityId: '{{ $cityId }}',
              filterCities() {
                  const sel = this.$refs.citySelect;
                  if (!sel) return;
                  Array.from(sel.options).forEach(opt => {
                      if (!opt.value) return; // placeholder
                      const pid = opt.dataset.provinceId;
                      opt.hidden = !this.provinceId || String(pid) !== String(this.provinceId);
                  });
                  // اگر شهر انتخاب‌شده مال استان جدید نیست، خالی کن
                  const cur = sel.querySelector('option:checked');
                  if (cur && cur.value && cur.dataset.provinceId !== String(this.provinceId)) {
                      sel.value = '';
                      this.cityId = '';
                  }
                  sel.disabled = !this.provinceId;
              }
          }"
          x-init="$nextTick(() => filterCities())">

        {{-- ردیف اصلی: جستجو + برند/استان همیشه نمایش، بقیه قابل نمایش/پنهان --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">جستجو</label>
                <input type="text" name="q" value="{{ $search }}" placeholder="کد سفارش، موبایل، نام مشتری، عنوان مشکل..."
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">استان</label>
                <select name="province_id" x-model="provinceId" @change="filterCities()"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                    <option value="">— همه —</option>
                    @foreach($provinces as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تکنسین</label>
                @php
                    $techOptions = ['' => '— همه —', 'none' => '— بدون تکنسین —'];
                    foreach ($technicians as $t) {
                        $tname = trim($t->firstname_tech ?: ($t->first_name . ' ' . ($t->last_name ?? '')));
                        $techOptions[(string) $t->id] = $tname ?: '—';
                    }
                    $techValue = $technicianId === null ? '' : (string) $technicianId;
                @endphp
                <x-searchable-select
                    name="technician_id"
                    :options="$techOptions"
                    :value="$techValue"
                    placeholder="— همه —"
                    searchPlaceholder="جستجوی تکنسین..." />
            </div>
        </div>

        {{-- ردیف فیلترهای پیشرفته (collapsible) --}}
        <div x-show="open" x-cloak x-transition class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شهر</label>
                <select name="city_id" x-ref="citySelect" x-model="cityId"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg disabled:opacity-50">
                    <option value="">— ابتدا استان —</option>
                    @foreach($cities as $c)
                        <option value="{{ $c->id }}" data-province-id="{{ $c->province_id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">برند</label>
                @php
                    $brandOptions = ['' => '— همه —'];
                    foreach ($brands as $b) { $brandOptions[(string) $b->id] = $b->name; }
                @endphp
                <x-searchable-select
                    name="brand_id"
                    :options="$brandOptions"
                    :value="$brandId"
                    placeholder="— همه —"
                    searchPlaceholder="جستجوی برند..." />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">دستگاه</label>
                @php
                    $deviceOptions = ['' => '— همه —'];
                    foreach ($devices as $d) { $deviceOptions[(string) $d->id] = $d->name; }
                @endphp
                <x-searchable-select
                    name="device_id"
                    :options="$deviceOptions"
                    :value="$deviceId"
                    placeholder="— همه —"
                    searchPlaceholder="جستجوی دستگاه..." />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نوع سفارش</label>
                <select name="order_type" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                    <option value="">— همه —</option>
                    <option value="repair" @selected($orderType === 'repair')>تعمیر</option>
                    <option value="service" @selected($orderType === 'service')>نصب</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">معرف</label>
                @if(count($introductionList))
                    @php
                        $introOptions = ['' => '— همه —'];
                        foreach ($introductionList as $opt) { $introOptions[(string) $opt] = (string) $opt; }
                    @endphp
                    <x-searchable-select
                        name="introduction"
                        :options="$introOptions"
                        :value="$introduction"
                        placeholder="— همه —"
                        searchPlaceholder="جستجو..." />
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
                <input type="text" name="from_date" value="{{ $fromDate }}" dir="ltr" placeholder="مثلاً 1404/02/01" readonly
                       class="jalali-datepicker w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg cursor-pointer bg-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تاریخ ثبت — تا</label>
                <input type="text" name="to_date" value="{{ $toDate }}" dir="ltr" placeholder="مثلاً 1404/02/12" readonly
                       class="jalali-datepicker w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg cursor-pointer bg-white">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">زمان مراجعه — از</label>
                <input type="text" name="visit_from" value="{{ $visitFrom }}" dir="ltr" placeholder="مثلاً 1404/02/01" readonly
                       class="jalali-datepicker w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg cursor-pointer bg-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">زمان مراجعه — تا</label>
                <input type="text" name="visit_to" value="{{ $visitTo }}" dir="ltr" placeholder="مثلاً 1404/02/12" readonly
                       class="jalali-datepicker w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg cursor-pointer bg-white">
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
            @php $isAll = $status === '' && $technicianId !== 'none'; @endphp
            <a href="{{ route('crm.orders.index', $baseQuery) }}"
               class="relative px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors
                      {{ $isAll ? 'border-brand-600 text-brand-600' : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-200' }}">
                همه
                <span class="ms-1 inline-flex items-center justify-center min-w-[1.5rem] h-5 px-1.5 text-xs font-bold rounded-full
                            {{ $isAll ? 'bg-brand-100 text-brand-700' : ($statusCounts['all'] > 0 ? 'bg-gray-100 text-gray-700' : 'bg-gray-50 text-gray-400') }}">
                    {{ number_format($statusCounts['all']) }}
                </span>
            </a>

            {{-- تب بدون تکنسین --}}
            @php
                $isNoTech = $technicianId === 'none';
                $noTechCount = (int) ($statusCounts['no_tech'] ?? 0);
                $noTechUrl = route('crm.orders.index', array_merge($baseQuery, ['technician_id' => 'none']));
            @endphp
            <a href="{{ $noTechUrl }}"
               class="relative px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors
                      {{ $isNoTech ? 'border-rose-600 text-rose-600' : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-200' }}">
                بدون تکنسین
                <span class="ms-1 inline-flex items-center justify-center min-w-[1.5rem] h-5 px-1.5 text-xs font-bold rounded-full
                            {{ $isNoTech ? 'bg-rose-100 text-rose-700' : ($noTechCount > 0 ? 'bg-gray-100 text-gray-700' : 'bg-gray-50 text-gray-400') }}">
                    {{ number_format($noTechCount) }}
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
                        <div class="flex items-center gap-1.5">
                            <a href="{{ route('crm.orders.show', $order) }}" class="font-medium text-gray-900 dark:text-gray-100 hover:text-brand-600" dir="ltr">
                                {{ $order->order_code }}
                            </a>
                            @if($order->is_lead)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700 border border-rose-200" title="این تماس به‌عنوان لید ثبت شده">لید</span>
                            @endif
                        </div>
                        @if($order->order_type && ! $order->is_lead)
                            <div class="text-xs text-gray-400 mt-0.5">{{ $order->order_type === 'service' ? 'نصب' : 'تعمیر' }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <div class="text-gray-900 dark:text-gray-100">{{ $order->customer_name ?: $order->customer?->display_name }}</div>
                        <div class="text-xs">@tel($order->customer_mobile)</div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                        {{ $order->brand?->name }}{{ $order->device ? ' / ' . $order->device->name : '' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                        {{ $order->technician ? trim($order->technician->firstname_tech ?: ($order->technician->first_name . ' ' . $order->technician->last_name)) : '—' }}
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                        @php
                            $loc = trim(implode(' / ', array_filter([
                                $order->province?->name,
                                $order->city?->name,
                                $order->region?->name,
                            ])));
                        @endphp
                        {{ $loc !== '' ? $loc : '—' }}
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex flex-col items-start gap-1">
                            <span class="px-2.5 py-1 text-xs font-medium rounded-full {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                            @if($order->return_type)
                            <span class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300 whitespace-nowrap" title="{{ $order->return_description }}">
                                ↩ {{ (string) $order->return_type === '1' ? 'برگشت انجام شده' : 'برگشت کنسل شده' }}
                            </span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap" dir="ltr">@jdatetime($order->created_at)</td>
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

    <div class="flex items-center justify-between flex-wrap gap-3 px-2">
        <div>{{ $orders->links() }}</div>
        <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
            <label for="per-page-select">تعداد در صفحه:</label>
            <select id="per-page-select"
                    onchange="(()=>{ const u=new URL(window.location); u.searchParams.set('per_page', this.value); u.searchParams.delete('page'); window.location=u; }).call(this)"
                    class="px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-xs">
                @foreach([25, 50, 100, 200] as $opt)
                    <option value="{{ $opt }}" @selected(($perPage ?? 50) === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
@endsection
