@extends('layouts.admin')
@section('page-title', 'مشاهده تیکت')
@section('main')
<div class="max-w-5xl mx-auto">
    <!-- Ticket Header -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <h2 class="text-xl font-semibold text-gray-900">{{ $ticket->ticket_number }}</h2>
                        <span class="px-3 py-1 text-xs font-medium rounded-full
                            @if($ticket->status === 'open') bg-blue-100 text-blue-800
                            @elseif($ticket->status === 'pending') bg-yellow-100 text-yellow-800
                            @elseif($ticket->status === 'answered') bg-green-100 text-green-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            {{ $ticket->status_label }}
                        </span>
                        <span class="px-3 py-1 text-xs font-medium rounded-full
                            @if($ticket->priority === 'urgent') bg-red-100 text-red-800
                            @elseif($ticket->priority === 'high') bg-orange-100 text-orange-800
                            @elseif($ticket->priority === 'normal') bg-blue-100 text-blue-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            {{ $ticket->priority_label }}
                        </span>
                    </div>
                    <h3 class="text-lg text-gray-700">{{ $ticket->subject }}</h3>
                </div>
                <div class="flex gap-2">
                    @if($ticket->status !== 'closed')
                    <form action="{{ route('admin.tickets.close', $ticket) }}" method="POST" class="inline">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">بستن تیکت</button>
                    </form>
                    @else
                    <form action="{{ route('admin.tickets.reopen', $ticket) }}" method="POST" class="inline">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">باز کردن مجدد</button>
                    </form>
                    @endif
                </div>
            </div>
        </div>

        <!-- Ticket Info -->
        <div class="p-6 grid grid-cols-2 md:grid-cols-4 gap-6 text-sm">
            <div>
                <span class="text-gray-600">مشتری:</span>
                <p class="font-medium text-gray-900 mt-1">{{ $ticket->customer->full_name }}</p>
            </div>
            <div>
                <span class="text-gray-600">دپارتمان:</span>
                <p class="font-medium text-gray-900 mt-1">{{ $ticket->department_label }}</p>
            </div>
            <div>
                <span class="text-gray-600">تاریخ ایجاد:</span>
                <p class="font-medium text-gray-900 mt-1">{{ \Morilog\Jalali\Jalalian::fromDateTime($ticket->created_at)->format('Y/m/d H:i') }}</p>
            </div>
            <div>
                <span class="text-gray-600">تخصیص به:</span>
                <form action="{{ route('admin.tickets.update', $ticket) }}" method="POST" class="mt-1">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="status" value="{{ $ticket->status }}">
                    <input type="hidden" name="priority" value="{{ $ticket->priority }}">
                    <input type="hidden" name="department" value="{{ $ticket->department }}">
                    <select name="assigned_to" onchange="this.form.submit()" class="text-sm px-2 py-1 border border-gray-300 rounded">
                        <option value="">انتخاب...</option>
                        @foreach($staffUsers as $staff)
                        <option value="{{ $staff->id }}" {{ $ticket->assigned_to == $staff->id ? 'selected' : '' }}>
                            {{ $staff->full_name }}
                        </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </div>

    <!-- Initial Message -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-semibold">
                    {{ mb_substr($ticket->customer->first_name, 0, 1) }}
                </div>
                <div>
                    <p class="font-medium text-gray-900">{{ $ticket->customer->full_name }}</p>
                    <p class="text-xs text-gray-500">{{ \Morilog\Jalali\Jalalian::fromDateTime($ticket->created_at)->format('Y/m/d H:i') }}</p>
                </div>
            </div>
        </div>
        <div class="p-6">
            <p class="text-gray-700 whitespace-pre-line">{{ $ticket->description }}</p>
        </div>
    </div>

    <!-- Replies -->
    @foreach($ticket->replies as $reply)
    <div class="bg-white rounded-xl shadow-sm mb-4">
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full {{ $reply->is_staff ? 'bg-green-500' : 'bg-blue-500' }} flex items-center justify-center text-white font-semibold">
                    {{ mb_substr($reply->user->first_name ?? 'U', 0, 1) }}
                </div>
                <div>
                    <p class="font-medium text-gray-900">
                        {{ $reply->user->full_name ?? 'کاربر' }}
                        @if($reply->is_staff)
                        <span class="text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded mr-2">پشتیبان</span>
                        @endif
                    </p>
                    <p class="text-xs text-gray-500">{{ \Morilog\Jalali\Jalalian::fromDateTime($reply->created_at)->format('Y/m/d H:i') }}</p>
                </div>
            </div>
        </div>
        <div class="p-6 {{ $reply->is_staff ? 'bg-green-50' : '' }}">
            <p class="text-gray-700 whitespace-pre-line">{{ $reply->message }}</p>
        </div>
    </div>
    @endforeach

    <!-- Reply Form -->
    @if($ticket->status !== 'closed')
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h3 class="font-semibold text-gray-900">ارسال پاسخ</h3>
        </div>
        <form action="{{ route('admin.tickets.reply', $ticket) }}" method="POST" class="p-6">
            @csrf
            <div class="mb-4">
                <textarea name="message" rows="5" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="پاسخ خود را بنویسید..." required></textarea>
                @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="flex items-center gap-4">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">ارسال پاسخ</button>
                <a href="{{ route('admin.tickets.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">بازگشت</a>
            </div>
        </form>
    </div>
    @else
    <div class="bg-gray-100 rounded-xl p-6 text-center text-gray-600">
        این تیکت بسته شده است. برای ارسال پاسخ، ابتدا تیکت را باز کنید.
    </div>
    @endif
</div>
@endsection
