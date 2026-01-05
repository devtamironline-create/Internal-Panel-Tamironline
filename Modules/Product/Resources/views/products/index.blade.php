@extends('layouts.admin')
@section('page-title', 'مدیریت محصولات')
@section('main')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">محصولات</h2>
            <p class="text-gray-600 mt-1">مدیریت محصولات و خدمات</p>
        </div>
        <a href="{{ route('admin.products.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            افزودن محصول
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4">
        <form action="{{ route('admin.products.index') }}" method="GET" class="flex flex-col sm:flex-row gap-4">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="جستجو..." class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            <select name="category" class="px-4 py-2 border border-gray-300 rounded-lg">
                <option value="">همه دسته‌ها</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">فیلتر</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">محصول</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">دسته‌بندی</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">قیمت‌ها</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Setup Fee</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($products as $product)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div>
                            <p class="font-medium text-gray-900">{{ $product->name }}</p>
                            @if($product->is_featured)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">ویژه</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 text-gray-600">{{ $product->category->name }}</td>
                    <td class="px-6 py-4">
                        @php
                            $cycles = [
                                'hourly' => 'ساعتی',
                                'monthly' => 'ماهانه',
                                'quarterly' => 'سه‌ماهه',
                                'semiannually' => 'شش‌ماهه',
                                'annually' => 'سالانه',
                                'biennially' => 'دوسالانه',
                                'onetime' => 'یکباره',
                            ];
                        @endphp
                        @if($product->prices->count() > 0)
                        <div class="space-y-1">
                            @foreach($product->prices as $price)
                            <div class="flex items-center gap-2 text-xs">
                                <span class="font-medium text-gray-700 min-w-[60px]">{{ $cycles[$price->billing_cycle] ?? $price->billing_cycle }}:</span>
                                @if($price->discount_percent > 0)
                                <span class="text-gray-400 line-through">{{ number_format($price->price) }}</span>
                                <span class="text-green-600 font-semibold">{{ number_format($price->final_price) }}</span>
                                <span class="text-xs text-green-600">(-{{ $price->discount_percent }}%)</span>
                                @else
                                <span class="text-gray-900 font-semibold">{{ number_format($price->price) }}</span>
                                @endif
                                <span class="text-gray-500">تومان</span>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="text-sm">
                            <span class="text-gray-900 font-semibold">{{ number_format($product->base_price) }}</span>
                            <span class="text-gray-500 text-xs">تومان</span>
                        </div>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-gray-600">
                        @if($product->setup_fee > 0)
                        {{ number_format($product->setup_fee) }} تومان
                        @else
                        <span class="text-green-600 text-sm font-medium">رایگان</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @if($product->is_active)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">فعال</span>
                        @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">غیرفعال</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.products.edit', $product) }}" class="p-2 text-gray-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg" title="ویرایش">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <form action="{{ route('admin.products.destroy', $product) }}" method="POST" onsubmit="return confirm('آیا مطمئن هستید؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-2 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg" title="حذف">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">محصولی یافت نشد</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($products->hasPages())
    <div class="flex justify-center">{{ $products->links() }}</div>
    @endif
</div>
@endsection
