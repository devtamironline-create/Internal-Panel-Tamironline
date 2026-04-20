@extends('layouts.admin')
@section('page-title', 'مقایسه ' . $table)
@section('main')
<div class="space-y-6" x-data="{ selectedIds: [], selectedColumns: @js(array_values($textColumns)) }">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">مقایسه: {{ $table }}</h1>
            <p class="text-gray-600 dark:text-gray-400">
                <span x-show="selectedIds.length === 0">{{ $totalCorrupted }} رکورد خراب پیدا شد</span>
                <span x-show="selectedIds.length > 0" x-text="selectedIds.length + ' رکورد انتخاب شده از ' + {{ $totalCorrupted }}"></span>
            </p>
        </div>
        <a href="{{ route('admin.restore.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
            بازگشت
        </a>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">{{ session('error') }}</div>
    @endif

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 flex flex-wrap items-center gap-4">
        <form method="GET" class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="only_corrupted" value="1" {{ $onlyCorrupted ? 'checked' : '' }} onchange="this.form.submit()" class="rounded text-brand-500">
                فقط رکوردهای خراب (?????)
            </label>
        </form>
    </div>

    <!-- Column Selection -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
        <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-3">ستون‌هایی که میخوای بازیابی بشن</h3>
        <div class="flex flex-wrap gap-2">
            @foreach($textColumns as $col)
            <label class="inline-flex items-center gap-1.5 px-3 py-1 border-2 rounded-lg cursor-pointer text-sm transition"
                :class="selectedColumns.includes('{{ $col }}') ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20 text-brand-700 dark:text-brand-400' : 'border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300'">
                <input type="checkbox" value="{{ $col }}" x-model="selectedColumns" class="sr-only">
                {{ $col }}
            </label>
            @endforeach
        </div>
    </div>

    <!-- Restore Form -->
    <form action="{{ route('admin.restore.do', $table) }}" method="POST"
        onsubmit="return confirm('آیا از بازیابی ' + document.querySelectorAll('input[name=&quot;ids[]&quot;]:checked').length + ' رکورد مطمئنید؟ این عمل غیرقابل بازگشت است.')">
        @csrf
        <template x-for="col in selectedColumns" :key="col">
            <input type="hidden" name="columns[]" :value="col">
        </template>

        <!-- Records Comparison -->
        <div class="space-y-4">
            @forelse($currentRows as $current)
            @php
                $id = $current->{$primaryKey};
                $backup = $backupRows->get($id);
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
                <div class="p-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="ids[]" value="{{ $id }}" x-model="selectedIds" class="rounded text-brand-500">
                        <span class="font-mono text-sm font-bold text-gray-900 dark:text-white">ID: {{ $id }}</span>
                    </label>
                    @if(!$backup)
                    <span class="text-xs text-red-500">در بکاپ یافت نشد</span>
                    @endif
                </div>

                @if($backup)
                <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-200 dark:divide-gray-600">
                    <!-- Current (corrupted) -->
                    <div class="p-4">
                        <div class="text-xs font-bold text-red-500 mb-2">داده فعلی (خراب)</div>
                        <div class="space-y-1.5">
                            @foreach($textColumns as $col)
                                @php $val = $current->$col ?? ''; @endphp
                                @if($val !== null && $val !== '')
                                <div class="text-xs">
                                    <span class="font-mono text-gray-500">{{ $col }}:</span>
                                    <span class="text-gray-900 dark:text-gray-300 mr-1">{{ mb_substr($val, 0, 100) }}{{ mb_strlen($val) > 100 ? '...' : '' }}</span>
                                </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <!-- Backup (correct) -->
                    <div class="p-4 bg-green-50/30 dark:bg-green-900/5">
                        <div class="text-xs font-bold text-green-600 mb-2">داده بکاپ (سالم)</div>
                        <div class="space-y-1.5">
                            @foreach($textColumns as $col)
                                @php $val = $backup->$col ?? ''; @endphp
                                @if($val !== null && $val !== '')
                                <div class="text-xs">
                                    <span class="font-mono text-gray-500">{{ $col }}:</span>
                                    <span class="text-gray-900 dark:text-gray-300 mr-1">{{ mb_substr($val, 0, 100) }}{{ mb_strlen($val) > 100 ? '...' : '' }}</span>
                                </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif
            </div>
            @empty
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-8 text-center text-gray-500 dark:text-gray-400">
                هیچ رکوردی پیدا نشد
            </div>
            @endforelse
        </div>

        <!-- Actions -->
        @if($currentRows->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 sticky bottom-4 mt-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button type="button" @click="selectedIds = @js($currentRows->pluck($primaryKey)->toArray())" class="text-sm text-brand-500 hover:text-brand-600">انتخاب همه این صفحه</button>
                <button type="button" @click="selectedIds = []" class="text-sm text-gray-500 hover:text-gray-700">پاک کردن</button>
            </div>
            <button type="submit" :disabled="selectedIds.length === 0 || selectedColumns.length === 0" class="px-6 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-show="selectedIds.length === 0">رکوردی انتخاب نشده</span>
                <span x-show="selectedIds.length > 0" x-text="'بازیابی ' + selectedIds.length + ' رکورد'"></span>
            </button>
        </div>
        @endif
    </form>

    <!-- Pagination -->
    @if($totalPages > 1)
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 flex items-center justify-center gap-2">
        @if($page > 1)
        <a href="{{ route('admin.restore.show', $table) }}?page={{ $page - 1 }}&only_corrupted={{ $onlyCorrupted ? 1 : 0 }}" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm">قبلی</a>
        @endif
        <span class="text-sm text-gray-600 dark:text-gray-400">صفحه {{ $page }} از {{ $totalPages }}</span>
        @if($page < $totalPages)
        <a href="{{ route('admin.restore.show', $table) }}?page={{ $page + 1 }}&only_corrupted={{ $onlyCorrupted ? 1 : 0 }}" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm">بعدی</a>
        @endif
    </div>
    @endif
</div>
@endsection
