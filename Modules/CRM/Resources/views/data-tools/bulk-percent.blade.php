@extends('layouts.admin')

@section('page-title', 'تنظیم درصد گروهی تکنسین‌ها')

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-6xl">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">📋 تنظیم درصد گروهی تکنسین‌ها</h1>
            <p class="text-xs text-gray-500 mt-1">لیست را paste کنید — برای هر خط: «نام تکنسین <span dir="ltr">[فاصله/تب]</span> درصد سهم شرکت». تغییرات در Panel و WP هر دو اعمال می‌شود.</p>
        </div>
        <a href="{{ route('crm.data-tools.index') }}" class="text-xs text-gray-500 hover:text-gray-700">← بازگشت به ابزارها</a>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>
    @endif

    {{-- ─── فرم اصلی ─── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5">
        <form method="POST" action="{{ route('crm.data-tools.bulk-percent.apply') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">لیست تکنسین‌ها (هر خط: نام<span dir="ltr"> + </span>درصد سهم شرکت)</label>
                <textarea name="list" rows="14" required
                          placeholder="مثال:&#10;علی دریکوند 25&#10;مهدی حیدری 25&#10;سهیل توکلی 30&#10;..."
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg font-mono text-sm leading-7">{{ old('list', $list ?? '') }}</textarea>
                <p class="text-[11px] text-gray-500 mt-1">«درصد» = سهم شرکت (مثلاً 30 یعنی شرکت ۳۰٪، تکنسین ۷۰٪).</p>
            </div>

            <div class="flex items-center gap-4 flex-wrap">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">نوع محاسبه</label>
                    <select name="calc" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
                        <option value="external" @selected(($calc ?? 'external') === 'external')>External (روی percent)</option>
                        <option value="internal" @selected(($calc ?? '') === 'internal')>Internal (روی tech_per_of_all)</option>
                    </select>
                </div>

                <div class="flex gap-2 mt-6">
                    <button type="submit" name="action" value="preview"
                            class="px-5 py-2.5 bg-gray-600 hover:bg-gray-700 text-white rounded-lg text-sm font-bold">
                        🔍 پیش‌نمایش (بدون اعمال)
                    </button>
                    <button type="submit" name="action" value="apply"
                            onclick="return confirm('تغییرات روی Panel و WP اعمال می‌شود. ادامه دهیم؟');"
                            class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold">
                        ✓ اعمال نهایی
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- ─── خلاصه و نتایج ─── --}}
    @if(! empty($results))
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-5 space-y-3">
            <div class="flex items-center gap-4 flex-wrap text-sm">
                <span class="text-gray-700 dark:text-gray-200 font-bold">خلاصه:</span>
                <span class="text-gray-600 dark:text-gray-300">کل خطوط: <strong>{{ $summary['total'] }}</strong></span>
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
                @if(! $wp_available)
                    <span class="text-rose-700 ms-auto">⚠ WP DB قابل اتصال نبود — فقط Panel آپدیت شد</span>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs text-gray-600 dark:text-gray-300">
                        <tr>
                            <th class="px-3 py-2 text-right">نام در لیست</th>
                            <th class="px-3 py-2 text-right">درصد</th>
                            <th class="px-3 py-2 text-right">وضعیت</th>
                            <th class="px-3 py-2 text-right">تکنسین یافت‌شده</th>
                            <th class="px-3 py-2 text-right">موبایل</th>
                            <th class="px-3 py-2 text-right">درصد فعلی</th>
                            <th class="px-3 py-2 text-right">عمل</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($results as $r)
                            <tr class="@if($r['status']==='notfound') bg-rose-50/50 dark:bg-rose-900/10 @elseif($r['status']==='ambiguous') bg-amber-50/50 dark:bg-amber-900/10 @endif">
                                <td class="px-3 py-2">{{ $r['input_name'] }}</td>
                                <td class="px-3 py-2 font-bold">{{ $r['input_percent'] }}%</td>
                                <td class="px-3 py-2">
                                    @if($r['status']==='ok')
                                        <span class="px-2 py-0.5 text-[10px] rounded-full bg-emerald-100 text-emerald-800">یافت شد</span>
                                    @elseif($r['status']==='notfound')
                                        <span class="px-2 py-0.5 text-[10px] rounded-full bg-rose-100 text-rose-800">پیدا نشد</span>
                                    @else
                                        <span class="px-2 py-0.5 text-[10px] rounded-full bg-amber-100 text-amber-800">{{ $r['matches_count'] }} مورد</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    {{ $r['matched_name'] ?? '—' }}
                                    @if($r['status']==='ambiguous')
                                        <div class="text-[10px] text-amber-700 mt-0.5">{{ $r['sample_matches'] }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs" dir="ltr">{{ $r['mobile'] ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    @if($r['current_percent'] !== null)
                                        {{ $r['current_percent'] }}%
                                        @if($r['current_percent'] !== $r['input_percent'])
                                            <span class="text-rose-600 text-[10px]">→ {{ $r['input_percent'] }}%</span>
                                        @else
                                            <span class="text-emerald-600 text-[10px]">✓ یکسان</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
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
                    💡 این فقط پیش‌نمایش بود — هیچ تغییری اعمال نشد. اگر نتایج درست بود، روی «اعمال نهایی» کلیک کنید.
                </div>
            @endif
        </div>
    @endif

</div>
@endsection
