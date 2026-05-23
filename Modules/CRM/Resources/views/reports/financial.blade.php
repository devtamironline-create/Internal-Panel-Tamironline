@extends('layouts.admin')

@section('page-title', 'گزارش مالی')

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-7xl">

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">📊 گزارش مالی</h1>
            <p class="text-xs text-gray-500 mt-1">جمع‌بندی فاکتورها، کیف‌پول و سود در بازه‌ی شمسی.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('crm.reports.financial.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}"
               class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold">
                خروجی Excel
            </a>
            <a href="{{ route('crm.reports.financial.export', array_merge(request()->query(), ['format' => 'csv'])) }}"
               class="px-3 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded-lg text-xs font-bold">
                خروجی CSV
            </a>
        </div>
    </div>

    {{-- ─── فیلتر ─── --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">از تاریخ</label>
                <input type="text" name="from_date" value="{{ $filters['from_j'] }}" dir="ltr" placeholder="1405/02/01" readonly
                       class="jalali-datepicker w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg cursor-pointer bg-white text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">تا تاریخ</label>
                <input type="text" name="to_date" value="{{ $filters['to_j'] }}" dir="ltr" placeholder="1405/02/31" readonly
                       class="jalali-datepicker w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg cursor-pointer bg-white text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">تکنسین</label>
                @php
                    $techOptions = ['' => '— همه —'];
                    foreach ($technicians as $t) { $techOptions[(string) $t->id] = $t->full_name; }
                @endphp
                <x-searchable-select
                    name="technician_id"
                    :options="$techOptions"
                    :value="$filters['technician_id']"
                    placeholder="— همه —"
                    searchPlaceholder="جستجوی تکنسین..." />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">نوع سند</label>
                <select name="doc_type" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                    @foreach($docTypes as $k => $v)
                        <option value="{{ $k }}" @selected($filters['doc_type'] === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">شماره سند</label>
                <input type="text" name="doc_no" value="{{ $filters['doc_no'] }}" dir="ltr"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">شماره موبایل مشتری</label>
                <input type="text" name="mobile" value="{{ $filters['mobile'] }}" dir="ltr" placeholder="09xxxxxxxxx"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">درصد سودرسانی</label>
                <div class="flex gap-1">
                    <select name="profit_op" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                        <option value="">—</option>
                        @foreach($profitOps as $k => $v)
                            <option value="{{ $k }}" @selected($filters['profit_op'] === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="profit_val" value="{{ $filters['profit_val'] }}" min="0" max="100"
                           class="w-20 px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                </div>
            </div>
            <div class="flex items-end gap-2">
                <button class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">
                    اعمال فیلتر
                </button>
                <a href="{{ route('crm.reports.financial') }}" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-sm">
                    پاک کردن
                </a>
            </div>
        </div>
    </form>

    {{-- ─── باکس‌های جمع‌بندی (ردیف ۱) ─── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
        @php
            $boxClass = 'bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 border border-gray-100 dark:border-gray-700';
            $labelClass = 'text-[10px] text-gray-500 uppercase tracking-wider';
            $valueClass = 'text-base md:text-lg font-bold mt-1';
        @endphp

        <div class="{{ $boxClass }}">
            <div class="{{ $labelClass }}">جمع کل فاکتورها</div>
            <div class="{{ $valueClass }} text-gray-900 dark:text-gray-100" dir="ltr">{{ number_format($summary['total_invoice']) }}</div>
            <div class="text-[10px] text-gray-400 mt-0.5">{{ number_format($summary['invoice_count']) }} فاکتور</div>
        </div>

        <div class="{{ $boxClass }}">
            <div class="{{ $labelClass }}">سهم تعمیرآنلاین</div>
            <div class="{{ $valueClass }} text-emerald-600" dir="ltr">{{ number_format($summary['company_share']) }}</div>
        </div>

        <div class="{{ $boxClass }}">
            <div class="{{ $labelClass }}">سهم تعمیرکار</div>
            <div class="{{ $valueClass }} text-indigo-600" dir="ltr">{{ number_format($summary['tech_share']) }}</div>
        </div>

        <div class="{{ $boxClass }}">
            <div class="{{ $labelClass }}">ایجاد بستانکاری</div>
            <div class="{{ $valueClass }} text-emerald-700" dir="ltr">{{ number_format($summary['reward']) }}</div>
        </div>

        <div class="{{ $boxClass }}">
            <div class="{{ $labelClass }}">ایجاد بدهکاری</div>
            <div class="{{ $valueClass }} text-rose-600" dir="ltr">{{ number_format($summary['penalty']) }}</div>
        </div>

        <div class="{{ $boxClass }}">
            <div class="{{ $labelClass }}">درصد سودآوری</div>
            <div class="{{ $valueClass }} text-amber-600" dir="ltr">{{ $summary['profit_pct'] }} %</div>
        </div>

        <div class="{{ $boxClass }}">
            <div class="{{ $labelClass }}">هزینه‌ها (قطعات)</div>
            <div class="{{ $valueClass }} text-gray-700 dark:text-gray-300" dir="ltr">{{ number_format($summary['expenses']) }}</div>
        </div>
    </div>

    {{-- ─── باکس‌های جمع‌بندی (ردیف ۲) ─── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div class="{{ $boxClass }} border-emerald-200 dark:border-emerald-800">
            <div class="{{ $labelClass }}">شارژ کیف‌پول انجام شده</div>
            <div class="{{ $valueClass }} text-emerald-600" dir="ltr">{{ number_format($summary['wallet_charge']) }} <span class="text-xs font-normal">تومان</span></div>
        </div>

        <div class="{{ $boxClass }} {{ $summary['tech_status_val'] > 0 ? 'border-emerald-200' : ($summary['tech_status_val'] < 0 ? 'border-rose-200' : 'border-gray-200') }}">
            <div class="{{ $labelClass }}">وضعیت حساب تعمیرکار</div>
            <div class="{{ $valueClass }} {{ $summary['tech_status_val'] > 0 ? 'text-emerald-600' : ($summary['tech_status_val'] < 0 ? 'text-rose-600' : 'text-gray-600') }}">
                {{ $summary['tech_status'] }}
            </div>
        </div>
    </div>

    {{-- ─── لیست ردیف‌به‌ردیف اسناد ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-900 dark:text-gray-100">📑 اسناد مالی در این بازه</h2>
            <span class="text-xs text-gray-500">{{ number_format($rows->total()) }} رکورد</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">تاریخ</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">نوع</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">شماره سند</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">تکنسین</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">مشتری</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">مبلغ</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">توضیح</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($rows as $r)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-2 text-xs text-gray-600 whitespace-nowrap" dir="ltr">{{ $r['date_jalali'] }}</td>
                            <td class="px-4 py-2 text-xs">
                                <span class="px-2 py-0.5 rounded text-[11px]
                                    @if($r['type'] === 'invoice') bg-sky-100 text-sky-700
                                    @elseif($r['type'] === 'wallet_charge') bg-emerald-100 text-emerald-700
                                    @elseif($r['type'] === 'reward') bg-emerald-100 text-emerald-700
                                    @elseif($r['type'] === 'penalty') bg-rose-100 text-rose-700
                                    @else bg-gray-100 text-gray-700 @endif">
                                    {{ $r['type_label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-700" dir="ltr">{{ $r['ref'] }}</td>
                            <td class="px-4 py-2 text-xs">{{ $r['tech_name'] }}</td>
                            <td class="px-4 py-2 text-xs">{{ $r['customer'] }}</td>
                            <td class="px-4 py-2 text-xs font-bold {{ $r['amount'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}" dir="ltr">
                                {{ ($r['amount'] >= 0 ? '+' : '') . number_format($r['amount']) }}
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-500">{{ $r['note'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-12 text-center text-gray-500">رکوردی در این بازه یافت نشد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700">
            {{ $rows->links() }}
        </div>
    </div>

</div>
@endsection
