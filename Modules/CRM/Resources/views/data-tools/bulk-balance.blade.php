@extends('layouts.admin')

@section('page-title', 'تنظیم مانده کیف‌پول گروهی')

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-6xl">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">💰 تنظیم مانده کیف‌پول گروهی</h1>
            <p class="text-xs text-gray-500 mt-1">لیست را paste کنید — برای هر خط: «نام تکنسین <span dir="ltr">[فاصله/تب]</span> مانده (با علامت)». مثال: «بهروز رحیمی 20425» برای بستانکار، «بهروز رحیمی -722000» برای بدهکار.</p>
        </div>
        <a href="{{ route('crm.data-tools.index') }}" class="text-xs text-gray-500 hover:text-gray-700">← بازگشت</a>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif

    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-4 text-xs leading-7">
        <strong>⚠ این عملیات destructive است:</strong>
        برای هر تکنسین در لیست:
        <br>۱) همه wallet_txs او حذف می‌شوند
        <br>۲) همه invoices او superseded می‌شوند (آرشیو می‌شوند)
        <br>۳) یک تراکنش «موجودی افتتاحیه از WP» با مقدار وارد شده درج می‌شود
        <br>۴) wallet_balance تکنسین به همان مقدار ست می‌شود
        <br><br>backup خودکار در <code class="bg-amber-100 px-1 rounded" dir="ltr">storage/app/crm/</code> ذخیره می‌شود.
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
        <form method="POST" action="{{ route('crm.data-tools.bulk-balance.apply') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">لیست تکنسین‌ها (هر خط: نام<span dir="ltr"> + </span>مانده)</label>
                <textarea name="list" rows="14" required
                          placeholder="مثال:&#10;بهروز رحیمی 20425&#10;مصطفی کرم زاده 722000&#10;سینا اسدی -1500000&#10;..."
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg font-mono text-sm leading-7" dir="ltr">{{ old('list', $list ?? '') }}</textarea>
                <p class="text-[11px] text-gray-500 mt-1">عدد مثبت = بستانکار (شرکت بدهکار به تکنسین). منفی = بدهکار (تکنسین بدهکار به شرکت).</p>
            </div>

            <div class="flex gap-2">
                <button type="submit" name="action" value="preview"
                        class="px-5 py-2.5 bg-gray-600 hover:bg-gray-700 text-white rounded-lg text-sm font-bold">
                    🔍 پیش‌نمایش
                </button>
                <button type="submit" name="action" value="apply"
                        onclick="return confirm('این عملیات destructive است. تراکنش‌های قدیمی تکنسین‌ها حذف می‌شوند و یک opening balance درج می‌شود. ادامه دهیم؟');"
                        class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-sm font-bold">
                    🔥 اعمال نهایی
                </button>
            </div>
        </form>
    </div>

    @if(! empty($results))
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 space-y-3">
            <div class="flex items-center gap-4 flex-wrap text-sm">
                <span class="text-gray-700 dark:text-gray-200 font-bold">خلاصه:</span>
                <span class="text-gray-600 dark:text-gray-300">کل: <strong>{{ $summary['total'] }}</strong></span>
                <span class="text-emerald-700">یافت‌شده: <strong>{{ $summary['ok'] }}</strong></span>
                @if($summary['notfound'] > 0)
                    <span class="text-rose-700">پیدا نشد: <strong>{{ $summary['notfound'] }}</strong></span>
                @endif
                @if($summary['ambiguous'] > 0)
                    <span class="text-amber-700">چندنامزد: <strong>{{ $summary['ambiguous'] }}</strong></span>
                @endif
                @if($action === 'apply')
                    <span class="text-emerald-700">اعمال شد: <strong>{{ $summary['applied'] }}</strong></span>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs">
                        <tr>
                            <th class="px-3 py-2 text-right">نام در لیست</th>
                            <th class="px-3 py-2 text-right">مانده هدف</th>
                            <th class="px-3 py-2 text-right">وضعیت</th>
                            <th class="px-3 py-2 text-right">تکنسین یافت‌شده</th>
                            <th class="px-3 py-2 text-right">مانده فعلی Panel</th>
                            <th class="px-3 py-2 text-right">عمل</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($results as $r)
                            <tr class="@if($r['status']==='notfound') bg-rose-50/50 @elseif($r['status']==='ambiguous') bg-amber-50/50 @endif">
                                <td class="px-3 py-2">{{ $r['input_name'] }}</td>
                                <td class="px-3 py-2 font-bold {{ $r['target'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}" dir="ltr">{{ number_format($r['target']) }}</td>
                                <td class="px-3 py-2">
                                    @if($r['status']==='ok')
                                        <span class="px-2 py-0.5 text-[10px] rounded-full bg-emerald-100 text-emerald-800">یافت شد</span>
                                    @elseif($r['status']==='notfound')
                                        <span class="px-2 py-0.5 text-[10px] rounded-full bg-rose-100 text-rose-800">پیدا نشد</span>
                                    @else
                                        <span class="px-2 py-0.5 text-[10px] rounded-full bg-amber-100 text-amber-800">{{ $r['matches_count'] }} مورد</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2">{{ $r['matched_name'] ?? '—' }}</td>
                                <td class="px-3 py-2" dir="ltr">{{ $r['current_balance'] !== null ? number_format($r['current_balance']) : '—' }}</td>
                                <td class="px-3 py-2">
                                    @if(! empty($r['applied']))
                                        <span class="text-emerald-700 text-xs font-bold">✓ اعمال شد</span>
                                    @elseif($action === 'apply' && $r['status'] !== 'ok')
                                        <span class="text-gray-400 text-xs">رد شد</span>
                                    @else
                                        <span class="text-gray-400 text-xs">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($action === 'preview')
                <div class="text-xs text-amber-700 dark:text-amber-300">
                    💡 این فقط پیش‌نمایش بود. برای اعمال «اعمال نهایی» را بزنید.
                </div>
            @endif
        </div>
    @endif

</div>
@endsection
