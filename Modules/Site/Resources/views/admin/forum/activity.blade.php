@extends('layouts.admin')

@section('page-title', 'Activity Log انجمن')

@section('main')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900">

    <div class="sticky top-0 z-30 bg-white/95 dark:bg-gray-800/95 backdrop-blur border-b border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="p-4 flex items-center justify-between gap-4">
            <div>
                <div class="text-xs text-gray-500 mb-1">
                    <a href="{{ route('site.admin.forum.dashboard') }}" class="hover:text-blue-600">داشبورد انجمن</a> ›
                </div>
                <h1 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span>📜</span>
                    <span>Activity Log — تاریخچه عملیات ادمین</span>
                </h1>
            </div>
        </div>
    </div>

    <div class="p-4 lg:p-6">
        {{-- Filters --}}
        <form method="GET" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 mb-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium mb-1">نوع عمل</label>
                <select name="action" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                    <option value="">— همه —</option>
                    @foreach($actions as $a)
                        <option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">User ID</label>
                <input type="number" name="user_id" value="{{ request('user_id') }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1">Question ID</label>
                <input type="number" name="question_id" value="{{ request('question_id') }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm">اعمال فیلتر</button>
                <a href="{{ route('site.admin.forum.activity') }}" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">پاک‌کردن</a>
            </div>
        </form>

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900 text-xs text-gray-700 dark:text-gray-300">
                    <tr>
                        <th class="text-right px-4 py-3">زمان</th>
                        <th class="text-right px-4 py-3">عمل</th>
                        <th class="text-right px-4 py-3">ادمین</th>
                        <th class="text-right px-4 py-3">سوال</th>
                        <th class="text-right px-4 py-3">جزئیات</th>
                        <th class="text-right px-4 py-3">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($logs as $log)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap" dir="ltr">
                                {{ \Morilog\Jalali\Jalalian::fromCarbon($log->created_at)->format('Y/m/d H:i:s') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-[10px] px-2 py-0.5 rounded bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 font-mono">{{ $log->action }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                {{ $log->user?->full_name ?? '—' }}
                                @if($log->user_id)<span class="text-gray-400 font-mono">#{{ $log->user_id }}</span>@endif
                            </td>
                            <td class="px-4 py-3 text-xs">
                                @if($log->question)
                                    <a href="{{ route('site.admin.forum.questions.show', $log->question->id) }}" class="text-blue-600 hover:underline">
                                        #{{ $log->question->id }} — {{ \Illuminate\Support\Str::limit($log->question->title, 60) }}
                                    </a>
                                @elseif($log->question_id)
                                    <span class="text-gray-400 font-mono">#{{ $log->question_id }} (حذف‌شده)</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[11px] text-gray-600 font-mono ltr" dir="ltr">
                                @if($log->meta){{ json_encode($log->meta, JSON_UNESCAPED_UNICODE) }}@endif
                            </td>
                            <td class="px-4 py-3 text-[11px] font-mono text-gray-500 ltr" dir="ltr">{{ $log->ip ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-gray-400 text-xs py-10">رویدادی پیدا نشد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
