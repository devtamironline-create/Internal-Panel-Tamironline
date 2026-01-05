@extends('layouts.admin')
@section('page-title', 'افزونه‌های محصولات')
@section('main')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">افزونه‌های محصولات</h1>
        <p class="mt-1 text-sm text-gray-600">مدیریت افزونه‌های قابل خرید برای محصولات</p>
    </div>
    <a href="{{ route('admin.product-addons.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
        <span class="text-lg ml-1">+</span> افزونه جدید
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm mb-6 p-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <select name="product_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                <option value="">همه محصولات</option>
                @foreach($products as $product)
                <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                    {{ $product->name }}
                </option>
                @endforeach
            </select>
        </div>
        <div>
            <select name="global" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                <option value="">همه</option>
                <option value="1" {{ request('global') == '1' ? 'selected' : '' }}>فقط افزونه‌های عمومی</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="flex-1 px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900">فیلتر</button>
            @if(request()->hasAny(['product_id', 'global']))
            <a href="{{ route('admin.product-addons.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">پاک</a>
            @endif
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">نام افزونه</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">محصول</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">نوع</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">دوره</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">قیمت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">وضعیت</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($addons as $addon)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900">{{ $addon->name }}</p>
                        @if($addon->description)
                        <p class="text-xs text-gray-600 mt-1">{{ Str::limit($addon->description, 50) }}</p>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        @if($addon->product_id)
                            {{ $addon->product->name }}
                        @else
                            <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">عمومی</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs font-medium rounded-full
                            {{ $addon->type === 'recurring' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $addon->type === 'recurring' ? 'تکرارشونده' : 'یکباره' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        @if($addon->billing_cycle)
                            @php
                                $cycles = [
                                    'monthly' => 'ماهانه',
                                    'quarterly' => 'سه‌ماهه',
                                    'semiannually' => 'شش‌ماهه',
                                    'annually' => 'سالانه',
                                ];
                            @endphp
                            {{ $cycles[$addon->billing_cycle] ?? $addon->billing_cycle }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ number_format($addon->price) }} تومان</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs font-medium rounded-full
                            {{ $addon->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $addon->is_active ? 'فعال' : 'غیرفعال' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm space-x-2 space-x-reverse">
                        <a href="{{ route('admin.product-addons.edit', $addon) }}" class="text-blue-600 hover:text-blue-800">ویرایش</a>
                        <form action="{{ route('admin.product-addons.destroy', $addon) }}" method="POST" class="inline" onsubmit="return confirm('آیا مطمئن هستید؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800">حذف</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                            <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <p class="text-lg font-medium">هیچ افزونه‌ای یافت نشد</p>
                            <a href="{{ route('admin.product-addons.create') }}" class="mt-3 text-blue-600 hover:text-blue-800">اولین افزونه را ایجاد کنید</a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($addons->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $addons->links() }}
    </div>
    @endif
</div>
@endsection
