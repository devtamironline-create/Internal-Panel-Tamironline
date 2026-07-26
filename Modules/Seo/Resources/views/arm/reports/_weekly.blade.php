@php use Morilog\Jalali\Jalalian; @endphp
<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
    <h2 class="font-bold text-gray-800 dark:text-gray-100 mb-3">روند snapshotهای بازه</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/50 text-[11px] text-gray-500"><tr>
                <th class="p-2">تاریخ</th><th class="p-2">کلیک</th><th class="p-2">ایمپرشن</th><th class="p-2">CTR</th><th class="p-2">میانگین رتبه</th><th class="p-2">Top3</th><th class="p-2">Top10</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($series as $s)
                    <tr class="text-center text-xs">
                        <td class="p-2">{{ Jalalian::fromCarbon($s->snapshot_date->copy())->format('Y/m/d') }}</td>
                        <td class="p-2" dir="ltr">{{ number_format($s->clicks) }}</td>
                        <td class="p-2" dir="ltr">{{ number_format($s->impressions) }}</td>
                        <td class="p-2" dir="ltr">{{ number_format($s->ctr * 100, 2) }}٪</td>
                        <td class="p-2" dir="ltr">{{ number_format($s->average_position, 2) }}</td>
                        <td class="p-2" dir="ltr">{{ $s->keywords_top_3 }}</td>
                        <td class="p-2" dir="ltr">{{ $s->keywords_top_10 }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-4 text-center text-xs text-gray-400">در این بازه snapshot ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="font-bold text-gray-800 dark:text-gray-100 mb-3">کارهای تکمیل‌شده در بازه</h2>
        @forelse($completed as $item)
            <div class="text-xs py-1.5 border-b border-gray-50 dark:border-gray-700/50 last:border-0 text-gray-600 dark:text-gray-300">✅ {{ $item->title }}</div>
        @empty
            <p class="text-xs text-gray-400">موردی نیست.</p>
        @endforelse
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
        <h2 class="font-bold text-gray-800 dark:text-gray-100 mb-3">محتوای منتشرشده در بازه</h2>
        @forelse($published as $item)
            <div class="text-xs py-1.5 border-b border-gray-50 dark:border-gray-700/50 last:border-0 text-gray-600 dark:text-gray-300">📰 {{ $item->title }}</div>
        @empty
            <p class="text-xs text-gray-400">موردی نیست.</p>
        @endforelse
    </div>
</div>
