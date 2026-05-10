@extends('crm::tech-panel.layout')

@section('title', 'مشاهده تیکت')

@section('body')
<div class="min-h-screen pb-nav" style="background: #eef0f4;">
    <div class="relative overflow-hidden rounded-b-[40px] pb-12"
         style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);">
        <div class="flex items-center justify-between px-5 pt-5">
            <a href="{{ route('tech.tickets.index') }}"
               class="w-10 h-10 rounded-full bg-white/15 backdrop-blur flex items-center justify-center text-white border border-white/20">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/>
                </svg>
            </a>
            <div class="text-white font-bold text-base">تیکت #{{ $ticket->id }}</div>
            <div class="w-10"></div>
        </div>
    </div>

    @if(session('success'))
        <div class="mx-3 mt-3 bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-xs text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mx-3 mt-3 bg-rose-50 border border-rose-200 rounded-xl p-3 text-xs text-rose-700">{{ session('error') }}</div>
    @endif

    <div class="mx-3 -mt-6 bg-white rounded-2xl shadow-sm p-4 space-y-3">
        <div class="flex items-center gap-2">
            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $ticket->priorityBadgeClass() }}">{{ $ticket->priorityLabel() }}</span>
            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $ticket->statusBadgeClass() }}">{{ $ticket->statusLabel() }}</span>
            <span class="ms-auto text-[10px] text-gray-400">@jdatetime($ticket->created_at)</span>
        </div>

        @if($ticket->subject)
            <div class="text-base font-bold text-gray-900">{{ $ticket->subject }}</div>
        @endif

        @if($ticket->order)
            <a href="{{ route('tech.orders.show', $ticket->order) }}"
               class="block bg-gray-50 rounded-xl px-3 py-2 text-xs">
                <span class="text-gray-500">سفارش مرتبط:</span>
                <span class="font-bold" dir="ltr">{{ $ticket->order->order_code }}</span>
                <span class="text-gray-700">— {{ $ticket->order->customer_name }}</span>
            </a>
        @endif

        <div class="text-sm text-gray-800 leading-7 whitespace-pre-wrap">{{ $ticket->body }}</div>

        @if($ticket->image_path)
            <a href="{{ asset('storage/' . $ticket->image_path) }}" target="_blank">
                <img src="{{ asset('storage/' . $ticket->image_path) }}" alt="ضمیمه"
                     class="w-full max-w-xs rounded-xl border border-gray-200">
            </a>
        @endif
    </div>

    {{-- پاسخ‌ها --}}
    @if($ticket->replies->count())
        <div class="mx-3 mt-3 space-y-2">
            @foreach($ticket->replies as $r)
                <div class="bg-white rounded-2xl shadow-sm p-3 {{ $r->sender_type === 'admin' ? 'border-r-4 border-brand-500' : 'border-r-4 border-emerald-500' }}">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[11px] font-bold {{ $r->sender_type === 'admin' ? 'text-brand-700' : 'text-emerald-700' }}">
                            {{ $r->sender_type === 'admin' ? 'پشتیبانی' : 'شما' }}
                        </span>
                        <span class="text-[10px] text-gray-400">@jdatetime($r->created_at)</span>
                    </div>
                    <div class="text-sm text-gray-800 leading-7 whitespace-pre-wrap">{{ $r->body }}</div>
                    @if($r->image_path)
                        <a href="{{ asset('storage/' . $r->image_path) }}" target="_blank" class="block mt-2">
                            <img src="{{ asset('storage/' . $r->image_path) }}" alt="ضمیمه"
                                 class="w-full max-w-xs rounded-lg border border-gray-200">
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- فرم پاسخ --}}
    @if($ticket->status !== 'closed')
        <form method="POST" action="{{ route('tech.tickets.reply', $ticket) }}" enctype="multipart/form-data"
              class="mx-3 mt-3 bg-white rounded-2xl shadow-sm p-4 space-y-3">
            @csrf
            <div class="text-[11px] text-gray-500 font-bold">پاسخ شما</div>
            <textarea name="body" rows="4" required maxlength="5000"
                      placeholder="پاسخ یا توضیحات اضافه را اینجا بنویسید..."
                      class="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm leading-7 focus:bg-white focus:border-brand-400 focus:outline-none">{{ old('body') }}</textarea>
            <input type="file" name="image" accept="image/*"
                   class="block w-full text-xs text-gray-600 file:ms-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-bold file:text-xs">
            <button type="submit"
                    class="w-full py-2.5 rounded-xl text-white font-bold text-sm"
                    style="background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%);">
                ارسال پاسخ
            </button>
        </form>
    @else
        <div class="mx-3 mt-3 bg-gray-100 rounded-2xl p-4 text-center text-xs text-gray-500">
            این تیکت بسته شده است.
        </div>
    @endif
</div>

@include('crm::tech-panel._partials.bottom-nav', ['current' => 'tech.profile'])
@endsection
