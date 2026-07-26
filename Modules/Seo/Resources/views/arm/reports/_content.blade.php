@php use Morilog\Jalali\Jalalian; @endphp
<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/50 text-[11px] text-gray-500"><tr>
                <th class="p-3 text-right">عنوان</th><th class="p-3">نوع</th><th class="p-3">کلمهٔ اصلی</th><th class="p-3">برنامه</th><th class="p-3">مهلت</th><th class="p-3">وضعیت</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($items as $item)
                    <tr class="text-xs">
                        <td class="p-3 text-gray-800 dark:text-gray-100">{{ $item->title }}</td>
                        <td class="p-3 text-center text-gray-500">{{ $item->typeLabel() }}</td>
                        <td class="p-3 text-center text-gray-500">{{ $item->primary_keyword ?: '—' }}</td>
                        <td class="p-3 text-center" dir="ltr">{{ $item->planned_at ? Jalalian::fromCarbon($item->planned_at->copy())->format('Y/m/d') : '—' }}</td>
                        <td class="p-3 text-center" dir="ltr">{{ $item->due_at ? Jalalian::fromCarbon($item->due_at->copy())->format('Y/m/d') : '—' }}</td>
                        <td class="p-3 text-center">@include('seo::arm.partials._status', ['status' => $item->status, 'label' => $item->statusLabel()])</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-4 text-center text-xs text-gray-400">در این بازه محتوایی برنامه‌ریزی نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
