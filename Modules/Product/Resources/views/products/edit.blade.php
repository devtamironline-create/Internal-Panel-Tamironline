@extends('layouts.admin')
@section('page-title', 'ویرایش محصول')
@section('main')
<div class="max-w-4xl">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">ویرایش محصول: {{ $product->name }}</h2>
        </div>
        <form action="{{ route('admin.products.update', $product) }}" method="POST" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام محصول *</label>
                    <input type="text" name="name" value="{{ old('name', $product->name) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('name') border-red-500 @enderror" required>
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">دسته‌بندی *</label>
                    <select name="product_category_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('product_category_id') border-red-500 @enderror" required>
                        <option value="">انتخاب کنید...</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ old('product_category_id', $product->product_category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('product_category_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                <textarea name="description" class="rich-editor">{{ old('description', $product->description) }}</textarea>
                @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">امکانات محصول</label>
                <textarea name="features" class="rich-editor">{{ old('features', $product->features) }}</textarea>
                <p class="mt-1 text-xs text-gray-500">از ادیتور برای فرمت‌بندی امکانات استفاده کنید</p>
                @error('features')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="border-t pt-6">
                <h3 class="text-md font-semibold text-gray-900 mb-4">مشخصات فنی</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="specifications">
                    @if($product->specifications)
                        @foreach($product->specifications as $index => $spec)
                        <div class="spec-item flex gap-2">
                            <input type="text" name="specifications[{{ $index }}][key]" value="{{ $spec['key'] ?? '' }}" placeholder="نام مشخصه" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg">
                            <input type="text" name="specifications[{{ $index }}][value]" value="{{ $spec['value'] ?? '' }}" placeholder="مقدار" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg">
                            <button type="button" onclick="this.parentElement.remove()" class="px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg">×</button>
                        </div>
                        @endforeach
                    @else
                    <div class="spec-item flex gap-2">
                        <input type="text" name="specifications[0][key]" placeholder="نام مشخصه" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg">
                        <input type="text" name="specifications[0][value]" placeholder="مقدار" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                    @endif
                </div>
                <button type="button" onclick="addSpecification()" class="mt-3 text-sm text-blue-600 hover:text-blue-700">+ افزودن مشخصه</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t pt-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">قیمت پایه (تومان)</label>
                    <input type="number" name="base_price" value="{{ old('base_price', $product->base_price) }}" min="0" step="1000" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    @error('base_price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">هزینه راه‌اندازی (تومان)</label>
                    <input type="number" name="setup_fee" value="{{ old('setup_fee', $product->setup_fee) }}" min="0" step="1000" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    @error('setup_fee')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="border-t pt-6">
                <h3 class="text-md font-semibold text-gray-900 mb-4">قیمت‌گذاری دوره‌ای</h3>
                <p class="text-sm text-gray-600 mb-4">قیمت محصول را برای هر دوره پرداخت تعیین کنید.</p>
                @php
                $existingPrices = $product->prices->keyBy('billing_cycle');
                @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach(['monthly' => 'ماهانه', 'quarterly' => 'سه ماهه', 'semiannually' => 'شش ماهه', 'annually' => 'سالانه', 'biennially' => 'دو سالانه', 'onetime' => 'یکباره', 'hourly' => 'ساعتی'] as $cycle => $label)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }} (تومان)</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <input type="number" name="prices[{{ $cycle }}][price]" value="{{ old('prices.'.$cycle.'.price', $existingPrices->get($cycle)?->price) }}" min="0" step="1000" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="قیمت">
                            </div>
                            <div>
                                <input type="number" name="prices[{{ $cycle }}][discount_percent]" value="{{ old('prices.'.$cycle.'.discount_percent', $existingPrices->get($cycle)?->discount_percent) }}" min="0" max="100" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="تخفیف %">
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center gap-6 border-t pt-6">
                <div class="flex items-center gap-3">
                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $product->is_active) ? 'checked' : '' }} class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <label for="is_active" class="text-gray-700">محصول فعال باشد</label>
                </div>

                <div class="flex items-center gap-3">
                    <input type="checkbox" id="is_featured" name="is_featured" value="1" {{ old('is_featured', $product->is_featured) ? 'checked' : '' }} class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <label for="is_featured" class="text-gray-700">محصول ویژه</label>
                </div>
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">بروزرسانی</button>
                <a href="{{ route('admin.products.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">انصراف</a>
            </div>
        </form>
    </div>

    {{-- قسمت مدیریت قیمت‌ها --}}
    <div class="bg-white rounded-xl shadow-sm mt-6">
        <div class="p-6 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900">مدیریت قیمت‌گذاری</h3>
            <p class="text-sm text-gray-600 mt-1">قیمت‌های مختلف برای دوره‌های پرداخت</p>
        </div>
        <div class="p-6">
            <p class="text-gray-500 text-center py-4">صفحه مدیریت قیمت‌ها به زودی...</p>
        </div>
    </div>
</div>

<script>
let specCounter = {{ $product->specifications ? count($product->specifications) : 1 }};
function addSpecification() {
    const container = document.getElementById('specifications');
    const newSpec = document.createElement('div');
    newSpec.className = 'spec-item flex gap-2';
    newSpec.innerHTML = `
        <input type="text" name="specifications[${specCounter}][key]" placeholder="نام مشخصه" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg">
        <input type="text" name="specifications[${specCounter}][value]" placeholder="مقدار" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg">
        <button type="button" onclick="this.parentElement.remove()" class="px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg">×</button>
    `;
    container.appendChild(newSpec);
    specCounter++;
}
</script>
@endsection
