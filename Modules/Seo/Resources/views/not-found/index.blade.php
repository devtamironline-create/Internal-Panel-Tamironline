@extends('layouts.admin')

@section('page-title', 'مانیتور ۴۰۴')

@section('main')
<div class="p-6 max-w-6xl mx-auto" dir="rtl">
    <div class="flex items-center justify-between mb-1 gap-3 flex-wrap">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">مانیتور صفحات یافت‌نشده (۴۰۴)</h1>
        <a href="{{ route('seo.admin.not-found.export', request()->query()) }}"
           class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold">خروجی CSV</a>
    </div>
    <p class="text-sm text-gray-500 mb-4">فرانت هر بازدید ۴۰۴ را به <code class="font-mono ltr">/v1/seo/404</code> می‌فرستد. از روی هر ردیف می‌توانید ریدایرکت بسازید.</p>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    {{-- نوار فیلتر --}}
    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2 items-end">
            <label class="md:col-span-2 text-sm">جستجو
                <input name="q" value="{{ $filters['q'] }}" placeholder="جستجو در مسیر/ارجاع‌دهنده…"
                       class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </label>
            <label class="text-sm">مرتب‌سازی
                <select name="sort" class="mt-1 w-full px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    <option value="hits" @selected($filters['sort'] === 'hits')>پربازدیدترین</option>
                    <option value="last_seen" @selected($filters['sort'] === 'last_seen')>آخرین بازدید</option>
                </select>
            </label>
            <label class="text-sm">ربات‌ها
                <select name="bot" class="mt-1 w-full px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    <option value="all" @selected($filters['bot'] === 'all')>همه</option>
                    <option value="exclude" @selected($filters['bot'] === 'exclude')>بدون ربات</option>
                    <option value="only" @selected($filters['bot'] === 'only')>فقط ربات</option>
                </select>
            </label>
            <label class="text-sm">نمایش
                <select name="show" class="mt-1 w-full px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    <option value="active" @selected($filters['show'] === 'active')>فعال</option>
                    <option value="ignored" @selected($filters['show'] === 'ignored')>نادیده‌گرفته‌شده</option>
                    <option value="all" @selected($filters['show'] === 'all')>همه</option>
                </select>
            </label>
        </div>
        <div class="mt-3 flex justify-end">
            <button class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">اعمال فیلتر</button>
        </div>
    </form>

    {{-- فرم حذف گروهی (با form-attribute به چک‌باکس‌ها وصل می‌شود تا تو‌درتو نشود) --}}
    <form id="nf-bulk" method="POST" action="{{ route('seo.admin.not-found.bulk-destroy') }}"
          onsubmit="return confirm('حذف رکوردهای انتخاب‌شده؟');">
        @csrf @method('DELETE')
    </form>

    <div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-right"><input type="checkbox" onclick="document.querySelectorAll('.nf-check').forEach(c=>c.checked=this.checked)"></th>{{-- انتخاب همه --}}
                        <th class="px-3 py-2 text-right">مسیر</th>
                        <th class="px-3 py-2 text-right">بازدید</th>
                        <th class="px-3 py-2 text-right">ربات</th>
                        <th class="px-3 py-2 text-right">اولین بازدید</th>
                        <th class="px-3 py-2 text-right">آخرین بازدید</th>
                        <th class="px-3 py-2 text-right">پیشنهاد ریدایرکت</th>
                        <th class="px-3 py-2 text-right">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    @php($suggested = $suggestions[$log->id] ?? null)
                    <tr class="border-t border-gray-100 dark:border-gray-700 {{ $log->ignored_at ? 'opacity-60' : '' }}">
                        <td class="px-3 py-2"><input type="checkbox" form="nf-bulk" name="ids[]" value="{{ $log->id }}" class="nf-check"></td>
                        <td class="px-3 py-2 font-mono ltr" dir="ltr">{{ $log->uri }}</td>
                        <td class="px-3 py-2">{{ $log->hits }}</td>
                        <td class="px-3 py-2">
                            @if($log->is_bot)
                                <span class="px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700">ربات</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500">کاربر</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-500">
                            {{ $log->first_seen_at ? \Morilog\Jalali\Jalalian::fromDateTime($log->first_seen_at)->format('Y/m/d H:i') : '—' }}
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-500">
                            {{ $log->last_seen_at ? \Morilog\Jalali\Jalalian::fromDateTime($log->last_seen_at)->format('Y/m/d H:i') : '—' }}
                        </td>
                        <td class="px-3 py-2 font-mono ltr text-xs" dir="ltr">
                            @if($suggested)
                                <span class="text-emerald-600">{{ $suggested }}</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <div class="flex gap-3 items-center">
                                <a href="{{ route('seo.admin.redirects.index', array_filter(['source' => $log->uri, 'target' => $suggested])) }}"
                                   class="text-blue-600 hover:underline whitespace-nowrap">ساخت ریدایرکت</a>
                                @if($log->ignored_at)
                                    <form method="POST" action="{{ route('seo.admin.not-found.unignore', $log) }}">
                                        @csrf
                                        <button class="text-emerald-600 hover:underline whitespace-nowrap">بازگردانی</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('seo.admin.not-found.ignore', $log) }}">
                                        @csrf
                                        <button class="text-gray-500 hover:underline whitespace-nowrap">نادیده‌گرفتن</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('seo.admin.not-found.destroy', $log) }}" onsubmit="return confirm('حذف این رکورد؟');">
                                    @csrf @method('DELETE')
                                    <button class="text-rose-600 hover:underline">حذف</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-3 py-8 text-center text-gray-400">رکورد ۴۰۴ ثبت نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3 flex items-center justify-between">
            <button type="submit" form="nf-bulk" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-sm font-bold">حذف انتخاب‌شده‌ها</button>
            <div>{{ $logs->links() }}</div>
        </div>
    </div>
</div>
@endsection
