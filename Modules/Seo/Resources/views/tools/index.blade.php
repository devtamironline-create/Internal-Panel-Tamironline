@extends('layouts.admin')

@section('page-title', 'ابزارهای سئو')

@section('main')
<div class="p-6 max-w-4xl mx-auto" dir="rtl">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">ابزارها و Audit Log</h1>

    @if(session('success'))<div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 rounded bg-rose-50 text-rose-700 text-sm">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <h2 class="font-bold text-gray-800 dark:text-gray-100 mb-2">خروجی (Export)</h2>
            <p class="text-xs text-gray-500 mb-3">تمام تنظیمات، ریدایرکت‌ها و متاها در یک فایل JSON.</p>
            <a href="{{ route('seo.admin.tools.export') }}" class="inline-block px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">دانلود فایل JSON</a>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <h2 class="font-bold text-gray-800 dark:text-gray-100 mb-2">ورودی (Import)</h2>
            <form method="POST" action="{{ route('seo.admin.tools.import') }}" enctype="multipart/form-data" class="space-y-2">
                @csrf
                <input type="file" name="file" accept=".json" required class="block w-full text-sm">
                @error('file')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                <button class="px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded-lg text-sm font-bold">وارد کردن</button>
            </form>
        </div>
    </div>

    <h2 class="text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">Audit Log تغییرات سئو</h2>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600">
                <tr><th class="px-3 py-2 text-right">زمان</th><th class="px-3 py-2 text-right">کاربر</th><th class="px-3 py-2 text-right">عمل</th><th class="px-3 py-2 text-right">موضوع</th><th class="px-3 py-2 text-right">توضیح</th></tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr class="border-t border-gray-100 dark:border-gray-700">
                    <td class="px-3 py-2 text-xs text-gray-500">{{ optional($log->created_at)->format('Y/m/d H:i') }}</td>
                    <td class="px-3 py-2 text-xs">{{ $log->user_id ?? '—' }}</td>
                    <td class="px-3 py-2 text-xs">{{ $log->action }}</td>
                    <td class="px-3 py-2 font-mono text-xs ltr" dir="ltr">{{ $log->subject }}</td>
                    <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-300">{{ $log->description }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-3 py-8 text-center text-gray-400">رویدادی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</div>
@endsection
