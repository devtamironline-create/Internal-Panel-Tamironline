@extends('layouts.admin')
@section('page-title', 'ویرایش فاکتور')
@section('main')
<div class="max-w-5xl">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">ویرایش فاکتور {{ $invoice->invoice_number }}</h2>
                    <p class="text-sm text-gray-600 mt-1">آخرین بروزرسانی: {{ \Morilog\Jalali\Jalalian::fromDateTime($invoice->updated_at)->format('Y/m/d H:i') }}</p>
                </div>
                <span class="px-3 py-1 text-sm font-medium rounded-full
                    @if($invoice->status === 'paid') bg-green-100 text-green-800
                    @elseif($invoice->status === 'sent') bg-blue-100 text-blue-800
                    @elseif($invoice->status === 'overdue') bg-red-100 text-red-800
                    @elseif($invoice->status === 'cancelled') bg-gray-100 text-gray-800
                    @else bg-yellow-100 text-yellow-800
                    @endif">
                    {{ $invoice->status_label }}
                </span>
            </div>
        </div>
        <form action="{{ route('admin.invoices.update', $invoice) }}" method="POST" class="p-6 space-y-6" id="invoiceForm">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">مشتری *</label>
                    <select name="customer_id" id="customer_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('customer_id') border-red-500 @enderror" required>
                        <option value="">انتخاب مشتری...</option>
                        @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" {{ (old('customer_id', $invoice->customer_id) == $customer->id) ? 'selected' : '' }}>
                            {{ $customer->full_name }} ({{ $customer->mobile }})
                        </option>
                        @endforeach
                    </select>
                    @error('customer_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">سرویس مرتبط (اختیاری)</label>
                    <select name="service_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">بدون سرویس...</option>
                        @foreach($services as $service)
                        <option value="{{ $service->id }}" {{ (old('service_id', $invoice->service_id) == $service->id) ? 'selected' : '' }}>
                            {{ $service->order_number }} - {{ $service->product->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تاریخ صدور *</label>
                    <input type="text" name="invoice_date" value="{{ old('invoice_date', \Morilog\Jalali\Jalalian::fromDateTime($invoice->invoice_date)->format('Y/m/d')) }}"
                        class="jalali-datepicker w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('invoice_date') border-red-500 @enderror" required>
                    @error('invoice_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تاریخ سررسید *</label>
                    <input type="text" name="due_date" value="{{ old('due_date', \Morilog\Jalali\Jalalian::fromDateTime($invoice->due_date)->format('Y/m/d')) }}"
                        class="jalali-datepicker w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('due_date') border-red-500 @enderror" required>
                    @error('due_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">وضعیت *</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('status') border-red-500 @enderror" required>
                        <option value="draft" {{ old('status', $invoice->status) == 'draft' ? 'selected' : '' }}>پیش‌نویس</option>
                        <option value="sent" {{ old('status', $invoice->status) == 'sent' ? 'selected' : '' }}>ارسال شده</option>
                        <option value="paid" {{ old('status', $invoice->status) == 'paid' ? 'selected' : '' }}>پرداخت شده</option>
                        <option value="overdue" {{ old('status', $invoice->status) == 'overdue' ? 'selected' : '' }}>سررسید گذشته</option>
                        <option value="cancelled" {{ old('status', $invoice->status) == 'cancelled' ? 'selected' : '' }}>لغو شده</option>
                    </select>
                    @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="border-t pt-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-md font-semibold text-gray-900">آیتم‌های فاکتور</h3>
                    <button type="button" onclick="addItem()" class="px-3 py-1 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">+ افزودن آیتم</button>
                </div>

                <div id="items-container" class="space-y-4">
                    @foreach($invoice->items as $index => $item)
                    <div class="item-row grid grid-cols-12 gap-3 p-4 bg-gray-50 rounded-lg">
                        <div class="col-span-5">
                            <label class="block text-xs font-medium text-gray-700 mb-1">شرح *</label>
                            <input type="text" name="items[{{ $index }}][description]" value="{{ old('items.'.$index.'.description', $item->description) }}" placeholder="شرح خدمات یا محصول" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg" required>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">تعداد *</label>
                            <input type="number" name="items[{{ $index }}][quantity]" value="{{ old('items.'.$index.'.quantity', $item->quantity) }}" min="1" class="item-quantity w-full px-3 py-2 text-sm border border-gray-300 rounded-lg" required>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">قیمت واحد *</label>
                            <input type="number" name="items[{{ $index }}][unit_price]" value="{{ old('items.'.$index.'.unit_price', $item->unit_price) }}" min="0" step="1000" class="item-price w-full px-3 py-2 text-sm border border-gray-300 rounded-lg" required>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">جمع</label>
                            <input type="text" class="item-total w-full px-3 py-2 text-sm border border-gray-200 bg-gray-100 rounded-lg" readonly value="{{ number_format($item->total) }}">
                        </div>
                        <div class="col-span-1 flex items-end">
                            <button type="button" onclick="removeItem(this)" class="w-full px-2 py-2 text-red-600 hover:bg-red-50 rounded-lg text-sm">حذف</button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="border-t pt-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">مالیات (تومان)</label>
                        <input type="number" name="tax_amount" id="tax_amount" value="{{ old('tax_amount', $invoice->tax_amount) }}" min="0" step="1000" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">تخفیف (تومان)</label>
                        <input type="number" name="discount_amount" id="discount_amount" value="{{ old('discount_amount', $invoice->discount_amount) }}" min="0" step="1000" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">جمع کل</label>
                        <input type="text" id="total_display" class="w-full px-4 py-2 border border-gray-200 bg-gray-100 rounded-lg font-bold" readonly value="{{ number_format($invoice->total) }} تومان">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">یادداشت‌ها</label>
                <textarea name="notes" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="یادداشت‌های داخلی...">{{ old('notes', $invoice->notes) }}</textarea>
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">بروزرسانی فاکتور</button>
                <a href="{{ route('admin.invoices.show', $invoice) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">انصراف</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
let itemCounter = {{ count($invoice->items) }};

function addItem() {
    const container = document.getElementById('items-container');
    const newItem = document.createElement('div');
    newItem.className = 'item-row grid grid-cols-12 gap-3 p-4 bg-gray-50 rounded-lg';
    newItem.innerHTML = `
        <div class="col-span-5">
            <label class="block text-xs font-medium text-gray-700 mb-1">شرح *</label>
            <input type="text" name="items[${itemCounter}][description]" placeholder="شرح خدمات یا محصول" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg" required>
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-medium text-gray-700 mb-1">تعداد *</label>
            <input type="number" name="items[${itemCounter}][quantity]" value="1" min="1" class="item-quantity w-full px-3 py-2 text-sm border border-gray-300 rounded-lg" required>
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-medium text-gray-700 mb-1">قیمت واحد *</label>
            <input type="number" name="items[${itemCounter}][unit_price]" value="0" min="0" step="1000" class="item-price w-full px-3 py-2 text-sm border border-gray-300 rounded-lg" required>
        </div>
        <div class="col-span-2">
            <label class="block text-xs font-medium text-gray-700 mb-1">جمع</label>
            <input type="text" class="item-total w-full px-3 py-2 text-sm border border-gray-200 bg-gray-100 rounded-lg" readonly value="0">
        </div>
        <div class="col-span-1 flex items-end">
            <button type="button" onclick="removeItem(this)" class="w-full px-2 py-2 text-red-600 hover:bg-red-50 rounded-lg text-sm">حذف</button>
        </div>
    `;
    container.appendChild(newItem);
    itemCounter++;
    attachItemListeners(newItem);
}

function removeItem(button) {
    const itemRow = button.closest('.item-row');
    if (document.querySelectorAll('.item-row').length > 1) {
        itemRow.remove();
        calculateTotal();
    } else {
        alert('حداقل یک آیتم باید وجود داشته باشد');
    }
}

function attachItemListeners(row) {
    const quantity = row.querySelector('.item-quantity');
    const price = row.querySelector('.item-price');

    quantity.addEventListener('input', () => calculateItemTotal(row));
    price.addEventListener('input', () => calculateItemTotal(row));
}

function calculateItemTotal(row) {
    const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
    const price = parseFloat(row.querySelector('.item-price').value) || 0;
    const total = quantity * price;
    row.querySelector('.item-total').value = total.toLocaleString('fa-IR');
    calculateTotal();
}

function calculateTotal() {
    let subtotal = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        subtotal += quantity * price;
    });

    const tax = parseFloat(document.getElementById('tax_amount').value) || 0;
    const discount = parseFloat(document.getElementById('discount_amount').value) || 0;
    const total = subtotal + tax - discount;

    document.getElementById('total_display').value = total.toLocaleString('fa-IR') + ' تومان';
}

// Attach listeners to all existing items
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.item-row').forEach(row => {
        attachItemListeners(row);
    });

    document.getElementById('tax_amount').addEventListener('input', calculateTotal);
    document.getElementById('discount_amount').addEventListener('input', calculateTotal);

    // Calculate initial total
    calculateTotal();
});
</script>
@endpush
@endsection
