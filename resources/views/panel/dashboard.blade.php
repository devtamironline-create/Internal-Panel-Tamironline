@extends('layouts.panel')
@section('page-title', 'داشبورد')

@section('main')
<div class="space-y-6">
    <!-- Welcome -->
    <div class="bg-gradient-to-br from-brand-500 to-brand-600 rounded-2xl p-6 text-white">
        <h2 class="text-2xl font-bold mb-2">سلام {{ $customer->first_name }} عزیز</h2>
        <p class="text-brand-100">به پنل کاربری هاستلینو خوش آمدید</p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Active Services -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">سرویس‌های فعال</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['active_services']) }}</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                </div>
            </div>
        </div>

        <!-- Pending Services -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">در انتظار فعالسازی</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['pending_services']) }}</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-yellow-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>

        <!-- Open Tickets -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">تیکت‌های باز</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['open_tickets']) }}</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                </div>
            </div>
        </div>

        <!-- Unpaid Invoices -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">فاکتور پرداخت نشده</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($stats['unpaid_invoices']) }}</p>
                    @if($stats['unpaid_amount'] > 0)
                    <p class="text-xs text-red-500 mt-1">{{ number_format($stats['unpaid_amount']) }} تومان</p>
                    @endif
                </div>
                <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="{{ route('panel.tickets.create') }}" class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 hover:border-brand-300 hover:shadow-md transition-all group">
            <div class="w-12 h-12 rounded-xl bg-brand-100 flex items-center justify-center mb-3 group-hover:bg-brand-500 transition-colors">
                <svg class="w-6 h-6 text-brand-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </div>
            <p class="font-semibold text-gray-900">ثبت تیکت جدید</p>
            <p class="text-sm text-gray-500 mt-1">ارسال درخواست پشتیبانی</p>
        </a>

        <a href="{{ route('panel.services.index') }}" class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 hover:border-brand-300 hover:shadow-md transition-all group">
            <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center mb-3 group-hover:bg-green-500 transition-colors">
                <svg class="w-6 h-6 text-green-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
            </div>
            <p class="font-semibold text-gray-900">سرویس‌های من</p>
            <p class="text-sm text-gray-500 mt-1">مشاهده و مدیریت سرویس‌ها</p>
        </a>

        <a href="{{ route('panel.invoices.index') }}" class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 hover:border-brand-300 hover:shadow-md transition-all group">
            <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center mb-3 group-hover:bg-purple-500 transition-colors">
                <svg class="w-6 h-6 text-purple-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
            </div>
            <p class="font-semibold text-gray-900">فاکتورها</p>
            <p class="text-sm text-gray-500 mt-1">مشاهده و پرداخت فاکتورها</p>
        </a>

        <a href="{{ route('panel.profile') }}" class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 hover:border-brand-300 hover:shadow-md transition-all group">
            <div class="w-12 h-12 rounded-xl bg-orange-100 flex items-center justify-center mb-3 group-hover:bg-orange-500 transition-colors">
                <svg class="w-6 h-6 text-orange-600 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <p class="font-semibold text-gray-900">پروفایل</p>
            <p class="text-sm text-gray-500 mt-1">ویرایش اطلاعات کاربری</p>
        </a>
    </div>

    <!-- Recent Content -->
    <div class="grid lg:grid-cols-2 gap-6">
        <!-- Recent Services -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="font-bold text-gray-900">آخرین سرویس‌ها</h3>
                <a href="{{ route('panel.services.index') }}" class="text-sm text-brand-600 hover:text-brand-700">مشاهده همه</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($recentServices as $service)
                <a href="{{ route('panel.services.show', $service) }}" class="flex items-center justify-between p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">{{ $service->product->name ?? 'سرویس' }}</p>
                            <p class="text-sm text-gray-500">{{ $service->order_number }}</p>
                        </div>
                    </div>
                    <span class="px-3 py-1 text-xs font-medium rounded-full
                        @if($service->status === 'active') bg-green-100 text-green-700
                        @elseif($service->status === 'pending') bg-yellow-100 text-yellow-700
                        @elseif($service->status === 'suspended') bg-orange-100 text-orange-700
                        @else bg-gray-100 text-gray-700
                        @endif">
                        {{ $service->status_label }}
                    </span>
                </a>
                @empty
                <div class="p-8 text-center text-gray-500">
                    <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/></svg>
                    هنوز سرویسی ندارید
                </div>
                @endforelse
            </div>
        </div>

        <!-- Recent Invoices -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
            <div class="flex items-center justify-between p-5 border-b border-gray-100">
                <h3 class="font-bold text-gray-900">آخرین فاکتورها</h3>
                <a href="{{ route('panel.invoices.index') }}" class="text-sm text-brand-600 hover:text-brand-700">مشاهده همه</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($recentInvoices as $invoice)
                <a href="{{ route('panel.invoices.show', $invoice) }}" class="flex items-center justify-between p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900">فاکتور #{{ $invoice->invoice_number }}</p>
                            <p class="text-sm text-gray-500">{{ number_format($invoice->total_amount) }} تومان</p>
                        </div>
                    </div>
                    <span class="px-3 py-1 text-xs font-medium rounded-full
                        @if($invoice->status === 'paid') bg-green-100 text-green-700
                        @elseif($invoice->status === 'sent') bg-blue-100 text-blue-700
                        @elseif($invoice->status === 'overdue') bg-red-100 text-red-700
                        @else bg-gray-100 text-gray-700
                        @endif">
                        {{ $invoice->status_label }}
                    </span>
                </a>
                @empty
                <div class="p-8 text-center text-gray-500">
                    <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                    هنوز فاکتوری ندارید
                </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Recent Tickets -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
            <h3 class="font-bold text-gray-900">آخرین تیکت‌ها</h3>
            <a href="{{ route('panel.tickets.index') }}" class="text-sm text-brand-600 hover:text-brand-700">مشاهده همه</a>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($recentTickets as $ticket)
            <a href="{{ route('panel.tickets.show', $ticket) }}" class="flex items-center justify-between p-4 hover:bg-gray-50 transition-colors">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">{{ $ticket->subject }}</p>
                        <p class="text-sm text-gray-500">{{ $ticket->ticket_number }} - {{ $ticket->department_label }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-2 py-1 text-xs font-medium rounded-full
                        @if($ticket->priority === 'urgent') bg-red-100 text-red-700
                        @elseif($ticket->priority === 'high') bg-orange-100 text-orange-700
                        @else bg-gray-100 text-gray-700
                        @endif">
                        {{ $ticket->priority_label }}
                    </span>
                    <span class="px-3 py-1 text-xs font-medium rounded-full
                        @if($ticket->status === 'open') bg-blue-100 text-blue-700
                        @elseif($ticket->status === 'answered') bg-green-100 text-green-700
                        @elseif($ticket->status === 'pending') bg-yellow-100 text-yellow-700
                        @else bg-gray-100 text-gray-700
                        @endif">
                        {{ $ticket->status_label }}
                    </span>
                </div>
            </a>
            @empty
            <div class="p-8 text-center text-gray-500">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                هنوز تیکتی ثبت نکرده‌اید
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
