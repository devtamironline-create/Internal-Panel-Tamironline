@extends('layouts.panel')
@section('page-title', 'جزئیات فاکتور')

@section('main')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('panel.invoices.index') }}" class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-gray-600 hover:bg-gray-200 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">فاکتور #{{ $invoice->invoice_number }}</h1>
                <p class="text-gray-500">{{ \Morilog\Jalali\Jalalian::fromDateTime($invoice->invoice_date)->format('Y/m/d') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-4 py-2 text-sm font-medium rounded-full
                @if($invoice->status === 'paid') bg-green-100 text-green-700
                @elseif($invoice->status === 'sent') bg-blue-100 text-blue-700
                @elseif($invoice->status === 'overdue') bg-red-100 text-red-700
                @else bg-gray-100 text-gray-700
                @endif">
                {{ $invoice->status_label }}
            </span>
            <a href="{{ route('panel.invoices.pdf', $invoice) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-brand-500 text-white rounded-xl hover:bg-brand-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                دانلود PDF
            </a>
        </div>
    </div>

    <!-- Invoice Info -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="grid md:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div class="flex justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-500">شماره فاکتور</span>
                    <span class="font-medium text-gray-900">{{ $invoice->invoice_number }}</span>
                </div>
                <div class="flex justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-500">تاریخ صدور</span>
                    <span class="font-medium text-gray-900">{{ \Morilog\Jalali\Jalalian::fromDateTime($invoice->invoice_date)->format('Y/m/d') }}</span>
                </div>
                <div class="flex justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-500">تاریخ سررسید</span>
                    <span class="font-medium text-gray-900">{{ \Morilog\Jalali\Jalalian::fromDateTime($invoice->due_date)->format('Y/m/d') }}</span>
                </div>
            </div>
            <div class="space-y-4">
                @if($invoice->service)
                <div class="flex justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-500">سرویس مرتبط</span>
                    <a href="{{ route('panel.services.show', $invoice->service) }}" class="font-medium text-brand-600 hover:text-brand-700">
                        {{ $invoice->service->product->name ?? $invoice->service->order_number }}
                    </a>
                </div>
                @endif
                @if($invoice->paid_at)
                <div class="flex justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-500">تاریخ پرداخت</span>
                    <span class="font-medium text-green-600">{{ \Morilog\Jalali\Jalalian::fromDateTime($invoice->paid_at)->format('Y/m/d') }}</span>
                </div>
                @endif
                @if($invoice->payment_method)
                <div class="flex justify-between py-3 border-b border-gray-100">
                    <span class="text-gray-500">روش پرداخت</span>
                    <span class="font-medium text-gray-900">{{ $invoice->payment_method }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Invoice Items -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100">
            <h3 class="font-bold text-gray-900">اقلام فاکتور</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-right text-sm font-medium text-gray-500">شرح</th>
                        <th class="px-5 py-3 text-center text-sm font-medium text-gray-500">تعداد</th>
                        <th class="px-5 py-3 text-left text-sm font-medium text-gray-500">قیمت واحد</th>
                        <th class="px-5 py-3 text-left text-sm font-medium text-gray-500">جمع</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($invoice->items as $item)
                    <tr>
                        <td class="px-5 py-4 text-gray-900">{{ $item->description }}</td>
                        <td class="px-5 py-4 text-center text-gray-600">{{ number_format($item->quantity) }}</td>
                        <td class="px-5 py-4 text-left text-gray-600">{{ number_format($item->unit_price) }} تومان</td>
                        <td class="px-5 py-4 text-left font-medium text-gray-900">{{ number_format($item->total) }} تومان</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="border-t border-gray-100 p-5 bg-gray-50">
            <div class="max-w-xs mr-auto space-y-2">
                @if($invoice->discount_amount > 0)
                <div class="flex justify-between">
                    <span class="text-gray-500">تخفیف</span>
                    <span class="text-green-600">{{ number_format($invoice->discount_amount) }}- تومان</span>
                </div>
                @endif
                @if($invoice->tax_amount > 0)
                <div class="flex justify-between">
                    <span class="text-gray-500">مالیات</span>
                    <span class="text-gray-900">{{ number_format($invoice->tax_amount) }} تومان</span>
                </div>
                @endif
                <div class="flex justify-between pt-2 border-t border-gray-200">
                    <span class="font-bold text-gray-900">مبلغ قابل پرداخت</span>
                    <span class="font-bold text-gray-900 text-lg">{{ number_format($invoice->total_amount) }} تومان</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Button -->
    @if(in_array($invoice->status, ['draft', 'sent', 'overdue']))
    <div class="bg-gradient-to-br from-brand-500 to-brand-600 rounded-2xl p-6 text-center">
        <h3 class="text-white font-bold text-lg mb-2">پرداخت آنلاین</h3>
        <p class="text-brand-100 mb-4">برای پرداخت این فاکتور روی دکمه زیر کلیک کنید</p>
        <button class="px-8 py-3 bg-white text-brand-600 font-bold rounded-xl hover:bg-brand-50 transition-colors">
            پرداخت {{ number_format($invoice->total_amount) }} تومان
        </button>
        <p class="text-brand-200 text-sm mt-3">درگاه پرداخت به زودی فعال می‌شود</p>
    </div>
    @endif

    @if($invoice->notes)
    <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-5">
        <h4 class="font-bold text-yellow-800 mb-2">توضیحات</h4>
        <p class="text-yellow-700">{{ $invoice->notes }}</p>
    </div>
    @endif
</div>
@endsection
