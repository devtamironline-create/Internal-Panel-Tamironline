@extends('layouts.admin')

@section('page-title', 'کمپین‌های بله')

@section('main')
<div class="p-6 space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">💬 پیام‌رسانِ بله — کمپین‌ها</h1>
            <p class="text-sm text-gray-500 mt-1">ارسالِ گروهیِ پیام به مشتری‌ها از طریقِ چتِ ربات (رایگان) یا سفیر (با شماره).</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.notify-templates.index') }}" class="px-3 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">📨 قالب‌های پیامِ سفارش</a>
            <a href="{{ route('crm.bale-campaigns.create') }}" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">+ کمپینِ جدید</a>
        </div>
    </div>

    <div class="flex items-center gap-3 text-xs">
        <span class="px-2.5 py-1 rounded-full {{ $botConfigured ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
            چتِ ربات: {{ $botConfigured ? 'فعال ✓' : 'پیکربندی نشده (BALE_BOT_TOKEN)' }}
        </span>
        <span class="px-2.5 py-1 rounded-full {{ $safirConfigured ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
            سفیر (ارسال با شماره): {{ $safirConfigured ? 'فعال ✓' : 'پیکربندی نشده (BALE_SAFIR_*)' }}
        </span>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>@endif

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="p-3 text-start">کمپین</th>
                    <th class="p-3 text-start">مخاطب</th>
                    <th class="p-3 text-start">پیشرفت</th>
                    <th class="p-3 text-start">وضعیت</th>
                    <th class="p-3 text-start">سازنده / تاریخ</th>
                    <th class="p-3 w-24"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($campaigns as $c)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                        <td class="p-3">
                            <a href="{{ route('crm.bale-campaigns.show', $c) }}" class="font-bold text-brand-700 hover:underline">{{ $c->title }}</a>
                            <div class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ \Illuminate\Support\Str::limit($c->text, 80) }}</div>
                        </td>
                        <td class="p-3 text-xs">{{ $c->audienceLabel() }}</td>
                        <td class="p-3 text-xs" dir="ltr">
                            <span class="text-emerald-600 font-bold">{{ number_format($c->sent_count) }}</span>
                            @if($c->failed_count) / <span class="text-rose-600">{{ number_format($c->failed_count) }}✗</span> @endif
                            <span class="text-gray-400">از {{ number_format($c->total_count) }}</span>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 text-[11px] font-bold rounded-full
                                {{ $c->status === 'done' ? 'bg-emerald-100 text-emerald-800' : ($c->status === 'sending' ? 'bg-sky-100 text-sky-800' : 'bg-gray-100 text-gray-600') }}">
                                {{ $c->statusLabel() }}
                            </span>
                        </td>
                        <td class="p-3 text-xs text-gray-500">
                            {{ trim(($c->creator?->first_name ?? '').' '.($c->creator?->last_name ?? '')) ?: '—' }}
                            <span dir="ltr" class="text-gray-400">— @jdatetime($c->created_at)</span>
                        </td>
                        <td class="p-3">
                            <form method="POST" action="{{ route('crm.bale-campaigns.destroy', $c) }}"
                                  onsubmit="return confirm('کمپین «{{ $c->title }}» حذف شود؟');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-rose-600 hover:text-rose-800">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center text-sm text-gray-400">هنوز کمپینی نساخته‌اید.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($campaigns->hasPages())<div class="p-3 border-t border-gray-100 dark:border-gray-700">{{ $campaigns->links() }}</div>@endif
    </div>
</div>
@endsection
