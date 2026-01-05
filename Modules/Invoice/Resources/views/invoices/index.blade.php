@extends('layouts.admin')
@section('page-title', 'فاکتورها')
@section('main')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">مدیریت فاکتورها</h1>
        <p class="mt-1 text-sm text-gray-600">لیست تمام فاکتورهای صادر شده</p>
    </div>
    <a href="{{ route('admin.invoices.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
        <span class="text-lg ml-1">+</span> صدور فاکتور جدید
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm">
    <div class="p-6 border-b border-gray-100">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="جستجو (شماره فاکتور، مشتری)..." class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>پیش‌نویس</option>
                    <option value="sent" {{ request('status') == 'sent' ? 'selected' : '' }}>ارسال شده</option>
                    <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>پرداخت شده</option>
                    <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>سررسید گذشته</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>لغو شده</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900">فیلتر</button>
                @if(request()->hasAny(['search', 'status']))
                <a href="{{ route('admin.invoices.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">پاک کردن</a>
                @endif
            </div>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">شماره فاکتور</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مشتری</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاریخ صدور</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">سررسید</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">مبلغ کل</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($invoices as $invoice)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $invoice->invoice_number }}</td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <a href="{{ route('admin.customers.show', $invoice->customer_id) }}" class="text-blue-600 hover:text-blue-800">
                            {{ $invoice->customer->full_name }}
                        </a>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ \Morilog\Jalali\Jalalian::fromDateTime($invoice->invoice_date)->format('Y/m/d') }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ \Morilog\Jalali\Jalalian::fromDateTime($invoice->due_date)->format('Y/m/d') }}</td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ number_format($invoice->total_amount) }} تومان</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs font-medium rounded-full
                            @if($invoice->status === 'paid') bg-green-100 text-green-800
                            @elseif($invoice->status === 'sent') bg-blue-100 text-blue-800
                            @elseif($invoice->status === 'overdue') bg-red-100 text-red-800
                            @elseif($invoice->status === 'draft') bg-gray-100 text-gray-800
                            @elseif($invoice->status === 'cancelled') bg-gray-100 text-gray-600
                            @endif">
                            {{ $invoice->status_label }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.invoices.show', $invoice) }}" class="text-blue-600 hover:text-blue-800">مشاهده</a>
                            <a href="{{ route('admin.invoices.download-pdf', $invoice) }}" class="text-green-600 hover:text-green-800">PDF</a>
                            <a href="{{ route('admin.invoices.edit', $invoice) }}" class="text-yellow-600 hover:text-yellow-800">ویرایش</a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                            <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path>
                            </svg>
                            <p class="text-lg font-medium">هیچ فاکتوری یافت نشد</p>
                            <p class="mt-1 text-sm">برای شروع، اولین فاکتور را صادر کنید</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($invoices->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $invoices->links() }}
    </div>
    @endif
</div>
@endsection
