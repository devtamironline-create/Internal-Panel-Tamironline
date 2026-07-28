@extends('layouts.admin')

@section('page-title', 'قرارداد من')

@section('main')
@php use Morilog\Jalali\Jalalian; @endphp
<div class="p-6 space-y-4" dir="rtl">
    <div>
        <h1 class="text-xl font-bold text-gray-800 dark:text-white">📄 قرارداد من</h1>
        <p class="text-sm text-gray-500 mt-1">قراردادهای صادرشده برای شما. برای تکمیل مدارک و امضا، روی هر قرارداد بزنید.</p>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif

    @forelse($contracts as $c)
        <a href="{{ route('my-contracts.show', $c) }}"
           class="block bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:border-brand-400 transition">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div>
                    <div class="font-bold text-sm text-gray-800 dark:text-gray-100">قرارداد ارائه خدمات مشاوره‌ای و تعهد محرمانگی</div>
                    <div class="text-[11px] text-gray-400 mt-1" dir="ltr">{{ $c->contract_number }}</div>
                    <div class="text-[11px] text-gray-500 mt-1">
                        از {{ Jalalian::fromDateTime($c->start_date)->format('Y/m/d') }}
                        @if($c->end_date) تا {{ Jalalian::fromDateTime($c->end_date)->format('Y/m/d') }} @endif
                    </div>
                </div>
                <div class="text-left">
                    @php($tone = ['awaiting_staff' => 'amber', 'submitted' => 'blue', 'approved' => 'emerald', 'rejected' => 'red'][$c->status] ?? 'gray')
                    <span @class([
                        'inline-flex px-2 py-0.5 rounded-full text-[11px] font-bold',
                        'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $tone === 'amber',
                        'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' => $tone === 'blue',
                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $tone === 'emerald',
                        'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $tone === 'red',
                    ])>{{ $c->statusLabel() }}</span>
                    <div class="mt-2 flex items-center gap-2 justify-end">
                        <div class="w-20 h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                            @php($p = $c->completionPercent())
                            <div @class(['h-full rounded-full', 'bg-emerald-500' => $p === 100, 'bg-amber-500' => $p < 100]) style="width: {{ $p }}%"></div>
                        </div>
                        <span class="text-[11px] text-gray-500" dir="ltr">{{ $p }}%</span>
                    </div>
                </div>
            </div>
            @if($c->status === 'rejected' && $c->reject_reason)
                <p class="mt-2 text-[11px] text-red-600 bg-red-50 dark:bg-red-900/20 rounded p-2">نیازمند اصلاح: {{ $c->reject_reason }}</p>
            @endif
            @if($c->status === 'approved')
                <p class="mt-2 text-[11px] text-emerald-600">✅ تأیید شده — نسخهٔ PDF مهر و امضاشده آمادهٔ دانلود است.</p>
            @endif
        </a>
    @empty
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 text-center py-16 text-gray-400">
            <div class="text-3xl mb-2">📄</div>
            <p class="text-sm">در حال حاضر قراردادی برای شما صادر نشده است.</p>
        </div>
    @endforelse
</div>
@endsection
