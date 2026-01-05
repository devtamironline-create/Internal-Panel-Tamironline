@extends('layouts.admin')
@section('page-title', 'صدور فاکتور جدید')
@section('main')
<div class="max-w-5xl">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">صدور فاکتور جدید</h2>
        </div>
        <form action="{{ route('admin.invoices.store') }}" method="POST" class="p-6 space-y-6" id="invoiceForm">
            @csrf

            <!-- Client Information -->
            <div class="border-b pb-6">
                <h3 class="text-md font-semibold text-gray-900 mb-4">مشخصات مشتری</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">نام مشتری *</label>
                        <input type="text" name="client_name" value="{{ old('client_name') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('client_name') border-red-500 @enderror"
                            placeholder="نام و نام خانوادگی" required>
                        @error('client_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">شماره تلفن</label>
                        <input type="text" name="client_mobile" value="{{ old('client_mobile') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('client_mobile') border-red-500 @enderror"
                            placeholder="09123456789">
                        @error('client_mobile')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ایمیل</label>
                        <input type="email" name="client_email" value="{{ old('client_email') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('client_email') border-red-500 @enderror"
                            placeholder="email@example.com">
                        @error('client_email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">آدرس</label>
                        <input type="text" name="client_address" value="{{ old('client_address') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('client_address') border-red-500 @enderror"
                            placeholder="آدرس مشتری">
                        @error('client_address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تاریخ صدور *</label>
                    <input type="text" name="invoice_date" value="{{ old('invoice_date', \Morilog\Jalali\Jalalian::now()->format('Y/m/d')) }}"
                        class="jalali-datepicker w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('invoice_date') border-red-500 @enderror" required>
                    @error('invoice_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تاریخ سررسید *</label>
                    <input type="text" name="due_date" value="{{ old('due_date', \Morilog\Jalali\Jalalian::now()->addDays(7)->format('Y/m/d')) }}"
                        class="jalali-datepicker w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('due_date') border-red-500 @enderror" required>
                    @error('due_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="border-t pt-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-md font-semibold text-gray-900">آیتم‌های فاکتور</h3>
                    <button type="button" onclick="addItem()" class="px-3 py-1 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">+ افزودن آیتم</button>
                </div>

                <div id="items-container" class="space-y-4">
                    <div class="item-row grid grid-cols-12 gap-3 p-4 bg-gray-50 rounded-lg">
                        <div class="col-span-5">
                            <label class="block text-xs font-medium text-gray-700 mb-1">شرح *</label>
                            <input type="text" name="items[0][description]" value="{{ old('items.0.description') }}" placeholder="شرح خدمات یا محصول" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg" required>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">تعداد *</label>
                            <input type="number" name="items[0][quantity]" value="{{ old('items.0.quantity', 1) }}" min="1" class="item-quantity w-full px-3 py-2 text-sm border border-gray-300 rounded-lg" required>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">قیمت واحد *</label>
                            <input type="number" name="items[0][unit_price]" value="{{ old('items.0.unit_price', 0) }}" min="0" step="1000" class="item-price w-full px-3 py-2 text-sm border border-gray-300 rounded-lg" required>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">جمع</label>
                            <input type="text" class="item-total w-full px-3 py-2 text-sm border border-gray-200 bg-gray-100 rounded-lg" readonly value="0">
                        </div>
                        <div class="col-span-1 flex items-end">
                            <button type="button" onclick="removeItem(this)" class="w-full px-2 py-2 text-red-600 hover:bg-red-50 rounded-lg text-sm">حذف</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t pt-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">مالیات (تومان)</label>
                        <input type="number" name="tax_amount" id="tax_amount" value="{{ old('tax_amount', 0) }}" min="0" step="1000" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">تخفیف (تومان)</label>
                        <input type="number" name="discount_amount" id="discount_amount" value="{{ old('discount_amount', 0) }}" min="0" step="1000" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">جمع کل</label>
                        <input type="text" id="total_display" class="w-full px-4 py-2 border border-gray-200 bg-gray-100 rounded-lg font-bold" readonly value="0 تومان">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">یادداشت‌ها</label>
                <textarea name="notes" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="یادداشت‌های داخلی...">{{ old('notes') }}</textarea>
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">ذخیره فاکتور</button>
                <a href="{{ route('admin.invoices.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">انصراف</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
let itemCounter = 1;

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

// Attach listeners to initial item
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.item-row').forEach(row => {
        attachItemListeners(row);
    });

    document.getElementById('tax_amount').addEventListener('input', calculateTotal);
    document.getElementById('discount_amount').addEventListener('input', calculateTotal);
});
</script>
@endpush
@endsection
