@extends('layouts.admin')
@section('page-title', 'بازیابی داده از بکاپ')
@section('main')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">بازیابی داده از بکاپ</h1>
        <p class="text-gray-600 dark:text-gray-400">داده‌های خراب شده (?????) را از دیتابیس بکاپ بازیابی کنید</p>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
        {{ session('error') }}
    </div>
    @endif

    <!-- Connection Status -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">وضعیت اتصال به بکاپ</h2>
        @if($connected)
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 px-4 py-3 rounded-lg flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            متصل به دیتابیس بکاپ: <strong>{{ config('database.connections.backup.database') }}</strong>
        </div>
        @else
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-lg">
            <p class="font-bold mb-2">خطا در اتصال به دیتابیس بکاپ</p>
            <p class="text-sm">{{ $error }}</p>
            <div class="mt-3 text-xs bg-white dark:bg-gray-700 p-3 rounded border border-red-200 dark:border-red-800">
                <p class="font-bold mb-2">برای راه‌اندازی، این مقادیر را به فایل <code>.env</code> اضافه کن:</p>
                <pre class="text-gray-700 dark:text-gray-300">BACKUP_DB_HOST=127.0.0.1
BACKUP_DB_PORT=3306
BACKUP_DB_DATABASE=panel_crm_backup
BACKUP_DB_USERNAME=
BACKUP_DB_PASSWORD=</pre>
                <p class="mt-2">سپس <code>php artisan config:clear</code> رو اجرا کن.</p>
            </div>
        </div>
        @endif
    </div>

    @if($connected)
    <!-- Tables List -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">جدول‌های مشترک</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">روی هر جدول کلیک کن تا داده‌ها رو مقایسه کنی</p>
        </div>
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">جدول</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">تعداد فعلی</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">تعداد بکاپ</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($commonTables as $table)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                    <td class="px-6 py-3 font-mono text-sm text-gray-900 dark:text-white">{{ $table }}</td>
                    <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-400">{{ number_format($currentTables[$table] ?? 0) }}</td>
                    <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-400">{{ number_format($backupTables[$table] ?? 0) }}</td>
                    <td class="px-6 py-3">
                        <a href="{{ route('admin.restore.show', $table) }}" class="inline-flex items-center gap-1 px-3 py-1.5 bg-brand-500 text-white text-xs rounded-lg hover:bg-brand-600 transition">
                            مقایسه و بازیابی
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
