<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/50 text-[11px] text-gray-500"><tr>
                <th class="p-3 text-right">لینک</th><th class="p-3">نوع</th><th class="p-3">وضعیت</th><th class="p-3">follow</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($links as $link)
                    <tr class="text-xs">
                        <td class="p-3"><div class="text-gray-800 dark:text-gray-100">{{ $link->anchor_text ?: '—' }}</div><div class="text-[10px] text-gray-400" dir="ltr">{{ $link->target_url }}</div></td>
                        <td class="p-3 text-center text-gray-500">{{ $link->typeLabel() }}</td>
                        <td class="p-3 text-center">@include('seo::arm.partials._status', ['status' => $link->status, 'label' => $link->statusLabel()])</td>
                        <td class="p-3 text-center" dir="ltr">{{ $link->follow_type }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-4 text-center text-xs text-gray-400">آیتمی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
