@extends('layouts.admin')
@section('page-title', 'متن قرارداد - ' . $registration->first_name . ' ' . $registration->last_name)

@section('main')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between print:hidden">
        <div class="flex items-center gap-4">
            <a href="{{ route('technician.admin.registrations.show', $registration->id) }}"
               class="flex items-center gap-1 text-gray-500 hover:text-gray-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                بازگشت به جزئیات درخواست
            </a>
        </div>
        <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-gray-700 border border-gray-200 text-sm font-medium rounded-lg hover:bg-gray-200">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            پرینت
        </button>
    </div>

    {{-- اطلاعات سرستون قرارداد --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 print:border-0 print:shadow-none">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h1 class="text-lg font-bold text-gray-900">متن قرارداد تکنسین</h1>
                <p class="text-sm text-gray-500 mt-0.5">{{ $registration->first_name }} {{ $registration->last_name }} — کدملی <span dir="ltr">{{ $registration->national_code }}</span></p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="text-sm">
                    <span class="text-gray-500">شماره قرارداد:</span>
                    <span class="font-bold text-gray-900" dir="ltr">{{ $contractNumber }}</span>
                </div>
                @if($registration->contract_signed_at)
                <div class="text-sm">
                    <span class="text-gray-500">تاریخ امضا:</span>
                    <span class="font-medium text-gray-800" dir="ltr">{{ \Morilog\Jalali\Jalalian::fromDateTime($registration->contract_signed_at)->format('Y/m/d H:i') }}</span>
                </div>
                <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-green-100 text-green-700">امضا شده</span>
                @else
                <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-amber-100 text-amber-700">امضا نشده</span>
                @endif
            </div>
        </div>

        {{-- متن قرارداد --}}
        <div class="prose prose-sm max-w-none border-t border-gray-100 pt-4 leading-7 text-gray-800 whitespace-pre-line">
            {!! nl2br(e($contractText)) !!}
        </div>

        {{-- امضای دیجیتال --}}
        @if($registration->contract_signature)
        <div class="mt-6 border-t border-gray-100 pt-4">
            <h2 class="text-sm font-bold text-gray-700 mb-2">امضای دیجیتال تکنسین</h2>
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-100 inline-block">
                <img src="{{ $registration->contract_signature }}" alt="امضا" class="max-h-32">
            </div>
        </div>
        @endif
    </div>
</div>

<style>
    @media print {
        body { background: #fff; }
        nav, aside, header, .print\:hidden { display: none !important; }
        main, .container { margin: 0 !important; padding: 0 !important; }
    }
</style>
@endsection
