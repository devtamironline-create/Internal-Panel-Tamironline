<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/50 text-[11px] text-gray-500"><tr>
                <th class="p-3 text-right">کلمه</th><th class="p-3">صفحه</th><th class="p-3">رتبه قبلی</th><th class="p-3">رتبه فعلی</th><th class="p-3">تغییر</th><th class="p-3">ایمپرشن</th><th class="p-3">امتیاز فرصت</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($keywords as $kw)
                    @php($delta = ($kw->previous_position !== null && $kw->current_position !== null) ? round($kw->previous_position - $kw->current_position, 1) : null)
                    <tr class="text-xs text-center">
                        <td class="p-3 text-right text-gray-800 dark:text-gray-100">@if($kw->is_primary)⭐ @endif{{ $kw->keyword }}</td>
                        <td class="p-3 text-gray-500 max-w-[180px] truncate">{{ $kw->page?->title ?: '—' }}</td>
                        <td class="p-3" dir="ltr">{{ $kw->previous_position !== null ? number_format($kw->previous_position, 1) : '—' }}</td>
                        <td class="p-3 font-bold" dir="ltr">{{ $kw->current_position !== null ? number_format($kw->current_position, 1) : '—' }}</td>
                        <td class="p-3" dir="ltr">
                            @if($delta === null) — 
                            @elseif($delta > 0)<span class="text-emerald-600 font-bold">▲ {{ $delta }}</span>
                            @elseif($delta < 0)<span class="text-red-600 font-bold">▼ {{ abs($delta) }}</span>
                            @else <span class="text-gray-400">=</span>@endif
                        </td>
                        <td class="p-3" dir="ltr">{{ number_format($kw->impressions) }}</td>
                        <td class="p-3 font-bold text-brand-600" dir="ltr">{{ $kw->opportunity_score !== null ? number_format($kw->opportunity_score, 1) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-4 text-center text-xs text-gray-400">کلمه‌ای ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
