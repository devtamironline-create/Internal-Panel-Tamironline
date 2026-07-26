@extends('layouts.admin')

@section('page-title', 'بازوی سئو — فرصت‌های رشد')

@section('main')
@php use Modules\Seo\Support\Arm\ArmEnums; @endphp
<div class="p-6 space-y-4" dir="rtl">
    <div>
        <h1 class="text-xl font-bold text-gray-800 dark:text-white">📈 فرصت‌های رشد</h1>
        <p class="text-sm text-gray-500 mt-1">امتیاز فرصت = ایمپرشن نرمال × پتانسیل رتبه × شکاف CTR × ارزش تجاری × اطمینان (وزن‌ها در تنظیمات).</p>
    </div>

    @if(session('success'))<div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-3 text-sm">{{ session('success') }}</div>@endif

    <form method="GET" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 flex flex-wrap items-end gap-2 text-sm">
        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="جستجو…" class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm w-44">
        <select name="type" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ انواع</option>
            @foreach(ArmEnums::OPPORTUNITY_TYPES as $k => $v)<option value="{{ $k }}" @selected($filters['type'] === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="status" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ وضعیت‌ها</option>
            @foreach(ArmEnums::OPPORTUNITY_STATUSES as $k => $v)<option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="quadrant" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-sm">
            <option value="">همهٔ ربع‌ها</option>
            <option value="quick_win" @selected($filters['quadrant'] === 'quick_win')>برد سریع</option>
            <option value="strategic" @selected($filters['quadrant'] === 'strategic')>استراتژیک</option>
            <option value="filler" @selected($filters['quadrant'] === 'filler')>پرکننده</option>
            <option value="avoid" @selected($filters['quadrant'] === 'avoid')>پرهیز</option>
        </select>
        <button class="px-4 py-2 bg-brand-600 text-white rounded-lg text-sm">اعمال</button>
        <a href="{{ route('seo.arm.opportunities.index') }}" class="px-3 py-2 text-gray-500 text-sm">پاک‌کردن</a>
    </form>

    <div class="space-y-2">
        @forelse($opportunities as $opp)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-lg font-black text-brand-600" dir="ltr" title="امتیاز فرصت">{{ number_format($opp->opportunity_score, 1) }}</span>
                            <span class="text-[11px] px-2 py-0.5 bg-gray-100 dark:bg-gray-700 rounded-full text-gray-600 dark:text-gray-300">{{ $opp->typeLabel() }}</span>
                            @include('seo::arm.partials._status', ['status' => $opp->status, 'label' => $opp->statusLabel()])
                            <span class="text-[10px] text-gray-400">اثر {{ number_format($opp->impact_score, 0) }}/۱۰ · تلاش {{ number_format($opp->effort_score, 0) }}/۱۰ · اطمینان {{ number_format($opp->confidence_score * 100, 0) }}٪</span>
                        </div>
                        <div class="font-bold text-sm text-gray-800 dark:text-gray-100 mt-1">{{ $opp->title }}</div>
                        @if($opp->description)<p class="text-xs text-gray-500 leading-6 mt-1">{{ $opp->description }}</p>@endif
                        @if($opp->recommended_action)
                            <p class="text-xs mt-2 bg-sky-50 dark:bg-sky-900/20 border border-sky-100 dark:border-sky-800 rounded-lg p-2 text-sky-800 dark:text-sky-300">🎯 اقدام پیشنهادی: {{ $opp->recommended_action }}</p>
                        @endif
                        <div class="text-[10px] text-gray-400 mt-1">
                            @if($opp->page) صفحه: {{ $opp->page->title }} @endif
                            @if($opp->keyword) · کلمه: {{ $opp->keyword->keyword }} @endif
                            @if($opp->due_at) · مهلت: {{ \Morilog\Jalali\Jalalian::fromCarbon($opp->due_at->copy())->format('Y/m/d') }} @endif
                        </div>
                    </div>
                    @can('seo-arm-manage')
                        <form method="POST" action="{{ route('seo.arm.opportunities.status', $opp) }}">
                            @csrf @method('PUT')
                            <select name="status" onchange="this.form.submit()" class="text-[11px] px-2 py-1.5 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg">
                                @foreach(ArmEnums::OPPORTUNITY_STATUSES as $k => $v)<option value="{{ $k }}" @selected($opp->status === $k)>{{ $v }}</option>@endforeach
                            </select>
                        </form>
                    @endcan
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                @include('seo::arm.partials._empty', ['message' => 'فرصتی با این فیلترها پیدا نشد.', 'icon' => '📈'])
            </div>
        @endforelse
    </div>
    <div>{{ $opportunities->links() }}</div>
</div>
@endsection
