@extends('layouts.admin')

@section('page-title', 'کمپین بله')

@section('main')
<div class="p-6 space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">💬 {{ $campaign->title }}</h1>
            <div class="text-xs text-gray-500 mt-1">
                {{ $campaign->audienceLabel() }} —
                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold
                    {{ $campaign->status === 'done' ? 'bg-emerald-100 text-emerald-800' : ($campaign->status === 'sending' ? 'bg-sky-100 text-sky-800' : 'bg-gray-100 text-gray-600') }}">
                    {{ $campaign->statusLabel() }}
                </span>
            </div>
        </div>
        <a href="{{ route('crm.bale-campaigns.index') }}" class="px-3 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm">← بازگشت</a>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg p-3 text-sm">{{ session('error') }}</div>@endif

    {{-- متن + آمار --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4">
            <div class="text-xs text-gray-400 mb-2">متنِ پیام</div>
            <p class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-line leading-7">{{ $campaign->text }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 space-y-3">
            @php $pct = $campaign->total_count > 0 ? (int) round(($campaign->sent_count + $campaign->failed_count) / $campaign->total_count * 100) : 0; @endphp
            <div class="grid grid-cols-3 text-center gap-2">
                <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 py-2">
                    <div class="font-bold text-emerald-700">{{ number_format($campaign->sent_count) }}</div>
                    <div class="text-[10px] text-emerald-700/70">موفق</div>
                </div>
                <div class="rounded-lg bg-rose-50 dark:bg-rose-900/20 py-2">
                    <div class="font-bold text-rose-700">{{ number_format($campaign->failed_count) }}</div>
                    <div class="text-[10px] text-rose-700/70">ناموفق</div>
                </div>
                <div class="rounded-lg bg-gray-50 dark:bg-gray-700/40 py-2">
                    <div class="font-bold text-gray-700 dark:text-gray-200">{{ number_format($pending) }}</div>
                    <div class="text-[10px] text-gray-500">در صف</div>
                </div>
            </div>
            <div class="w-full h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                <div class="h-full rounded-full {{ $pct >= 100 ? 'bg-emerald-500' : 'bg-sky-500' }}" style="width: {{ max($pct, 2) }}%"></div>
            </div>

            @if($pending > 0)
                <form method="POST" action="{{ route('crm.bale-campaigns.process', $campaign) }}">
                    @csrf
                    <button type="submit" class="w-full py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">
                        🚀 ارسالِ دستهٔ بعدی ({{ min($pending, $batchSize) }} گیرنده)
                    </button>
                </form>
            @else
                <div class="text-center text-sm text-emerald-600 font-bold py-2">✅ همهٔ گیرنده‌ها پردازش شدند</div>
            @endif

            <form method="POST" action="{{ route('crm.bale-campaigns.test', $campaign) }}" class="flex gap-2">
                @csrf
                <input type="text" name="phone" dir="ltr" placeholder="0912… تست"
                       class="flex-1 px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-xs text-center">
                <button type="submit" class="px-3 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg text-xs">ارسالِ تست</button>
            </form>
        </div>
    </div>

    {{-- گیرنده‌های اخیر --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 text-sm font-bold text-gray-700 dark:text-gray-200">آخرین گیرنده‌ها (۵۰ موردِ اخیر)</div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-600 dark:text-gray-300">
                <tr>
                    <th class="p-2.5 text-start">مشتری / شماره</th>
                    <th class="p-2.5 text-start">مسیر</th>
                    <th class="p-2.5 text-start">وضعیت</th>
                    <th class="p-2.5 text-start">زمان / خطا</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($recent as $r)
                    <tr>
                        <td class="p-2.5">
                            {{ trim(($r->customer?->first_name ?? '').' '.($r->customer?->last_name ?? '')) ?: '—' }}
                            <span class="text-xs text-gray-400" dir="ltr">{{ $r->phone }}</span>
                        </td>
                        <td class="p-2.5 text-xs">{{ $r->bale_user_id ? 'چتِ ربات' : 'سفیر (شماره)' }}</td>
                        <td class="p-2.5">
                            @if($r->status === 'sent')<span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-emerald-100 text-emerald-800">ارسال شد</span>
                            @elseif($r->status === 'failed')<span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-rose-100 text-rose-800">ناموفق</span>
                            @else<span class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-gray-100 text-gray-600">در صف</span>@endif
                        </td>
                        <td class="p-2.5 text-xs text-gray-500" dir="ltr">
                            @if($r->sent_at)@jdatetime($r->sent_at)@elseif($r->error){{ \Illuminate\Support\Str::limit($r->error, 60) }}@else — @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
