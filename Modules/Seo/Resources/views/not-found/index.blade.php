@extends('layouts.admin')

@section('page-title', 'مانیتور ۴۰۴')

@section('main')
<div class="p-6 max-w-5xl mx-auto" dir="rtl">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-1">مانیتور صفحات یافت‌نشده (۴۰۴)</h1>
    <p class="text-sm text-gray-500 mb-4">فرانت هر بازدید ۴۰۴ را به <code class="font-mono ltr">/v1/seo/404</code> می‌فرستد. از روی هر ردیف می‌توانید ریدایرکت بسازید.</p>

    @if(session('success'))
        <div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600">
                <tr>
                    <th class="px-3 py-2 text-right">مسیر</th>
                    <th class="px-3 py-2 text-right">بازدید</th>
                    <th class="px-3 py-2 text-right">ارجاع‌دهنده</th>
                    <th class="px-3 py-2 text-right">آخرین بازدید</th>
                    <th class="px-3 py-2 text-right">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr class="border-t border-gray-100 dark:border-gray-700">
                    <td class="px-3 py-2 font-mono ltr" dir="ltr">{{ $log->uri }}</td>
                    <td class="px-3 py-2">{{ $log->hits }}</td>
                    <td class="px-3 py-2 font-mono ltr text-xs text-gray-500" dir="ltr">{{ \Illuminate\Support\Str::limit($log->referrer, 40) ?: '—' }}</td>
                    <td class="px-3 py-2 text-xs text-gray-500">
                        {{ $log->last_seen_at ? \Morilog\Jalali\Jalalian::fromDateTime($log->last_seen_at)->format('Y/m/d H:i') : '—' }}
                    </td>
                    <td class="px-3 py-2 flex gap-3">
                        <a href="{{ route('seo.admin.redirects.index', ['source' => $log->uri]) }}" class="text-blue-600 hover:underline">ساخت ریدایرکت</a>
                        <form method="POST" action="{{ route('seo.admin.not-found.destroy', $log) }}" onsubmit="return confirm('حذف این رکورد؟');">
                            @csrf @method('DELETE')
                            <button class="text-rose-600 hover:underline">حذف</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-3 py-8 text-center text-gray-400">رکورد ۴۰۴ ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
@endsection
