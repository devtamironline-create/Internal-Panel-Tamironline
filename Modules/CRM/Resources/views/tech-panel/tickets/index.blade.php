@extends('crm::tech-panel.layout')

@section('title', 'تیکت‌های پشتیبانی')

@section('body')
<div class="min-h-screen pb-nav" style="background: #eef0f4;">
    <div class="relative overflow-hidden rounded-b-[40px] pb-20"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
        <div class="flex items-center justify-between px-5 pt-5">
            <a href="{{ route('tech.dashboard') }}"
               class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20"
               aria-label="بازگشت">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/>
                </svg>
            </a>
            <div class="text-white font-bold text-base">تیکت‌های پشتیبانی</div>
            <a href="{{ route('tech.tickets.create') }}"
               class="w-10 h-10 rounded-full bg-emerald-500 hover:bg-emerald-600 flex items-center justify-center text-white"
               aria-label="تیکت جدید">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
            </a>
        </div>
    </div>

    <div class="relative z-10 mx-3 -mt-12 space-y-3">
        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-xs text-emerald-800">{{ session('success') }}</div>
        @endif

        @forelse($tickets as $t)
            <a href="{{ route('tech.tickets.show', $t) }}"
               class="block bg-white rounded-2xl shadow-sm p-4">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $t->priorityBadgeClass() }}">
                        {{ $t->priorityLabel() }}
                    </span>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $t->statusBadgeClass() }}">
                        {{ $t->statusLabel() }}
                    </span>
                </div>
                <div class="text-sm font-medium text-gray-900 line-clamp-1">
                    {{ $t->subject ?: \Illuminate\Support\Str::limit($t->body, 60) }}
                </div>
                @if($t->order)
                    <div class="text-[10px] text-gray-400 mt-1" dir="ltr">
                        {{ $t->order->order_code }} — {{ $t->order->customer_name }}
                    </div>
                @endif
                <div class="text-[10px] text-gray-400 mt-1">
                    @jdatetime($t->created_at)
                </div>
            </a>
        @empty
            <div class="bg-white rounded-2xl p-8 text-center shadow-sm">
                <div class="text-sm text-gray-500 mb-3">تاکنون تیکتی ثبت نکرده‌اید.</div>
                <a href="{{ route('tech.tickets.create') }}"
                   class="inline-block px-4 py-2 rounded-xl bg-brand-700 text-white text-xs font-bold">
                    + ثبت تیکت جدید
                </a>
            </div>
        @endforelse

        @if($tickets->hasPages())
            <div class="pt-2">@include('crm::tech-panel._partials.pagination', ['paginator' => $tickets])</div>
        @endif
    </div>
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.profile'])
@endsection
