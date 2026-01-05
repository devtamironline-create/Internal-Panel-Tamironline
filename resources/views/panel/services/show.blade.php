@extends('layouts.panel')
@section('page-title', 'جزئیات سرویس')

@section('main')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('panel.services.index') }}" class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-gray-600 hover:bg-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $service->product->name ?? 'سرویس' }}</h1>
                <p class="text-gray-500">{{ $service->order_number }}</p>
            </div>
        </div>
        <span class="px-4 py-2 text-sm font-medium rounded-full
            @if($service->status === 'active') bg-green-100 text-green-700
            @elseif($service->status === 'pending') bg-yellow-100 text-yellow-700
            @elseif($service->status === 'suspended') bg-orange-100 text-orange-700
            @else bg-gray-100 text-gray-700
            @endif">
            {{ $service->status_label }}
        </span>
    </div>

    <!-- Service Details -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-bold text-gray-900 mb-4">اطلاعات سرویس</h3>
        <div class="grid md:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div class="flex justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-500">نام سرویس</span>
                    <span class="font-medium text-gray-900">{{ $service->product->name ?? '-' }}</span>
                </div>
                <div class="flex justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-500">شماره سفارش</span>
                    <span class="font-medium text-gray-900 ltr">{{ $service->order_number }}</span>
                </div>
                <div class="flex justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-500">دوره صورتحساب</span>
                    <span class="font-medium text-gray-900">
                        @php
                            $cycles = [
                                'monthly' => 'ماهانه',
                                'quarterly' => 'سه ماهه',
                                'semi_annually' => 'شش ماهه',
                                'annually' => 'سالانه',
                                'biennially' => 'دو ساله',
                                'one_time' => 'یکبار',
                            ];
                        @endphp
                        {{ $cycles[$service->billing_cycle] ?? $service->billing_cycle }}
                    </span>
                </div>
                <div class="flex justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-500">تمدید خودکار</span>
                    <span class="font-medium {{ $service->auto_renew ? 'text-green-600' : 'text-red-600' }}">
                        {{ $service->auto_renew ? 'فعال' : 'غیرفعال' }}
                    </span>
                </div>
            </div>
            <div class="space-y-4">
                <div class="flex justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-500">تاریخ شروع</span>
                    <span class="font-medium text-gray-900">
                        @if($service->start_date)
                            {{ \Morilog\Jalali\Jalalian::fromDateTime($service->start_date)->format('Y/m/d') }}
                        @else
                            -
                        @endif
                    </span>
                </div>
                <div class="flex justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-500">تاریخ سررسید</span>
                    <span class="font-medium text-gray-900">
                        @if($service->next_due_date)
                            {{ \Morilog\Jalali\Jalalian::fromDateTime($service->next_due_date)->format('Y/m/d') }}
                        @else
                            -
                        @endif
                    </span>
                </div>
                <div class="flex justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-500">قیمت</span>
                    <span class="font-medium text-gray-900">{{ number_format($service->price) }} تومان</span>
                </div>
                @if($service->setup_fee > 0)
                <div class="flex justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-500">هزینه راه‌اندازی</span>
                    <span class="font-medium text-gray-900">{{ number_format($service->setup_fee) }} تومان</span>
                </div>
                @endif
            </div>
        </div>

        @if($service->notes)
        <div class="mt-6 pt-6 border-t border-gray-100">
            <span class="text-gray-500">توضیحات</span>
            <p class="mt-2 text-gray-900">{{ $service->notes }}</p>
        </div>
        @endif
    </div>

    <!-- Related Invoices -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100">
            <h3 class="font-bold text-gray-900">فاکتورهای مرتبط</h3>
        </div>
        @if($service->invoices->count() > 0)
        <div class="divide-y divide-gray-100">
            @foreach($service->invoices as $invoice)
            <a href="{{ route('panel.invoices.show', $invoice) }}" class="flex items-center justify-between p-4 hover:bg-gray-50 transition-colors">
                <div>
                    <p class="font-medium text-gray-900">فاکتور #{{ $invoice->invoice_number }}</p>
                    <p class="text-sm text-gray-500">{{ \Morilog\Jalali\Jalalian::fromDateTime($invoice->invoice_date)->format('Y/m/d') }}</p>
                </div>
                <div class="flex items-center gap-4">
                    <span class="font-medium text-gray-900">{{ number_format($invoice->total_amount) }} تومان</span>
                    <span class="px-3 py-1 text-xs font-medium rounded-full
                        @if($invoice->status === 'paid') bg-green-100 text-green-700
                        @elseif($invoice->status === 'sent') bg-blue-100 text-blue-700
                        @elseif($invoice->status === 'overdue') bg-red-100 text-red-700
                        @else bg-gray-100 text-gray-700
                        @endif">
                        {{ $invoice->status_label }}
                    </span>
                </div>
            </a>
            @endforeach
        </div>
        @else
        <div class="p-8 text-center text-gray-500">
            فاکتوری برای این سرویس وجود ندارد
        </div>
        @endif
    </div>
</div>
@endsection
