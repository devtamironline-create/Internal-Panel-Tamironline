@extends('layouts.admin')
@section('page-title', 'افزونه جدید')
@section('main')
<div class="max-w-3xl">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">ایجاد افزونه جدید</h2>
        </div>
        <form action="{{ route('admin.product-addons.store') }}" method="POST" class="p-6 space-y-6" id="addonForm">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">محصول (اختیاری)</label>
                <select name="product_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('product_id') border-red-500 @enderror">
                    <option value="">عمومی (برای همه محصولات)</option>
                    @foreach($products as $product)
                    <option value="{{ $product->id }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>
                        {{ $product->name }}
                    </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">اگر خالی بگذارید، این افزونه برای همه محصولات قابل استفاده است</p>
                @error('product_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">نام افزونه *</label>
                <input type="text" name="name" value="{{ old('name') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('name') border-red-500 @enderror" placeholder="مثال: Backup روزانه، IP اضافی، فضای اضافی" required>
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Slug (اختیاری)</label>
                <input type="text" name="slug" value="{{ old('slug') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('slug') border-red-500 @enderror" placeholder="daily-backup" dir="ltr">
                <p class="mt-1 text-xs text-gray-500">اگر خالی بگذارید، به صورت خودکار ایجاد می‌شود</p>
                @error('slug')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('description') border-red-500 @enderror" placeholder="توضیحات کامل درباره این افزونه...">{{ old('description') }}</textarea>
                @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نوع افزونه *</label>
                    <select name="type" id="addon_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('type') border-red-500 @enderror" required>
                        <option value="recurring" {{ old('type') == 'recurring' ? 'selected' : '' }}>تکرارشونده (با دوره)</option>
                        <option value="onetime" {{ old('type') == 'onetime' ? 'selected' : '' }}>یکباره</option>
                    </select>
                    @error('type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div id="billing_cycle_container">
                    <label class="block text-sm font-medium text-gray-700 mb-1">دوره تکرار *</label>
                    <select name="billing_cycle" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('billing_cycle') border-red-500 @enderror">
                        <option value="">انتخاب کنید...</option>
                        <option value="monthly" {{ old('billing_cycle') == 'monthly' ? 'selected' : '' }}>ماهانه</option>
                        <option value="quarterly" {{ old('billing_cycle') == 'quarterly' ? 'selected' : '' }}>سه‌ماهه</option>
                        <option value="semiannually" {{ old('billing_cycle') == 'semiannually' ? 'selected' : '' }}>شش‌ماهه</option>
                        <option value="annually" {{ old('billing_cycle') == 'annually' ? 'selected' : '' }}>سالانه</option>
                    </select>
                    @error('billing_cycle')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">قیمت (تومان) *</label>
                <input type="number" name="price" value="{{ old('price', 0) }}" min="0" step="1000" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('price') border-red-500 @enderror" required>
                @error('price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <span class="text-sm font-medium text-gray-700">فعال</span>
                </label>
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">ایجاد افزونه</button>
                <a href="{{ route('admin.product-addons.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">انصراف</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('addon_type');
    const billingCycleContainer = document.getElementById('billing_cycle_container');

    function toggleBillingCycle() {
        if (typeSelect.value === 'onetime') {
            billingCycleContainer.style.display = 'none';
        } else {
            billingCycleContainer.style.display = 'block';
        }
    }

    typeSelect.addEventListener('change', toggleBillingCycle);
    toggleBillingCycle();
});
</script>
@endpush
@endsection
