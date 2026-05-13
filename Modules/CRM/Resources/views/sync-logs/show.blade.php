@extends('layouts.admin')

@section('page-title', 'جزئیات لاگ سینک #' . $log->id)

@section('main')
<div class="p-4 md:p-6 space-y-4 max-w-5xl">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">لاگ سینک #{{ $log->id }}</h1>
        <a href="{{ route('crm.sync-logs.index') }}" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-700 dark:text-gray-200 rounded-lg text-sm">بازگشت</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
        <div><div class="text-xs text-gray-500">زمان</div><div>{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</div></div>
        <div><div class="text-xs text-gray-500">جهت</div><div>{{ $log->direction === 'inbound' ? 'ورودی (WP→Laravel)' : 'خروجی (Laravel→WP)' }}</div></div>
        <div><div class="text-xs text-gray-500">موجودیت</div><div>{{ $log->entity_type }}</div></div>
        <div><div class="text-xs text-gray-500">Endpoint</div><div class="font-mono text-xs break-all" dir="ltr">{{ $log->endpoint }}</div></div>
        <div><div class="text-xs text-gray-500">Laravel id</div><div>{{ $log->entity_id ?? '—' }}</div></div>
        <div><div class="text-xs text-gray-500">WP id</div><div>{{ $log->wp_id ?? '—' }}</div></div>
        <div><div class="text-xs text-gray-500">HTTP</div><div>{{ $log->http_status ?? '—' }}</div></div>
        <div><div class="text-xs text-gray-500">وضعیت</div><div>{{ $log->status }}</div></div>
        @if ($log->error_message)
            <div class="col-span-2 md:col-span-4"><div class="text-xs text-gray-500">خطا</div><div class="text-rose-600 text-sm">{{ $log->error_message }}</div></div>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
            <h2 class="text-sm font-bold mb-2">Payload</h2>
            <pre dir="ltr" class="text-xs bg-gray-50 dark:bg-gray-900 p-3 rounded overflow-x-auto max-h-[60vh]">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
            <h2 class="text-sm font-bold mb-2">Response</h2>
            <pre dir="ltr" class="text-xs bg-gray-50 dark:bg-gray-900 p-3 rounded overflow-x-auto max-h-[60vh]">{{ json_encode($log->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </div>
</div>
@endsection
