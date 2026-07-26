<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/50 text-[11px] text-gray-500"><tr>
                <th class="p-3 text-right">صفحه</th><th class="p-3">نوع</th><th class="p-3">رتبه</th><th class="p-3">ایمپرشن</th><th class="p-3">کلیک</th><th class="p-3">CTR</th><th class="p-3">تبدیل</th><th class="p-3">اولویت</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($pages as $page)
                    <tr class="text-xs text-center">
                        <td class="p-3 text-right"><div class="text-gray-800 dark:text-gray-100 max-w-[240px] truncate">{{ $page->title }}</div><div class="text-[10px] text-gray-400" dir="ltr">{{ $page->path }}</div></td>
                        <td class="p-3 text-gray-500">{{ $page->typeLabel() }}</td>
                        <td class="p-3 font-bold" dir="ltr">{{ $page->current_position !== null ? number_format($page->current_position, 1) : '—' }}</td>
                        <td class="p-3" dir="ltr">{{ number_format($page->impressions) }}</td>
                        <td class="p-3" dir="ltr">{{ number_format($page->clicks) }}</td>
                        <td class="p-3" dir="ltr">{{ number_format($page->ctr * 100, 2) }}٪</td>
                        <td class="p-3" dir="ltr">{{ number_format($page->conversions) }}</td>
                        <td class="p-3">@include('seo::arm.partials._priority', ['priority' => $page->priority])</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-4 text-center text-xs text-gray-400">صفحه‌ای ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
