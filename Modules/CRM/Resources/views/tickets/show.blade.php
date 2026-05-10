@extends('layouts.admin')

@section('page-title', 'تیکت #' . $ticket->id)

@section('main')
<div class="p-4 md:p-6 max-w-4xl mx-auto space-y-4">
    <div class="flex items-center justify-between gap-2">
        <a href="{{ route('crm.tickets.index') }}"
           class="text-sm text-brand-700 hover:underline">← همه تیکت‌ها</a>
        <div class="flex items-center gap-2">
            <span class="px-2 py-0.5 text-xs font-bold rounded-full {{ $ticket->priorityBadgeClass() }}">اولویت: {{ $ticket->priorityLabel() }}</span>
            <span class="px-2 py-0.5 text-xs font-bold rounded-full {{ $ticket->statusBadgeClass() }}">{{ $ticket->statusLabel() }}</span>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 rounded-lg p-3 text-sm text-rose-700">{{ session('error') }}</div>
    @endif

    {{-- متادیتا --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
        @if($ticket->subject)
            <h1 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-3">{{ $ticket->subject }}</h1>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mb-4">
            <div>
                <div class="text-xs text-gray-500">تکنسین</div>
                <div class="font-medium">{{ $ticket->technician?->firstname_tech ?: $ticket->technician?->first_name ?: '—' }}</div>
                <div class="text-xs text-gray-400" dir="ltr">{{ $ticket->technician?->mobile }}</div>
            </div>
            @if($ticket->order)
                <div>
                    <div class="text-xs text-gray-500">سفارش مرتبط</div>
                    <a href="{{ route('crm.orders.show', $ticket->order) }}"
                       class="text-brand-700 font-medium hover:underline" dir="ltr">{{ $ticket->order->order_code }}</a>
                    <div class="text-xs text-gray-500">{{ $ticket->order->customer_name }}</div>
                </div>
            @endif
            <div>
                <div class="text-xs text-gray-500">زمان ثبت</div>
                <div class="text-sm" dir="ltr">@jdatetime($ticket->created_at)</div>
            </div>
        </div>

        <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
            <div class="text-sm leading-7 whitespace-pre-wrap text-gray-800 dark:text-gray-200">{{ $ticket->body }}</div>
            @if($ticket->image_path)
                <a href="{{ asset('storage/' . $ticket->image_path) }}" target="_blank" class="block mt-3">
                    <img src="{{ asset('storage/' . $ticket->image_path) }}" alt="ضمیمه"
                         class="max-w-md rounded-lg border border-gray-200">
                </a>
            @endif
        </div>
    </div>

    {{-- پاسخ‌ها --}}
    @foreach($ticket->replies as $r)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border-r-4 {{ $r->sender_type === 'admin' ? 'border-brand-500' : 'border-emerald-500' }} p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold {{ $r->sender_type === 'admin' ? 'text-brand-700' : 'text-emerald-700' }}">
                    {{ $r->sender_type === 'admin' ? 'پشتیبانی' : 'تکنسین' }}
                </span>
                <span class="text-xs text-gray-400" dir="ltr">@jdatetime($r->created_at)</span>
            </div>
            <div class="text-sm leading-7 whitespace-pre-wrap">{{ $r->body }}</div>
            @if($r->image_path)
                <a href="{{ asset('storage/' . $r->image_path) }}" target="_blank" class="block mt-2">
                    <img src="{{ asset('storage/' . $r->image_path) }}" alt="ضمیمه" class="max-w-sm rounded-lg border border-gray-200">
                </a>
            @endif
        </div>
    @endforeach

    {{-- فرم پاسخ --}}
    @can('reply-crm-tickets')
        @if($ticket->status !== 'closed')
            <form method="POST" action="{{ route('crm.tickets.reply', $ticket) }}" enctype="multipart/form-data"
                  class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-3">
                @csrf
                <h3 class="text-sm font-bold">پاسخ به تکنسین</h3>
                <textarea name="body" rows="5" required maxlength="5000" placeholder="متن پاسخ…"
                          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm leading-7">{{ old('body') }}</textarea>
                <input type="file" name="image" accept="image/*" class="text-xs">
                <div class="flex items-center gap-2">
                    <button type="submit" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg text-sm font-bold">ارسال پاسخ</button>
                </div>
            </form>

            <form method="POST" action="{{ route('crm.tickets.status', $ticket) }}" class="text-left">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="closed">
                <button type="submit" onclick="return confirm('این تیکت بسته شود؟')"
                        class="px-3 py-1.5 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg">بستن تیکت</button>
            </form>
        @else
            <form method="POST" action="{{ route('crm.tickets.status', $ticket) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="open">
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">بازگشایی تیکت</button>
            </form>
        @endif
    @endcan
</div>
@endsection
