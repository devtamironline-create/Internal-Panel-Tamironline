@php use Modules\Seo\Support\Arm\ArmEnums; @endphp
<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
    <h2 class="font-bold text-gray-800 dark:text-gray-100 mb-1">پیشرفت کل برنامه: <span dir="ltr">{{ $progress['overall_percent'] }}٪</span></h2>
    <p class="text-xs text-gray-500 mb-4">روز {{ $progress['day'] }} از ۹۰ · {{ $progress['completed'] }} از {{ $progress['total'] }} کار انجام شده · {{ $progress['delayed'] }} عقب‌افتاده</p>
    <div class="space-y-3">
        @foreach(ArmEnums::PHASES as $num => $phase)
            @php($rows = $byPhase[$num] ?? collect())
            @php($total = $rows->sum('c'))
            @php($done = $rows->whereIn('status', ['completed', 'skipped'])->sum('c'))
            <div>
                <div class="flex justify-between text-xs text-gray-600 dark:text-gray-300 mb-1">
                    <span>{{ $phase['title'] }} <span class="text-gray-400">(روزهای {{ $phase['days'] }})</span></span>
                    <span dir="ltr">{{ $done }}/{{ $total }}</span>
                </div>
                <div class="h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div class="h-full bg-brand-600 rounded-full" style="width: {{ $total > 0 ? round($done / $total * 100) : 0 }}%"></div>
                </div>
            </div>
        @endforeach
    </div>
</div>
