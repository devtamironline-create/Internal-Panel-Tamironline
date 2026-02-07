@extends('layouts.admin')

@section('title', 'لاگ همگام‌سازی')

@section('main')
<div class="p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('warehouse.dashboard') }}" class="p-2 text-slate-400 hover:text-slate-200 hover:bg-slate-700 rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-100">لاگ همگام‌سازی</h1>
                <p class="text-slate-400 mt-1">تاریخچه عملیات همگام‌سازی با ووکامرس</p>
            </div>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="bg-slate-800 rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-700/50">
                    <tr>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-400">تاریخ</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-400">عملیات</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-400">وضعیت</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-400">پردازش شده</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-400">ایجاد شده</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-400">بروزرسانی</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-400">خطا</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-400">مدت</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-400">کاربر</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700">
                    @forelse($logs as $log)
                    <tr class="hover:bg-slate-700/50">
                        <td class="px-4 py-3 text-slate-300">
                            {{ $log->created_at->format('Y/m/d H:i:s') }}
                        </td>
                        <td class="px-4 py-3 text-slate-300">
                            {{ $log->action_label }}
                            @if($log->entity_id)
                            <span class="text-slate-500">(#{{ $log->entity_id }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-1 text-xs rounded-full bg-{{ $log->status_color }}-900/50 text-{{ $log->status_color }}-400">
                                {{ $log->status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-300">{{ $log->items_processed }}</td>
                        <td class="px-4 py-3 text-green-400">{{ $log->items_created }}</td>
                        <td class="px-4 py-3 text-blue-400">{{ $log->items_updated }}</td>
                        <td class="px-4 py-3 text-red-400">{{ $log->items_failed }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ $log->duration ?? '-' }}</td>
                        <td class="px-4 py-3 text-slate-300">
                            {{ $log->user?->full_name ?? 'سیستم' }}
                        </td>
                    </tr>
                    @if($log->error_message)
                    <tr class="bg-red-900/20">
                        <td colspan="9" class="px-4 py-2 text-sm text-red-400">
                            <span class="font-medium">خطا:</span> {{ $log->error_message }}
                        </td>
                    </tr>
                    @endif
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-slate-400">
                            هنوز عملیات همگام‌سازی انجام نشده است
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
        <div class="px-4 py-3 border-t border-slate-700">
            {{ $logs->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
