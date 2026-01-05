@extends('layouts.panel')
@section('page-title', 'فاکتورها')

@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">فاکتورهای من</h1>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
        <form method="GET" class="flex flex-wrap items-center gap-4">
            <select name="status" class="px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-transparent">
                <option value="">همه وضعیت‌ها</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>پیش‌نویس</option>
                <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>ارسال شده</option>
                <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>پرداخت شده</option>
                <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>سررسید گذشته</option>
                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>لغو شده</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-brand-500 text-white rounded-xl hover:bg-brand-600 transition-colors">
                فیلتر
            </button>
            @if(request()->hasAny(['status']))
            <a href="{{ route('panel.invoices.index') }}" class="text-gray-500 hover:text-gray-700">پاک کردن فیلتر</a>
            @endif
        </form>
    </div>

    <!-- Invoices List -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        @if($invoices->count() > 0)
        <div class="divide-y divide-gray-100">
            @foreach($invoices as $invoice)
            <a href="{{ route('panel.invoices.show', $invoice) }}" class="flex items-center justify-between p-5 hover:bg-gray-50 transition-colors">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center
                        @if($invoice->status === 'paid') bg-green-100
                        @elseif($invoice->status === 'overdue') bg-red-100
                        @else bg-gray-100
                        @endif">
                        <svg class="w-6 h-6 @if($invoice->status === 'paid') text-green-600 @elseif($invoice->status === 'overdue') text-red-600 @else text-gray-600 @endif" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">فاکتور #{{ $invoice->invoice_number }}</h3>
                        <p class="text-sm text-gray-500">{{ \Morilog\Jalali\Jalalian::fromDateTime($invoice->invoice_date)->format('Y/m/d') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-6">
                    <div class="text-left hidden md:block">
                        <p class="text-sm text-gray-500">سررسید</p>
                        <p class="font-medium text-gray-900">
                            {{ \Morilog\Jalali\Jalalian::fromDateTime($invoice->due_date)->format('Y/m/d') }}
                        </p>
                    </div>
                    <div class="text-left">
                        <p class="text-sm text-gray-500 hidden md:block">مبلغ</p>
                        <p class="font-bold text-gray-900">{{ number_format($invoice->total_amount) }} تومان</p>
                    </div>
                    <span class="px-3 py-1.5 text-sm font-medium rounded-full
                        @if($invoice->status === 'paid') bg-green-100 text-green-700
                        @elseif($invoice->status === 'sent') bg-blue-100 text-blue-700
                        @elseif($invoice->status === 'overdue') bg-red-100 text-red-700
                        @elseif($invoice->status === 'draft') bg-gray-100 text-gray-700
                        @else bg-gray-100 text-gray-700
                        @endif">
                        {{ $invoice->status_label }}
                    </span>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </div>
            </a>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $invoices->links() }}
        </div>
        @else
        <div class="p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">هنوز فاکتوری ندارید</h3>
            <p class="text-gray-500">فاکتورهای شما پس از ثبت سفارش در اینجا نمایش داده می‌شوند.</p>
        </div>
        @endif
    </div>
</div>
@endsection
