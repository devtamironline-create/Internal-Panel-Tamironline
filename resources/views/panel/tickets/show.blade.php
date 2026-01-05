@extends('layouts.panel')
@section('page-title', $ticket->subject)

@section('main')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('panel.tickets.index') }}" class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-gray-600 hover:bg-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">{{ $ticket->subject }}</h1>
                <p class="text-gray-500">{{ $ticket->ticket_number }} - {{ $ticket->department_label }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1 text-sm font-medium rounded-full
                @if($ticket->priority === 'urgent') bg-red-100 text-red-700
                @elseif($ticket->priority === 'high') bg-orange-100 text-orange-700
                @else bg-gray-100 text-gray-700
                @endif">
                {{ $ticket->priority_label }}
            </span>
            <span class="px-3 py-1 text-sm font-medium rounded-full
                @if($ticket->status === 'open') bg-blue-100 text-blue-700
                @elseif($ticket->status === 'answered') bg-green-100 text-green-700
                @elseif($ticket->status === 'pending') bg-yellow-100 text-yellow-700
                @else bg-gray-100 text-gray-700
                @endif">
                {{ $ticket->status_label }}
            </span>
            @if($ticket->status !== 'closed')
            <form action="{{ route('panel.tickets.close', $ticket) }}" method="POST" class="inline">
                @csrf
                @method('PATCH')
                <button type="submit" onclick="return confirm('آیا مطمئن هستید که می‌خواهید این تیکت را ببندید؟')"
                    class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-xl transition-colors">
                    بستن تیکت
                </button>
            </form>
            @endif
        </div>
    </div>

    <!-- Original Message -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100 bg-gray-50">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-brand-500 flex items-center justify-center text-white font-bold">
                        {{ mb_substr(auth()->user()->first_name, 0, 1) }}
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">{{ auth()->user()->full_name }}</p>
                        <p class="text-sm text-gray-500">{{ \Morilog\Jalali\Jalalian::fromDateTime($ticket->created_at)->format('Y/m/d H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-5">
            <div class="prose max-w-none text-gray-700">
                {!! nl2br(e($ticket->description)) !!}
            </div>
        </div>
    </div>

    <!-- Replies -->
    @foreach($ticket->replies as $reply)
    <div class="bg-white rounded-2xl shadow-sm border overflow-hidden {{ $reply->is_staff_reply ? 'border-brand-200' : 'border-gray-100' }}">
        <div class="p-5 border-b {{ $reply->is_staff_reply ? 'bg-brand-50 border-brand-100' : 'bg-gray-50 border-gray-100' }}">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold {{ $reply->is_staff_reply ? 'bg-brand-600' : 'bg-gray-500' }}">
                        @if($reply->is_staff_reply)
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        @else
                            {{ mb_substr($reply->user->first_name ?? 'ک', 0, 1) }}
                        @endif
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">
                            @if($reply->is_staff_reply)
                                {{ $reply->user->full_name ?? 'پشتیبانی' }}
                                <span class="text-xs bg-brand-100 text-brand-700 px-2 py-0.5 rounded-full mr-2">پشتیبان</span>
                            @else
                                {{ $reply->user->full_name ?? 'شما' }}
                            @endif
                        </p>
                        <p class="text-sm text-gray-500">{{ \Morilog\Jalali\Jalalian::fromDateTime($reply->created_at)->format('Y/m/d H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-5">
            <div class="prose max-w-none text-gray-700">
                {!! nl2br(e($reply->message)) !!}
            </div>
        </div>
    </div>
    @endforeach

    <!-- Reply Form -->
    @if($ticket->status !== 'closed')
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-bold text-gray-900 mb-4">ارسال پاسخ</h3>
        <form action="{{ route('panel.tickets.reply', $ticket) }}" method="POST">
            @csrf
            <div class="mb-4">
                <textarea name="message" rows="4" required
                    placeholder="پاسخ خود را بنویسید..."
                    class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent transition-all @error('message') border-red-500 @enderror">{{ old('message') }}</textarea>
                @error('message')
                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="px-6 py-3 bg-brand-500 text-white font-medium rounded-xl hover:bg-brand-600 transition-colors">
                ارسال پاسخ
            </button>
        </form>
    </div>
    @else
    <div class="bg-gray-100 rounded-2xl p-6 text-center">
        <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        <p class="text-gray-600">این تیکت بسته شده است و امکان ارسال پاسخ وجود ندارد.</p>
    </div>
    @endif
</div>
@endsection
