@extends('layouts.admin')
@section('page-title', 'مشاهده فاکتور')
@section('main')
<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">فاکتور {{ $invoice->invoice_number }}</h2>
                <p class="text-sm text-gray-600 mt-1">تاریخ صدور: {{ \Morilog\Jalali\Jalalian::fromDateTime($invoice->invoice_date)->format('Y/m/d') }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.invoices.download-pdf', $invoice) }}" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    <svg class="w-5 h-5 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    دانلود PDF
                </a>
                <a href="{{ route('admin.invoices.edit', $invoice) }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">ویرایش</a>
            </div>
        </div>

        <div class="p-6">
            <!-- Status -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700">وضعیت فاکتور:</span>
                <span class="px-3 py-1 text-sm font-medium rounded-full
                    @if($invoice->status === 'paid') bg-green-100 text-green-800
                    @elseif($invoice->status === 'sent') bg-blue-100 text-blue-800
                    @elseif($invoice->status === 'overdue') bg-red-100 text-red-800
                    @elseif($invoice->status === 'draft') bg-gray-100 text-gray-800
                    @endif">
                    {{ $invoice->status_label }}
                </span>
            </div>

            <!-- Customer Info -->
            <div class="mb-6">
                <h3 class="text-md font-semibold text-gray-900 mb-3">مشخصات مشتری</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">نام:</span>
                        <span class="font-medium text-gray-900 mr-2">{{ $invoice->client_name }}</span>
                    </div>
                    @if($invoice->client_mobile)
                    <div>
                        <span class="text-gray-600">موبایل:</span>
                        <span class="font-medium text-gray-900 mr-2">{{ $invoice->client_mobile }}</span>
                    </div>
                    @endif
                    @if($invoice->client_email)
                    <div>
                        <span class="text-gray-600">ایمیل:</span>
                        <span class="font-medium text-gray-900 mr-2">{{ $invoice->client_email }}</span>
                    </div>
                    @endif
                    @if($invoice->client_address)
                    <div class="col-span-2">
                        <span class="text-gray-600">آدرس:</span>
                        <span class="font-medium text-gray-900 mr-2">{{ $invoice->client_address }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Invoice Details -->
            <div class="mb-6">
                <h3 class="text-md font-semibold text-gray-900 mb-3">جزئیات فاکتور</h3>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">تاریخ سررسید:</span>
                        <span class="font-medium text-gray-900 mr-2">{{ \Morilog\Jalali\Jalalian::fromDateTime($invoice->due_date)->format('Y/m/d') }}</span>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="mb-6">
                <h3 class="text-md font-semibold text-gray-900 mb-3">آیتم‌ها</h3>
                <table class="w-full border border-gray-200 rounded-lg">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-sm font-medium text-gray-700">شرح</th>
                            <th class="px-4 py-3 text-center text-sm font-medium text-gray-700">تعداد</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">قیمت واحد</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-700">جمع</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->items as $item)
                        <tr class="border-t border-gray-200">
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $item->description }}</td>
                            <td class="px-4 py-3 text-sm text-center text-gray-900">{{ $item->quantity }}</td>
                            <td class="px-4 py-3 text-sm text-left text-gray-900">{{ number_format($item->unit_price) }} تومان</td>
                            <td class="px-4 py-3 text-sm text-left font-medium text-gray-900">{{ number_format($item->total) }} تومان</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="bg-blue-50 rounded-lg p-4">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-gray-700">جمع کل:</span>
                    <span class="text-sm font-medium text-gray-900">{{ number_format($invoice->subtotal ?? $invoice->total_amount) }} تومان</span>
                </div>
                @if($invoice->discount_amount > 0)
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-gray-700">تخفیف:</span>
                    <span class="text-sm font-medium text-red-600">{{ number_format($invoice->discount_amount) }} تومان</span>
                </div>
                @endif
                @if($invoice->tax_amount > 0)
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm text-gray-700">مالیات:</span>
                    <span class="text-sm font-medium text-gray-900">{{ number_format($invoice->tax_amount) }} تومان</span>
                </div>
                @endif
                <div class="flex justify-between items-center pt-3 border-t border-blue-200">
                    <span class="text-lg font-semibold text-gray-900">مبلغ قابل پرداخت:</span>
                    <span class="text-xl font-bold text-blue-600">{{ number_format($invoice->total_amount) }} تومان</span>
                </div>
            </div>

            @if($invoice->notes)
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <h4 class="text-sm font-semibold text-gray-900 mb-2">یادداشت‌ها:</h4>
                <p class="text-sm text-gray-700">{{ $invoice->notes }}</p>
            </div>
            @endif
        </div>

        <div class="p-6 border-t border-gray-100">
            <a href="{{ route('admin.invoices.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">بازگشت به لیست</a>
        </div>
    </div>
</div>
@endsection
