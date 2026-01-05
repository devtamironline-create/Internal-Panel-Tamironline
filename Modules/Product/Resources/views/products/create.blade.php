@extends('layouts.admin')
@section('page-title', 'افزودن محصول')
@section('main')
<div class="max-w-4xl">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-100">
            <h2 class="text-lg font-semibold text-gray-900">افزودن محصول جدید</h2>
        </div>
        <form action="{{ route('admin.products.store') }}" method="POST" class="p-6 space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">نام محصول *</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('name') border-red-500 @enderror" required>
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">دسته‌بندی *</label>
                    <select name="product_category_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 @error('product_category_id') border-red-500 @enderror" required>
                        <option value="">انتخاب کنید...</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ old('product_category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('product_category_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">توضیحات</label>
                <textarea name="description" class="rich-editor">{{ old('description') }}</textarea>
                @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">امکانات محصول</label>
                <textarea name="features" class="rich-editor">{{ old('features') }}</textarea>
                <p class="mt-1 text-xs text-gray-500">از ادیتور برای فرمت‌بندی امکانات استفاده کنید</p>
                @error('features')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="border-t pt-6">
                <h3 class="text-md font-semibold text-gray-900 mb-4">مشخصات فنی</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="specifications">
                    <div class="spec-item flex gap-2">
                        <input type="text" name="specifications[0][key]" placeholder="نام مشخصه (مثل: فضا)" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg">
                        <input type="text" name="specifications[0][value]" placeholder="مقدار (مثل: 10 GB)" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                </div>
                <button type="button" onclick="addSpecification()" class="mt-3 text-sm text-blue-600 hover:text-blue-700">+ افزودن مشخصه</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t pt-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">قیمت پایه (تومان)</label>
                    <input type="number" name="base_price" value="{{ old('base_price', 0) }}" min="0" step="1000" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="mt-1 text-xs text-gray-500">قیمت پیش‌فرض (بدون دوره)</p>
                    @error('base_price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">هزینه راه‌اندازی (تومان)</label>
                    <input type="number" name="setup_fee" value="{{ old('setup_fee', 0) }}" min="0" step="1000" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="mt-1 text-xs text-gray-500">Setup Fee (یکبار در ابتدا)</p>
                    @error('setup_fee')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="border-t pt-6">
                <h3 class="text-md font-semibold text-gray-900 mb-4">قیمت‌گذاری دوره‌ای</h3>
                <p class="text-sm text-gray-600 mb-2">قیمت محصول را برای هر دوره پرداخت تعیین کنید. فیلدهای خالی، غیرفعال محسوب می‌شوند.</p>

                <div class="flex items-center gap-6 p-4 bg-blue-50 rounded-lg mb-6">
                    <label class="text-sm font-medium text-gray-700">واحد پول:</label>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="price_currency" value="IRR" checked class="w-4 h-4 text-blue-600">
                            <span class="text-sm">تومان (IRR)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="price_currency" value="USD" class="w-4 h-4 text-blue-600">
                            <span class="text-sm">دلار (USD)</span>
                        </label>
                    </div>
                    <span class="text-xs text-gray-600 mr-auto">
                        نرخ تبدیل: 1 USD = 50,000 تومان
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ماهانه (تومان)</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <input type="number" name="prices[monthly][price]" value="{{ old('prices.monthly.price') }}" min="0" step="1000" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="قیمت">
                            </div>
                            <div>
                                <input type="number" name="prices[monthly][discount_percent]" value="{{ old('prices.monthly.discount_percent') }}" min="0" max="100" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="تخفیف %">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">سه ماهه (تومان)</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <input type="number" name="prices[quarterly][price]" value="{{ old('prices.quarterly.price') }}" min="0" step="1000" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="قیمت">
                            </div>
                            <div>
                                <input type="number" name="prices[quarterly][discount_percent]" value="{{ old('prices.quarterly.discount_percent') }}" min="0" max="100" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="تخفیف %">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">شش ماهه (تومان)</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <input type="number" name="prices[semiannually][price]" value="{{ old('prices.semiannually.price') }}" min="0" step="1000" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="قیمت">
                            </div>
                            <div>
                                <input type="number" name="prices[semiannually][discount_percent]" value="{{ old('prices.semiannually.discount_percent') }}" min="0" max="100" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="تخفیف %">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">سالانه (تومان)</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <input type="number" name="prices[annually][price]" value="{{ old('prices.annually.price') }}" min="0" step="1000" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="قیمت">
                            </div>
                            <div>
                                <input type="number" name="prices[annually][discount_percent]" value="{{ old('prices.annually.discount_percent') }}" min="0" max="100" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="تخفیف %">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">دو سالانه (تومان)</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <input type="number" name="prices[biennially][price]" value="{{ old('prices.biennially.price') }}" min="0" step="1000" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="قیمت">
                            </div>
                            <div>
                                <input type="number" name="prices[biennially][discount_percent]" value="{{ old('prices.biennially.discount_percent') }}" min="0" max="100" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="تخفیف %">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">یکباره (تومان)</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <input type="number" name="prices[onetime][price]" value="{{ old('prices.onetime.price') }}" min="0" step="1000" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="قیمت">
                            </div>
                            <div>
                                <input type="number" name="prices[onetime][discount_percent]" value="{{ old('prices.onetime.discount_percent') }}" min="0" max="100" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="تخفیف %">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ساعتی (تومان)</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <input type="number" name="prices[hourly][price]" value="{{ old('prices.hourly.price') }}" min="0" step="1000" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="قیمت">
                            </div>
                            <div>
                                <input type="number" name="prices[hourly][discount_percent]" value="{{ old('prices.hourly.discount_percent') }}" min="0" max="100" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="تخفیف %">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-6 border-t pt-6">
                <div class="flex items-center gap-3">
                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }} class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <label for="is_active" class="text-gray-700">محصول فعال باشد</label>
                </div>

                <div class="flex items-center gap-3">
                    <input type="checkbox" id="is_featured" name="is_featured" value="1" {{ old('is_featured') ? 'checked' : '' }} class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <label for="is_featured" class="text-gray-700">محصول ویژه</label>
                </div>
            </div>

            <div class="flex items-center gap-4 pt-4 border-t border-gray-100">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">ذخیره</button>
                <a href="{{ route('admin.products.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">انصراف</a>
            </div>
        </form>
    </div>
</div>

<script>
let specCounter = 1;
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
