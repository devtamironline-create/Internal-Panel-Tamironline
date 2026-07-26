<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/50 text-[11px] text-gray-500"><tr>
                <th class="p-3 text-right">مشکل</th><th class="p-3">دسته</th><th class="p-3">شدت</th><th class="p-3">وضعیت</th><th class="p-3">راه‌حل پیشنهادی</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($issues as $issue)
                    <tr class="text-xs">
                        <td class="p-3 text-gray-800 dark:text-gray-100">{{ $issue->title }}</td>
                        <td class="p-3 text-center text-gray-500">{{ $issue->categoryLabel() }}</td>
                        <td class="p-3 text-center">{{ $issue->severityLabel() }}</td>
                        <td class="p-3 text-center">@include('seo::arm.partials._status', ['status' => $issue->status, 'label' => $issue->statusLabel()])</td>
                        <td class="p-3 text-gray-500 max-w-[280px]">{{ $issue->recommended_fix ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-4 text-center text-xs text-gray-400">مشکلی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
