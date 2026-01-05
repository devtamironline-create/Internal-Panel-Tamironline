@extends('layouts.panel')
@section('page-title', 'تیکت‌های پشتیبانی')

@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">تیکت‌های پشتیبانی</h1>
        <a href="{{ route('panel.tickets.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-500 text-white rounded-xl hover:bg-brand-600 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            تیکت جدید
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
        <form method="GET" class="flex flex-wrap items-center gap-4">
            <select name="status" class="px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                <option value="">همه وضعیت‌ها</option>
                <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>باز</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>در انتظار</option>
                <option value="answered" {{ request('status') === 'answered' ? 'selected' : '' }}>پاسخ داده شده</option>
                <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>بسته شده</option>
            </select>
            <select name="department" class="px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                <option value="">همه بخش‌ها</option>
                <option value="support" {{ request('department') === 'support' ? 'selected' : '' }}>پشتیبانی</option>
                <option value="technical" {{ request('department') === 'technical' ? 'selected' : '' }}>فنی</option>
                <option value="billing" {{ request('department') === 'billing' ? 'selected' : '' }}>مالی</option>
                <option value="sales" {{ request('department') === 'sales' ? 'selected' : '' }}>فروش</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-brand-500 text-white rounded-xl hover:bg-brand-600 transition-colors">
                فیلتر
            </button>
            @if(request()->hasAny(['status', 'department']))
            <a href="{{ route('panel.tickets.index') }}" class="text-gray-500 hover:text-gray-700">پاک کردن فیلتر</a>
            @endif
        </form>
    </div>

    <!-- Tickets List -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        @if($tickets->count() > 0)
        <div class="divide-y divide-gray-100">
            @foreach($tickets as $ticket)
            <a href="{{ route('panel.tickets.show', $ticket) }}" class="flex items-center justify-between p-5 hover:bg-gray-50 transition-colors">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center
                        @if($ticket->status === 'answered') bg-green-100
                        @elseif($ticket->status === 'open') bg-blue-100
                        @elseif($ticket->status === 'pending') bg-yellow-100
                        @else bg-gray-100
                        @endif">
                        <svg class="w-6 h-6 @if($ticket->status === 'answered') text-green-600 @elseif($ticket->status === 'open') text-blue-600 @elseif($ticket->status === 'pending') text-yellow-600 @else text-gray-600 @endif" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ $ticket->subject }}</h3>
                        <p class="text-sm text-gray-500">{{ $ticket->ticket_number }} - {{ $ticket->department_label }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-left hidden md:block">
                        <p class="text-sm text-gray-500">آخرین بروزرسانی</p>
                        <p class="font-medium text-gray-900">
                            {{ \Morilog\Jalali\Jalalian::fromDateTime($ticket->updated_at)->format('Y/m/d H:i') }}
                        </p>
                    </div>
                    <span class="px-2 py-1 text-xs font-medium rounded-full
                        @if($ticket->priority === 'urgent') bg-red-100 text-red-700
                        @elseif($ticket->priority === 'high') bg-orange-100 text-orange-700
                        @else bg-gray-100 text-gray-700
                        @endif">
                        {{ $ticket->priority_label }}
                    </span>
                    <span class="px-3 py-1.5 text-sm font-medium rounded-full
                        @if($ticket->status === 'open') bg-blue-100 text-blue-700
                        @elseif($ticket->status === 'answered') bg-green-100 text-green-700
                        @elseif($ticket->status === 'pending') bg-yellow-100 text-yellow-700
                        @else bg-gray-100 text-gray-700
                        @endif">
                        {{ $ticket->status_label }}
                    </span>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </div>
            </a>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $tickets->links() }}
        </div>
        @else
        <div class="p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">هنوز تیکتی ثبت نکرده‌اید</h3>
            <p class="text-gray-500 mb-4">برای ارتباط با پشتیبانی یک تیکت جدید ایجاد کنید.</p>
            <a href="{{ route('panel.tickets.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-500 text-white rounded-xl hover:bg-brand-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                ایجاد تیکت جدید
            </a>
        </div>
        @endif
    </div>
</div>
@endsection
