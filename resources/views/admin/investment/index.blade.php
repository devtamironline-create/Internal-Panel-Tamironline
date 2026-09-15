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
            <div class="text-xs text-gray-500 mb-1">سرمایهٔ خالص (خرید − فروش)</div>
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
            <?php $pl = ($totalValue ?? $pricedTotalValue) - $totalCost; ?>
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

    {{-- افزایش سرمایه — قیمتِ واحد خودکار از قیمتِ لحظه‌ایِ نوسان برداشته می‌شود --}}
    <form method="POST" action="{{ route('admin.investment.store') }}"
          class="bg-white dark:bg-gray-800 rounded-xl border-2 border-emerald-200 dark:border-emerald-900 p-4 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        @csrf
        <div class="md:col-span-12 -mb-1">
            <h2 class="text-sm font-bold text-emerald-700 dark:text-emerald-400">📈 افزایش سرمایه (خرید)</h2>
        </div>
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
            <label class="block text-xs text-gray-500 mb-1">تاریخ خرید (شمسی)</label>
            <input type="text" name="bought_at" placeholder="1405/05/20" value="{{ old('bought_at') }}" dir="ltr" autocomplete="off"
                   class="jalali-datepicker w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm text-center">
        </div>
        <div class="md:col-span-9">
            <label class="block text-xs text-gray-500 mb-1">یادداشت</label>
            <input type="text" name="note" maxlength="500" value="{{ old('note') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
        </div>
        <div class="md:col-span-3">
            <button class="w-full px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded-lg font-bold">افزایش سرمایه</button>
        </div>
        <div class="md:col-span-12 text-[11px] text-gray-400 leading-6">
            «مبلغ کل خرید» به‌صورتِ خودکار از قیمتِ لحظه‌ایِ نوسان در لحظهٔ ثبت محاسبه می‌شود (مقدار × قیمتِ روزِ واحد).
        </div>
    </form>

    {{-- کاهش سرمایه (فروش) — فقط از دارایی‌هایی که موجودی دارند --}}
    <form method="POST" action="{{ route('admin.investment.sell') }}"
          class="bg-white dark:bg-gray-800 rounded-xl border-2 border-rose-200 dark:border-rose-900 p-4 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        @csrf
        <div class="md:col-span-12 -mb-1">
            <h2 class="text-sm font-bold text-rose-700 dark:text-rose-400">📉 کاهش سرمایه (فروش)</h2>
        </div>
        <div class="md:col-span-4">
            <label class="block text-xs text-gray-500 mb-1">دارایی <span class="text-rose-500">*</span></label>
            <select name="asset" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                <option value="" disabled selected>انتخاب کنید…</option>
                @foreach($positions->filter(fn ($p) => $p['amount'] > 0) as $p)
                    <option value="{{ $p['asset'] }}">
                        {{ $p['label'] }} — موجودی: {{ rtrim(rtrim(number_format($p['amount'], 8), '0'), '.') }} {{ $p['unit'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs text-gray-500 mb-1">مقدار فروش <span class="text-rose-500">*</span></label>
            <input type="number" name="amount" required step="any" min="0" dir="ltr"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs text-gray-500 mb-1">تاریخ فروش (شمسی)</label>
            <input type="text" name="sold_at" placeholder="1405/05/25" dir="ltr" autocomplete="off"
                   class="jalali-datepicker w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm text-center">
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs text-gray-500 mb-1">یادداشت</label>
            <input type="text" name="note" maxlength="500"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
        </div>
        <div class="md:col-span-2">
            <button class="w-full px-3 py-2 bg-rose-600 hover:bg-rose-700 text-white text-sm rounded-lg font-bold"
                    onclick="return confirm('این مقدار از سرمایه با قیمت لحظه‌ای فروخته و کم شود؟');">کاهش سرمایه</button>
        </div>
        <div class="md:col-span-12 text-[11px] text-gray-400 leading-6">
            قیمت فروش، قیمت لحظه‌ای نوسان در لحظهٔ ثبت است و بیشتر از موجودی نمی‌توانید بفروشید. مبلغ فروش از «سرمایهٔ خالص» کم می‌شود تا سود فروخته‌شده در سود/زیان کل بماند.
        </div>
    </form>

    {{-- ─── نمودار روند ارزش دارایی‌ها (snapshot روزانه) ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
            <h2 class="text-sm font-bold text-gray-800 dark:text-gray-100">روند ارزش دارایی‌ها</h2>
            <div class="flex items-center gap-2 text-xs">
                @foreach(['day' => 'روزانه', 'month' => 'ماهانه', 'year' => 'سالانه'] as $v => $label)
                    <a href="{{ request()->fullUrlWithQuery(['view' => $v]) }}"
                       class="px-2.5 py-1 rounded-lg border {{ $trendView === $v ? 'bg-emerald-600 text-white border-emerald-600' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        @if($trendPoints->isEmpty())
            <div class="text-center text-gray-400 text-sm py-10">
                هنوز ثبت روزانه‌ای وجود ندارد — از امروز هر روز ساعت ۰۸:۴۰ ارزش سبد ذخیره می‌شود و این نمودار روزبه‌روز کامل‌تر می‌شود.
            </div>
        @else
            <?php
                // تگِ خامِ PHP — همان دلیلِ بلوکِ نمودارِ بالا.
                $values = $trendPoints->pluck('value');
                $costs = $trendPoints->pluck('cost');
                $minV = min($values->min(), $costs->min());
                $maxV = max($values->max(), $costs->max(), $minV + 1);
                $n = $trendPoints->count();
                $plotW = 690.0; $plotH = 170.0; $x0 = 50.0; $y0 = 190.0;
                $px = fn ($i) => $n > 1 ? $x0 + $i * ($plotW / ($n - 1)) : $x0 + $plotW / 2;
                $py = fn ($val) => $y0 - (($val - $minV) / ($maxV - $minV)) * $plotH;
                $valueLine = $trendPoints->map(fn ($p, $i) => round($px($i), 1).','.round($py($p['value']), 1))->implode(' ');
                $costLine = $trendPoints->map(fn ($p, $i) => round($px($i), 1).','.round($py($p['cost']), 1))->implode(' ');
                $labelStep = max(1, (int) ceil($n / 10));
            ?>
            <div class="flex items-center gap-4 text-[11px] text-gray-500 mb-2">
                <span class="inline-flex items-center gap-1"><span class="w-3 h-0.5 bg-emerald-500 inline-block"></span> ارزش روز</span>
                <span class="inline-flex items-center gap-1"><span class="w-3 h-0.5 bg-gray-400 inline-block border-b border-dashed"></span> مبلغ خرید</span>
            </div>
            <div class="overflow-x-auto">
                <svg viewBox="0 0 760 220" class="w-full min-w-[640px]" style="direction: ltr;">
                    <line x1="{{ $x0 }}" y1="{{ $y0 }}" x2="{{ $x0 + $plotW }}" y2="{{ $y0 }}" stroke="currentColor" class="text-gray-300 dark:text-gray-600" stroke-width="1"/>
                    <text x="4" y="{{ round($py($maxV), 1) + 4 }}" font-size="9" class="fill-gray-400">{{ $maxV >= 1000000 ? number_format($maxV / 1000000, 1).'M' : number_format($maxV) }}</text>
                    <text x="4" y="{{ $y0 }}" font-size="9" class="fill-gray-400">{{ $minV >= 1000000 ? number_format($minV / 1000000, 1).'M' : number_format($minV) }}</text>
                    <polyline points="{{ $costLine }}" fill="none" stroke="currentColor" class="text-gray-400" stroke-width="1.5" stroke-dasharray="4 3"/>
                    <polyline points="{{ $valueLine }}" fill="none" stroke="currentColor" class="text-emerald-500" stroke-width="2"/>
                    @foreach($trendPoints as $i => $p)
                        <circle cx="{{ round($px($i), 1) }}" cy="{{ round($py($p['value']), 1) }}" r="3" class="fill-emerald-500">
                            <title>{{ $p['full'] }} — ارزش: {{ number_format($p['value']) }} تومان — خرید: {{ number_format($p['cost']) }} تومان</title>
                        </circle>
                        @if($i % $labelStep === 0 || $i === $n - 1)
                            <text x="{{ round($px($i), 1) }}" y="{{ $y0 + 16 }}" text-anchor="middle" font-size="9" class="fill-gray-500 dark:fill-gray-400">{{ $p['label'] }}</text>
                        @endif
                    @endforeach
                </svg>
            </div>
        @endif
    </div>

    {{-- لاگ تراکنش‌ها — همهٔ افزایش/کاهش‌های سرمایه --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-x-auto">
        <div class="px-4 pt-3 text-sm font-bold text-gray-800 dark:text-gray-100">لاگ تراکنش‌ها</div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs text-gray-500 border-b border-gray-100 dark:border-gray-700">
                    <th class="px-4 py-2 text-right">نوع</th>
                    <th class="px-4 py-2 text-right">دارایی</th>
                    <th class="px-4 py-2 text-right">مقدار</th>
                    <th class="px-4 py-2 text-right">قیمت واحد لحظهٔ ثبت</th>
                    <th class="px-4 py-2 text-right">مبلغ</th>
                    <th class="px-4 py-2 text-right">تاریخ</th>
                    <th class="px-4 py-2 text-right">یادداشت</th>
                    <th class="px-4 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr class="border-b border-gray-50 dark:border-gray-700/50">
                        <td class="px-4 py-2">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $row->isSell() ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                                {{ $row->isSell() ? 'کاهش' : 'افزایش' }}
                            </span>
                        </td>
                        <td class="px-4 py-2">{{ ($registry[$row->asset]['label'] ?? $row->asset) }}</td>
                        <td class="px-4 py-2" dir="ltr">{{ ($row->isSell() ? '−' : '') }}{{ rtrim(rtrim(number_format((float) $row->amount, 8), '0'), '.') }}</td>
                        <td class="px-4 py-2">{{ number_format($row->buy_unit_price) }}</td>
                        <td class="px-4 py-2 {{ $row->isSell() ? 'text-rose-600' : '' }}">{{ ($row->isSell() ? '−' : '') }}{{ number_format($row->cost()) }}</td>
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
                    <tr><td colspan="8" class="px-4 py-6 text-center text-gray-400">—</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
