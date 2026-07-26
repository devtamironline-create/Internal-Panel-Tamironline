@extends('layouts.admin')

@section('page-title', 'بازوی سئو — '.$typeLabel)

@section('main')
@php use Morilog\Jalali\Jalalian; use Modules\Seo\Support\Arm\ArmEnums; @endphp
<div class="p-6 space-y-4" dir="rtl">
    <div class="flex items-start justify-between flex-wrap gap-3 print:hidden">
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-white">{{ $typeLabel }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $project->name }} — بازه: {{ Jalalian::fromCarbon($from)->format('Y/m/d') }} تا {{ Jalalian::fromCarbon($to)->format('Y/m/d') }}</p>
        </div>
        <div class="flex items-center gap-2">
            {{-- انتخاب بازهٔ گزارش --}}
            <form method="GET" class="flex items-center gap-2 text-sm">
                <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-xs" dir="ltr">
                <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="px-2 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg text-xs" dir="ltr">
                <button class="px-3 py-2 bg-brand-600 text-white rounded-lg text-xs">اعمال بازه</button>
            </form>
            <button onclick="window.print()" class="px-3 py-2 bg-gray-700 text-white rounded-lg text-xs">🖨️ چاپ / ذخیره</button>
            <a href="{{ route('seo.arm.reports.index') }}" class="px-3 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-200 rounded-lg text-xs">همهٔ گزارش‌ها</a>
        </div>
    </div>

    {{-- خلاصهٔ عملکرد بازه (مشترک) --}}
    @if($snapshot)
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach([
                ['کلیک', $snapshot->clicks, $previous?->clicks, 'int'],
                ['ایمپرشن', $snapshot->impressions, $previous?->impressions, 'int'],
                ['میانگین رتبه', $snapshot->average_position, $previous?->average_position, 'pos'],
                ['CTR', $snapshot->ctr, $previous?->ctr, 'pct'],
            ] as [$label, $cur, $prev, $fmt])
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3">
                    <div class="text-[11px] text-gray-500">{{ $label }}</div>
                    <div class="text-lg font-black text-gray-800 dark:text-gray-100" dir="ltr">
                        @if($fmt === 'pct') {{ number_format($cur * 100, 2) }}٪ @elseif($fmt === 'pos') {{ number_format($cur, 1) }} @else {{ number_format($cur) }} @endif
                    </div>
                    <div class="text-[10px] text-gray-400" dir="ltr">
                        @if($prev !== null)
                            قبلی: @if($fmt === 'pct'){{ number_format($prev * 100, 2) }}٪ @elseif($fmt === 'pos'){{ number_format($prev, 1) }} @else {{ number_format($prev) }} @endif
                        @else
                            اولین بازه — مبنا
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-3 text-sm">برای این بازه snapshot عملکردی ثبت نشده است.</div>
    @endif

    @includeIf('seo::arm.reports._'.$type)
</div>
@endsection
