{{-- کارت‌های خلاصهٔ یک run --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 text-center">
        <div class="text-2xl font-bold {{ $run->avg_score >= 80 ? 'text-emerald-600' : ($run->avg_score >= 50 ? 'text-amber-600' : 'text-rose-600') }}">{{ $run->avg_score }}</div>
        <div class="text-xs text-gray-500">میانگین امتیاز</div>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-3 text-center">
        <div class="text-2xl font-bold text-gray-700 dark:text-gray-200">{{ $run->total_urls }}</div>
        <div class="text-xs text-gray-500">صفحه</div>
    </div>
    <div class="bg-rose-50 dark:bg-rose-900/20 rounded-xl shadow-sm p-3 text-center">
        <div class="text-2xl font-bold text-rose-600">{{ $run->critical_count }}</div>
        <div class="text-xs text-rose-600">بحرانی</div>
    </div>
    <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl shadow-sm p-3 text-center">
        <div class="text-2xl font-bold text-amber-600">{{ $run->warning_count }}</div>
        <div class="text-xs text-amber-600">هشدار</div>
    </div>
    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl shadow-sm p-3 text-center">
        <div class="text-2xl font-bold text-blue-600">{{ $run->notice_count }}</div>
        <div class="text-xs text-blue-600">توجه</div>
    </div>
</div>
