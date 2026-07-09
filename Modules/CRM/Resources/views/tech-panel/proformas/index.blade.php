@extends('crm::tech-panel.layout')

@section('title', 'پیش‌فاکتورها')

@section('body')
<div class="min-h-screen pb-nav" style="background:#eef0f4;">
    <div class="px-5 pt-6 pb-4 flex items-center justify-between">
        <a href="{{ route('tech.dashboard') }}" class="w-9 h-9 rounded-full bg-white flex items-center justify-center shadow-sm" aria-label="بازگشت">
            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/></svg>
        </a>
        <h1 class="font-bold text-gray-800">پیش‌فاکتورها</h1>
        <a href="{{ route('tech.proformas.create') }}" class="w-9 h-9 rounded-full bg-brand-600 text-white flex items-center justify-center shadow-sm" aria-label="جدید">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5"/></svg>
        </a>
    </div>

    @if(session('success'))<div class="mx-4 mb-3 bg-green-50 border border-green-200 text-green-800 rounded-xl p-3 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mx-4 mb-3 bg-red-50 border border-red-200 text-red-800 rounded-xl p-3 text-sm">{{ session('error') }}</div>@endif

    <div class="px-4 space-y-3">
        @forelse($proformas as $pf)
            <a href="{{ route('tech.proformas.show', $pf) }}" class="block bg-white rounded-2xl p-4 shadow-sm active:bg-gray-50">
                <div class="flex items-center justify-between">
                    <span class="font-mono text-sm font-bold text-gray-800" dir="ltr">{{ $pf->proforma_code }}</span>
                    <span class="text-[11px] px-2 py-0.5 rounded-full {{ $pf->statusBadge() }}">{{ $pf->statusLabel() }}</span>
                </div>
                <div class="mt-2 flex items-center justify-between text-sm">
                    <div class="text-gray-600">{{ $pf->customer_name ?: '—' }} <span class="text-gray-400 text-xs">{{ $pf->device_name }}</span></div>
                    <div class="font-bold text-brand-700" dir="ltr">{{ number_format($pf->total) }}</div>
                </div>
            </a>
        @empty
            <div class="bg-white rounded-2xl p-8 text-center text-gray-400 text-sm">
                هنوز پیش‌فاکتوری نساخته‌اید.
                <a href="{{ route('tech.proformas.create') }}" class="block mt-3 text-brand-600 font-bold">+ ساخت اولین پیش‌فاکتور</a>
            </div>
        @endforelse
    </div>

    <div class="px-4 mt-4">{{ $proformas->links() }}</div>
</div>
@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.proformas.index'])
@endsection
