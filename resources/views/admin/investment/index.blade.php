@extends('layouts.admin')

@section('page-title', 'صندوق سرمایه')

@section('main')
<div class="space-y-6" dir="rtl">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100">💰 صندوق سرمایه</h1>
        @if($fetchedAt)
            <span class="text-xs text-gray-500" dir="rtl">قیمت‌ها: @jdatetime(\Illuminate\Support\Carbon::parse($fetchedAt))</span>
        @endif
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-2 text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-lg px-4 py-2 text-sm">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    @unless($navasanConfigured)
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-4 py-2 text-sm">
            کلید وب‌سرویس نوسان تنظیم نشده است (NAVASAN_API_KEY در .env). ثبت خرید کار می‌کند ولی ارزش‌گذاری روز در دسترس نیست.
        </div>
    @endunless

    {{-- جمع کل --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-xs text-gray-500 mb-1">مبلغ کل خرید</div>
            <div class="text-lg font-bold text-gray-800 dark:text-gray-100">{{ number_format($totalCost) }} <span class="text-xs font-normal">تومان</span></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-xs text-gray-500 mb-1">ارزش امروز</div>
            <div class="text-lg font-bold {{ ($totalValue ?? $pricedTotalValue) >= $totalCost ? 'text-emerald-600' : 'text-rose-600' }}">
                {{ number_format($totalValue ?? $pricedTotalValue) }} <span class="text-xs font-normal text-gray-500">تومان{{ $totalValue === null ? ' (فقط دارایی‌های قیمت‌دار)' : '' }}</span>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="text-xs text-gray-500 mb-1">سود / زیان</div>
            @php($pl = ($totalValue ?? $pricedTotalValue) - $totalCost)
            <div class="text-lg font-bold {{ $pl >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                {{ $pl >= 0 ? '+' : '' }}{{ number_format($pl) }} <span class="text-xs font-normal text-gray-500">تومان</span>
            </div>
        </div>
    </div>

    {{-- موقعیت‌ها به تفکیک دارایی --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs text-gray-500 border-b border-gray-100 dark:border-gray-700">
                    <th class="px-4 py-2 text-right">دارایی</th>
                    <th class="px-4 py-2 text-right">مقدار</th>
                    <th class="px-4 py-2 text-right">مبلغ خرید</th>
                    <th class="px-4 py-2 text-right">قیمت روز واحد</th>
                    <th class="px-4 py-2 text-right">ارزش امروز</th>
                    <th class="px-4 py-2 text-right">سود / زیان</th>
                </tr>
            </thead>
            <tbody>
                @forelse($positions as $p)
                    <tr class="border-b border-gray-50 dark:border-gray-700/50">
                        <td class="px-4 py-2 font-medium text-gray-800 dark:text-gray-100">{{ $p['label'] }}</td>
                        <td class="px-4 py-2">{{ rtrim(rtrim(number_format($p['amount'], 8), '0'), '.') }} {{ $p['unit'] }}</td>
                        <td class="px-4 py-2">{{ number_format($p['cost']) }}</td>
                        <td class="px-4 py-2">{{ $p['unit_price'] !== null ? number_format($p['unit_price']) : '—' }}</td>
                        <td class="px-4 py-2">{{ $p['value'] !== null ? number_format($p['value']) : '—' }}</td>
                        <td class="px-4 py-2 {{ ($p['profit'] ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ $p['profit'] !== null ? (($p['profit'] >= 0 ? '+' : '').number_format($p['profit'])) : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">هنوز خریدی ثبت نشده — از فرم پایین شروع کنید.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ثبت خرید جدید --}}
    <form method="POST" action="{{ route('admin.investment.store') }}"
          class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        @csrf
        <div class="md:col-span-3">
            <label class="block text-xs text-gray-500 mb-1">نوع دارایی <span class="text-rose-500">*</span></label>
            <select name="asset" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                @foreach($registry as $key => $meta)
                    <option value="{{ $key }}" @selected(old('asset') === $key)>{{ $meta['label'] }} ({{ $meta['unit'] }})</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs text-gray-500 mb-1">مقدار <span class="text-rose-500">*</span></label>
            <input type="number" name="amount" required step="any" min="0" value="{{ old('amount') }}" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
        </div>
        <div class="md:col-span-3">
            <label class="block text-xs text-gray-500 mb-1">قیمت خرید هر واحد (تومان) <span class="text-rose-500">*</span></label>
            <input type="number" name="buy_unit_price" required min="1" value="{{ old('buy_unit_price') }}" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs text-gray-500 mb-1">تاریخ خرید (شمسی)</label>
            <input type="text" name="bought_at" placeholder="1405/05/20" value="{{ old('bought_at') }}" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm text-center">
        </div>
        <div class="md:col-span-2">
            <button class="w-full px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded-lg font-bold">ثبت خرید</button>
        </div>
        <div class="md:col-span-12">
            <label class="block text-xs text-gray-500 mb-1">یادداشت</label>
            <input type="text" name="note" maxlength="500" value="{{ old('note') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
        </div>
    </form>

    {{-- ریز خریدها --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs text-gray-500 border-b border-gray-100 dark:border-gray-700">
                    <th class="px-4 py-2 text-right">دارایی</th>
                    <th class="px-4 py-2 text-right">مقدار</th>
                    <th class="px-4 py-2 text-right">قیمت واحد خرید</th>
                    <th class="px-4 py-2 text-right">مبلغ</th>
                    <th class="px-4 py-2 text-right">تاریخ خرید</th>
                    <th class="px-4 py-2 text-right">یادداشت</th>
                    <th class="px-4 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr class="border-b border-gray-50 dark:border-gray-700/50">
                        <td class="px-4 py-2">{{ ($registry[$row->asset]['label'] ?? $row->asset) }}</td>
                        <td class="px-4 py-2" dir="ltr">{{ rtrim(rtrim(number_format((float) $row->amount, 8), '0'), '.') }}</td>
                        <td class="px-4 py-2">{{ number_format($row->buy_unit_price) }}</td>
                        <td class="px-4 py-2">{{ number_format($row->cost()) }}</td>
                        <td class="px-4 py-2">{{ $row->bought_at ? \App\Support\JalaliDate::toJalali($row->bought_at->toDateString()) : '—' }}</td>
                        <td class="px-4 py-2 text-gray-500 text-xs">{{ $row->note }}</td>
                        <td class="px-4 py-2">
                            <form method="POST" action="{{ route('admin.investment.destroy', $row) }}"
                                  onsubmit="return confirm('این خرید حذف شود؟');">
                                @csrf @method('DELETE')
                                <button class="text-rose-600 text-xs hover:underline">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">—</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
