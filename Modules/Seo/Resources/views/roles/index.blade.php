@extends('layouts.admin')

@section('page-title', 'دسترسی نقش‌ها به سئو')

@section('main')
<div class="p-6 max-w-5xl mx-auto" dir="rtl">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-1">Role Manager سئو</h1>
    <p class="text-sm text-gray-500 mb-4">انتخاب کنید چه نقش‌هایی به مدیریت سئو (<code class="font-mono">manage-seo</code>) و دسترسی‌های ریزدانه دسترسی داشته باشند.</p>

    @if(session('success'))<div class="mb-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif

    <form method="POST" action="{{ route('seo.admin.roles.update') }}" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        @csrf @method('PUT')

        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="text-gray-600 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700">
                        <th class="text-right font-bold py-2 px-2">نقش</th>
                        <th class="text-center font-bold py-2 px-2" title="manage-seo">
                            مدیریت سئو
                            <div class="font-mono font-normal text-[10px] text-gray-400">manage-seo</div>
                        </th>
                        @foreach($granularLabels as $key => $meta)
                            <th class="text-center font-bold py-2 px-2" title="{{ $meta['description'] }}">
                                {{ $meta['label'] }}
                                <div class="font-mono font-normal text-[10px] text-gray-400">{{ $key }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $role)
                        <tr class="border-b border-gray-50 dark:border-gray-700 last:border-0">
                            <td class="py-2 px-2 text-gray-700 dark:text-gray-200 font-medium">{{ $role['name'] }}</td>
                            <td class="py-2 px-2 text-center">
                                <input type="checkbox" name="roles[]" value="{{ $role['id'] }}" @checked($role['has']) class="rounded">
                            </td>
                            @foreach($granularLabels as $key => $meta)
                                <td class="py-2 px-2 text-center">
                                    <input type="checkbox" name="granular[{{ $key }}][]" value="{{ $role['id'] }}" @checked($role['granular'][$key] ?? false) class="rounded">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pt-4 flex justify-end">
            <button class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">ذخیره</button>
        </div>
    </form>

    <div class="mt-6 text-xs text-gray-500 dark:text-gray-400 space-y-1">
        <p class="font-bold text-gray-600 dark:text-gray-300">راهنمای دسترسی‌های ریزدانه:</p>
        @foreach($granularLabels as $key => $meta)
            <p><span class="font-mono text-gray-400">{{ $key }}</span> — {{ $meta['description'] }}</p>
        @endforeach
        <p class="pt-2 text-gray-400">توجه: مسیرهای بخش سئو همچنان با <code class="font-mono">manage-seo</code> محافظت می‌شوند؛ برای دسترسیِ نقش‌های ریزدانه، manage-seo نیز برای آن نقش فعال بماند.</p>
    </div>
</div>
@endsection
