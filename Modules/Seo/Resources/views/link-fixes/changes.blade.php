@extends('layouts.admin')

@section('page-title', 'تاریخچهٔ اصلاح لینک‌ها')

@section('main')
<div class="p-6 max-w-5xl mx-auto" dir="rtl">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">تاریخچهٔ تغییرات و بازگردانی</h1>
        <a href="{{ route('seo.admin.link-fixes.index') }}" class="text-sm text-blue-600 hover:underline">بازگشت به قواعد &rarr;</a>
    </div>

    @if(session('success'))<div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-3 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 px-4 py-3 text-sm">{{ session('error') }}</div>@endif

    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">هر ردیف یک فیلدِ تغییرکرده است؛ نسخهٔ کاملِ قبلی ذخیره شده و با «بازگردانی» عیناً برمی‌گردد.</p>

    @if($changes->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 p-10 text-center text-gray-500 dark:text-gray-400">هنوز تغییری اعمال نشده است.</div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400 text-xs">
                    <tr>
                        <th class="px-3 py-3 text-right font-medium">رکورد</th>
                        <th class="px-3 py-3 text-right font-medium">قاعده</th>
                        <th class="px-3 py-3 text-center font-medium">فیلد</th>
                        <th class="px-3 py-3 text-center font-medium">تاریخ</th>
                        <th class="px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($changes as $chg)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $chg->reverted_at ? 'opacity-60' : '' }}">
                        <td class="px-3 py-3">
                            <span class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-[11px] font-bold">{{ $typeLabels[$chg->entity_type] ?? $chg->entity_type }} #{{ $chg->entity_id }}</span>
                            <span class="text-xs text-gray-600 dark:text-gray-300 ms-1">{{ \Illuminate\Support\Str::limit($chg->entity_label, 50) }}</span>
                        </td>
                        <td class="px-3 py-3 max-w-xs">
                            <div dir="ltr" class="truncate text-[11px] text-gray-500" title="{{ $chg->rule?->old_url }}">{{ $chg->rule?->old_url }}</div>
                        </td>
                        <td class="px-3 py-3 text-center text-xs"><code>{{ $chg->field }}</code> × {{ $chg->occurrences }}</td>
                        <td class="px-3 py-3 text-center text-[11px] text-gray-500">{{ \Morilog\Jalali\Jalalian::fromDateTime($chg->created_at)->format('Y/m/d H:i') }}</td>
                        <td class="px-3 py-3 text-left">
                            @if($chg->reverted_at)
                                <span class="text-[11px] text-amber-600 font-bold">بازگردانی‌شده</span>
                            @else
                            <form method="POST" action="{{ route('seo.admin.link-fixes.revert', $chg) }}"
                                  onsubmit="return confirm('این فیلد به نسخهٔ قبل از اصلاح برگردد؟');">
                                @csrf
                                <button class="px-3 py-1 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded text-[11px] font-bold hover:bg-gray-300">بازگردانی</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $changes->links() }}</div>
    @endif
</div>
@endsection
