@extends('layouts.admin')

@section('page-title', 'لیدها')

@section('main')
<div class="p-6 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">لیدها</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">تماس‌هایی که سفارش نشده‌اند — مستقل از لیست سفارش‌های واقعی.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.leads.export', ['format' => 'xlsx'] + request()->query()) }}"
               class="inline-flex items-center gap-1 px-3 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm" title="خروجی Excel با فیلترهای فعلی">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Excel
            </a>
            <a href="{{ route('crm.leads.export', ['format' => 'csv'] + request()->query()) }}"
               class="inline-flex items-center gap-1 px-3 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm" title="خروجی CSV با فیلترهای فعلی">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                CSV
            </a>
        </div>
    </div>

    {{-- فیلترها --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        <form method="GET" x-data="{
                provinceId: '{{ $provinceId }}',
                cityId: '{{ $cityId }}',
                async loadCities(reset = true) {
                    if (! this.provinceId) { this.cityId = ''; return; }
                    try {
                        const res = await fetch(`{{ url('/admin/crm/provinces') }}/${this.provinceId}/cities`, { headers: {'Accept':'application/json'} });
                        const data = await res.json();
                        const sel = this.$refs.citySelect;
                        sel.innerHTML = '<option value=&quot;&quot;>— همه —</option>'
                            + data.map(c => `<option value=&quot;${c.id}&quot;>${c.name}</option>`).join('');
                        if (reset) this.cityId = '';
                        sel.value = this.cityId;
                    } catch(e) {}
                }
              }"
              x-init="$watch('provinceId', () => loadCities(true)); if (provinceId) loadCities(false);">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">جستجو</label>
                    <input type="text" name="search" value="{{ $search }}" placeholder="کد، موبایل، نام مشتری..."
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">استان</label>
                    <select name="province_id" x-model="provinceId"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                        <option value="">— همه —</option>
                        @foreach($provinces as $p)
                            <option value="{{ $p->id }}" @selected($provinceId == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">شهر</label>
                    <select name="city_id" x-ref="citySelect" x-model="cityId" :disabled="!provinceId"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg disabled:opacity-50">
                        <option value="">— همه —</option>
                        @foreach($cities as $c)
                            <option value="{{ $c->id }}" @selected($cityId == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">برند</label>
                    <select name="brand_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                        <option value="">— همه —</option>
                        @foreach($brands as $b)
                            <option value="{{ $b->id }}" @selected($brandId == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">دستگاه</label>
                    <select name="device_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                        <option value="">— همه —</option>
                        @foreach($devices as $d)
                            <option value="{{ $d->id }}" @selected($deviceId == $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">دلیل عدم سفارش</label>
                    <select name="lead_reason_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                        <option value="">— همه —</option>
                        @foreach($leadReasons as $lr)
                            <option value="{{ $lr->id }}" @selected($leadReasonId == $lr->id)>{{ $lr->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">معرف</label>
                    <select name="introduction" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                        <option value="">— همه —</option>
                        @foreach($introductionList as $intro)
                            <option value="{{ $intro }}" @selected($introduction === $intro)>{{ $intro }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">از تاریخ</label>
                    <input type="text" name="from_date" value="{{ $fromDate }}" placeholder="1405/03/01" dir="ltr"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">تا تاریخ</label>
                    <input type="text" name="to_date" value="{{ $toDate }}" placeholder="1405/03/30" dir="ltr"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                </div>
            </div>

            <div class="flex items-center gap-2 mt-4">
                <button type="submit" class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded-lg text-sm">اعمال فیلتر</button>
                <a href="{{ route('crm.leads.index') }}" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm">پاک کردن فیلترها</a>
                <span class="text-sm text-gray-500 ms-auto">{{ number_format($leads->total()) }} لید پیدا شد</span>
            </div>
        </form>
    </div>

    {{-- جدول لیدها --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">کد</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">مشتری</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">دستگاه</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">محل</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">دلیل</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">تاریخ ثبت</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($leads as $lead)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-3">
                            <a href="{{ route('crm.orders.show', $lead) }}" class="font-medium text-gray-900 dark:text-gray-100 hover:text-rose-600" dir="ltr">
                                {{ $lead->order_code }}
                            </a>
                            <span class="block mt-0.5 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700 border border-rose-200">لید</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $lead->customer?->display_name ?? $lead->customer_name ?: '—' }}</div>
                            <div class="text-xs text-gray-500" dir="ltr">{{ $lead->customer?->mobile ?? $lead->customer_mobile }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                            {{ $lead->brand?->name }}{{ $lead->device ? ' / ' . $lead->device->name : '' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                            @php
                                $loc = trim(implode(' / ', array_filter([
                                    $lead->province?->name, $lead->city?->name, $lead->region?->name,
                                ])));
                            @endphp
                            {{ $loc !== '' ? $loc : '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                            <div class="font-medium">{{ $lead->leadReason?->name ?: '—' }}</div>
                            @if($lead->lead_notes)
                                <div class="text-xs text-gray-500 mt-1 whitespace-pre-wrap leading-5">{{ $lead->lead_notes }}</div>
                            @endif
                            @if($lead->problem_description)
                                <div class="text-xs text-gray-500 mt-1 whitespace-pre-wrap leading-5 border-t border-gray-100 pt-1">
                                    <span class="text-gray-400">شرح:</span> {{ $lead->problem_description }}
                                </div>
                            @endif
                            @if($lead->problem_title)
                                <div class="text-xs text-amber-700 mt-1">⚠ {{ $lead->problem_title }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap" dir="ltr">@jdatetime($lead->created_at)</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2 flex-wrap">
                                <a href="{{ route('crm.orders.show', $lead) }}" class="text-gray-600 hover:text-gray-900 text-sm">جزئیات</a>
                                <form action="{{ route('crm.orders.convert-from-lead', $lead) }}" method="POST" class="inline"
                                      onsubmit="return confirm('این لید به سفارش واقعی تبدیل می‌شود. ادامه؟');">
                                    @csrf
                                    <button type="submit" class="text-emerald-600 hover:text-emerald-800 text-sm">تبدیل به سفارش</button>
                                </form>
                                @can('delete-crm-order')
                                <form action="{{ route('crm.orders.destroy', $lead) }}" method="POST" class="inline"
                                      onsubmit="return confirm('این لید برای همیشه حذف می‌شود — قابل بازگشت نیست. مطمئنی؟');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-800 text-sm">حذف</button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-16 text-center text-sm text-gray-500">
                            هیچ لیدی با این فیلترها پیدا نشد.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($leads->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700">
                {{ $leads->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
