@php
    $sevBadge = [
        'critical' => 'bg-rose-100 text-rose-700',
        'warning' => 'bg-amber-100 text-amber-700',
        'notice' => 'bg-blue-100 text-blue-700',
        'good' => 'bg-emerald-100 text-emerald-700',
    ];
    $sevLabel = ['critical' => 'بحرانی', 'warning' => 'هشدار', 'notice' => 'توجه', 'good' => 'سالم'];
@endphp
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600">
            <tr>
                <th class="px-3 py-2 text-right">URL</th>
                <th class="px-3 py-2 text-right">کد</th>
                <th class="px-3 py-2 text-right">امتیاز</th>
                <th class="px-3 py-2 text-right">شدت</th>
                <th class="px-3 py-2 text-right">مشکلات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($audits as $a)
            <tr class="border-t border-gray-100 dark:border-gray-700 align-top">
                <td class="px-3 py-2 font-mono ltr text-xs max-w-xs truncate" dir="ltr" title="{{ $a->url }}">{{ $a->url }}</td>
                <td class="px-3 py-2">{{ $a->status_code }}</td>
                <td class="px-3 py-2 font-bold {{ $a->score >= 80 ? 'text-emerald-600' : ($a->score >= 50 ? 'text-amber-600' : 'text-rose-600') }}">{{ $a->score }}</td>
                <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs {{ $sevBadge[$a->severity] ?? '' }}">{{ $sevLabel[$a->severity] ?? $a->severity }}</span></td>
                <td class="px-3 py-2">
                    <ul class="space-y-0.5">
                        @foreach(($a->issues ?? []) as $iss)
                            <li class="text-xs text-gray-600 dark:text-gray-300">• {{ $iss['message'] ?? '' }}</li>
                        @endforeach
                    </ul>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-3 py-8 text-center text-gray-400">موردی نیست.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $audits->links() }}</div>
