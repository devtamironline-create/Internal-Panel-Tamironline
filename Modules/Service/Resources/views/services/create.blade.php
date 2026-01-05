@extends('layouts.admin')
@section('page-title', 'افزودن سرویس')
@section('main')
<div class="max-w-4xl">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">افزودن سرویس جدید</h2>
        </div>
        <form action="{{ route('admin.services.store') }}" method="POST" class="p-6 space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">مشتری *</label>
                    <select name="customer_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('customer_id') border-red-500 @enderror" required>
                        <option value="">انتخاب مشتری...</option>
                        @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                            {{ $customer->full_name }} ({{ $customer->mobile }})
                        </option>
                        @endforeach
                    </select>
                    @error('customer_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">محصول *</label>
                    <select name="product_id" id="product_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('product_id') border-red-500 @enderror" required>
                        <option value="">انتخاب محصول...</option>
                        @foreach($products as $product)
                        <option value="{{ $product->id }}"
                            data-base-price="{{ $product->base_price }}"
                            data-setup-fee="{{ $product->setup_fee }}"
                            {{ old('product_id') == $product->id ? 'selected' : '' }}>
                            {{ $product->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('product_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">دوره پرداخت *</label>
                    <select name="billing_cycle" id="billing_cycle" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('billing_cycle') border-red-500 @enderror" required>
                        <option value="">انتخاب کنید...</option>
                        <option value="monthly" {{ old('billing_cycle') == 'monthly' ? 'selected' : '' }}>ماهانه</option>
                        <option value="quarterly" {{ old('billing_cycle') == 'quarterly' ? 'selected' : '' }}>سه ماهه</option>
                        <option value="semiannually" {{ old('billing_cycle') == 'semiannually' ? 'selected' : '' }}>شش ماهه</option>
                        <option value="annually" {{ old('billing_cycle') == 'annually' ? 'selected' : '' }}>سالانه</option>
                        <option value="biennially" {{ old('billing_cycle') == 'biennially' ? 'selected' : '' }}>دو سالانه</option>
                        <option value="onetime" {{ old('billing_cycle') == 'onetime' ? 'selected' : '' }}>یکباره</option>
                        <option value="hourly" {{ old('billing_cycle') == 'hourly' ? 'selected' : '' }}>ساعتی</option>
                    </select>
                    @error('billing_cycle')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تاریخ شروع *</label>
                    <input type="text" name="start_date" value="{{ old('start_date', \Morilog\Jalali\Jalalian::now()->format('Y/m/d')) }}"
                        placeholder="مثال: 1403/10/01"
                        class="jalali-datepicker w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('start_date') border-red-500 @enderror" required>
                    <p class="mt-1 text-xs text-gray-500">فرمت: سال/ماه/روز (شمسی)</p>
                    @error('start_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="border-t pt-6">
                <h3 class="text-md font-semibold text-gray-900 mb-4">قیمت‌گذاری</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">قیمت (تومان) *</label>
                        <input type="number" name="price" id="price" value="{{ old('price', 0) }}" min="0" step="1000"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('price') border-red-500 @enderror" required>
                        @error('price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">هزینه راه‌اندازی (تومان)</label>
                        <input type="number" name="setup_fee" id="setup_fee" value="{{ old('setup_fee', 0) }}" min="0" step="1000"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        @error('setup_fee')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">تخفیف (تومان)</label>
                        <input type="number" name="discount_amount" value="{{ old('discount_amount', 0) }}" min="0" step="1000"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        @error('discount_amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">یادداشت‌ها</label>
                <textarea name="notes" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="یادداشت‌های داخلی درباره این سرویس...">{{ old('notes') }}</textarea>
                @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t pt-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">وضعیت *</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('status') border-red-500 @enderror" required>
                        <option value="pending" {{ old('status', 'pending') == 'pending' ? 'selected' : '' }}>در انتظار</option>
                        <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>فعال</option>
                        <option value="suspended" {{ old('status') == 'suspended' ? 'selected' : '' }}>تعلیق</option>
                        <option value="cancelled" {{ old('status') == 'cancelled' ? 'selected' : '' }}>لغو شده</option>
                        <option value="expired" {{ old('status') == 'expired' ? 'selected' : '' }}>منقضی</option>
                    </select>
                    @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center pt-7">
                    <input type="checkbox" id="auto_renew" name="auto_renew" value="1" {{ old('auto_renew') ? 'checked' : '' }}
                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <label for="auto_renew" class="mr-3 text-gray-700">تمدید خودکار فعال باشد</label>
                </div>
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">ذخیره</button>
                <a href="{{ route('admin.services.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">انصراف</a>
            </div>
        </form>
    </div>
</div>

<script>
// Auto-fill price based on product and billing cycle
function updatePrice() {
    const productId = document.getElementById('product_id').value;
    const billingCycle = document.getElementById('billing_cycle').value;

    if (!productId || !billingCycle) {
        console.log('محصول یا دوره پرداخت انتخاب نشده');
        return;
    }

    const url = `{{ route('admin.services.get-price') }}?product_id=${productId}&billing_cycle=${billingCycle}`;
    console.log('در حال دریافت قیمت از:', url);

    // Fetch price from API
    fetch(url)
        .then(response => {
            console.log('پاسخ دریافت شد:', response);
            return response.json();
        })
        .then(data => {
            console.log('داده‌های قیمت:', data);

            if (!data.error) {
                const price = data.final_price || data.price || 0;
                const setupFee = data.setup_fee || 0;

                document.getElementById('price').value = price;
                document.getElementById('setup_fee').value = setupFee;

                console.log(`قیمت ${price} و هزینه راه‌اندازی ${setupFee} تنظیم شد`);

                // Optionally show discount info
                if (data.discount_percent > 0) {
                    console.log(`تخفیف ${data.discount_percent}% اعمال شد`);
                }
            } else {
                console.error('خطا در دریافت قیمت:', data.error);
            }
        })
        .catch(error => {
            console.error('خطا در درخواست قیمت:', error);
        });
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('product_id').addEventListener('change', updatePrice);
    document.getElementById('billing_cycle').addEventListener('change', updatePrice);
    console.log('رویدادهای قیمت‌گذاری خودکار فعال شدند');
});
</script>
@endsection
