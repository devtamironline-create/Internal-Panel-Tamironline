@extends('warehouse::layouts.master')

@section('title', 'کاتالوگ محصولات')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">کاتالوگ محصولات</h1>
            <p class="text-sm text-gray-500 mt-1">لیست همه محصولات با وزن و اطلاعات کامل</p>
        </div>
        <a href="{{ route('warehouse.woocommerce.index') }}" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm">
            بازگشت به اتصال ووکامرس
        </a>
    </div>

    <!-- آمار -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs text-blue-600 mb-1">کل محصولات</div>
                    <div class="text-2xl font-bold text-blue-800">{{ number_format($stats['total']) }}</div>
                </div>
                <div class="text-4xl">📦</div>
            </div>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs text-red-600 mb-1">بدون وزن (0g)</div>
                    <div class="text-2xl font-bold text-red-800">{{ number_format($stats['zero_weight']) }}</div>
                </div>
                <div class="text-4xl">⚠️</div>
            </div>
        </div>
        <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs text-orange-600 mb-1">پکیج‌ها</div>
                    <div class="text-2xl font-bold text-orange-800">{{ number_format($stats['bundles']) }}</div>
                </div>
                <div class="text-4xl">📦</div>
            </div>
        </div>
    </div>

    <!-- فیلترها -->
    <form method="GET" class="bg-white rounded-xl border p-4 mb-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="جستجو (نام، SKU، ID)..."
                       class="w-full px-3 py-2 border rounded-lg text-sm">
            </div>
            <div>
                <select name="type" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">همه انواع</option>
                    <option value="simple" {{ request('type') === 'simple' ? 'selected' : '' }}>Simple</option>
                    <option value="variable" {{ request('type') === 'variable' ? 'selected' : '' }}>Variable</option>
                    <option value="bundle" {{ request('type') === 'bundle' ? 'selected' : '' }}>Bundle</option>
                    <option value="yith_bundle" {{ request('type') === 'yith_bundle' ? 'selected' : '' }}>YITH Bundle</option>
                    <option value="woosb" {{ request('type') === 'woosb' ? 'selected' : '' }}>WooSB</option>
                    <option value="grouped" {{ request('type') === 'grouped' ? 'selected' : '' }}>Grouped</option>
                </select>
            </div>
            <div>
                <select name="weight_filter" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">همه وزن‌ها</option>
                    <option value="zero" {{ request('weight_filter') === 'zero' ? 'selected' : '' }}>فقط 0 گرم</option>
                    <option value="non_zero" {{ request('weight_filter') === 'non_zero' ? 'selected' : '' }}>بیشتر از 0 گرم</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
                    فیلتر
                </button>
                <a href="{{ route('warehouse.woocommerce.products-catalog') }}"
                   class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200">
                    پاک کردن
                </a>
            </div>
        </div>
    </form>

    <!-- جدول محصولات -->
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-right font-medium text-gray-700 w-16">ID</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700">نام محصول</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700 w-24">نوع</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700 w-32">SKU</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-700 w-24">وزن (گرم)</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-700 w-20">پکیج</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-700 w-24">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($products as $product)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-gray-500 text-xs font-mono" dir="ltr">{{ $product->wc_product_id }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-800">{{ $product->name }}</div>
                            @if($product->is_bundle)
                            <div class="text-xs text-orange-600 mt-0.5">
                                {{ $product->bundleItems->count() }} زیرمجموعه
                            </div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded text-xs font-medium
                                {{ $product->type === 'simple' ? 'bg-blue-100 text-blue-700' : '' }}
                                {{ $product->type === 'variable' ? 'bg-purple-100 text-purple-700' : '' }}
                                {{ in_array($product->type, ['bundle', 'yith_bundle', 'woosb', 'grouped']) ? 'bg-orange-100 text-orange-700' : '' }}
                            ">{{ $product->type }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs font-mono" dir="ltr">{{ $product->sku ?: '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="font-bold {{ $product->weight > 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format($product->weight, 0) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($product->is_bundle)
                            <span class="text-orange-600">✓</span>
                            @else
                            <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($product->weight == 0)
                            <button onclick="debugProduct({{ $product->wc_product_id }})"
                                    class="text-xs px-2 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">
                                چک WC
                            </button>
                            @else
                            <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>
                    </tr>
                    @if($product->weight == 0)
                    <tr id="debug-result-{{ $product->wc_product_id }}" class="hidden">
                        <td colspan="7" class="px-4 py-3 bg-gray-50">
                            <div id="debug-content-{{ $product->wc_product_id }}" class="text-xs"></div>
                        </td>
                    </tr>
                    @endif
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                            محصولی یافت نشد
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
        <div class="border-t px-4 py-3">
            {{ $products->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function debugProduct(productId) {
    const resultRow = document.getElementById('debug-result-' + productId);
    const content = document.getElementById('debug-content-' + productId);

    // اگه بازه، ببند
    if (!resultRow.classList.contains('hidden')) {
        resultRow.classList.add('hidden');
        return;
    }

    content.innerHTML = '<div class="text-gray-500">در حال دریافت اطلاعات از ووکامرس...</div>';
    resultRow.classList.remove('hidden');

    fetch('{{ route("warehouse.woocommerce.debug-product-weight") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ product_id: productId }),
    })
    .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(data => {
        if (!data.success) {
            content.innerHTML = '<div class="text-red-600">' + data.message + '</div>';
            return;
        }

        const d = data.debug;
        let html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';

        // محصول محلی
        html += '<div class="bg-white p-3 rounded border">';
        html += '<div class="font-bold text-gray-700 mb-2">📦 دیتابیس محلی:</div>';
        html += '<div class="space-y-1 text-gray-600">';
        html += '<div>نام: <span class="font-medium">' + d.local_product.name + '</span></div>';
        html += '<div>نوع: <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded">' + d.local_product.type + '</span></div>';
        html += '<div>وزن (DB): <span class="font-bold text-red-600">' + d.local_product.weight_in_db + ' گرم</span></div>';
        html += '<div>پکیج؟: <span class="font-medium">' + (d.local_product.is_bundle ? 'بله' : 'خیر') + '</span></div>';
        html += '</div></div>';

        // محصول ووکامرس
        html += '<div class="bg-white p-3 rounded border">';
        html += '<div class="font-bold text-gray-700 mb-2">🌐 ووکامرس:</div>';
        html += '<div class="space-y-1 text-gray-600">';
        html += '<div>نام: <span class="font-medium">' + d.wc_product.name + '</span></div>';
        html += '<div>نوع: <span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded">' + d.wc_product.type + '</span></div>';
        html += '<div>وزن خام: <span class="font-bold">' + d.wc_product.raw_weight + ' ' + d.wc_product.weight_unit + '</span></div>';
        html += '<div>تبدیل شده: <span class="font-bold text-green-600">' + d.wc_product.converted_to_grams + ' گرم</span></div>';
        html += '<div>SKU: <span class="font-mono text-xs">' + (d.wc_product.sku || '—') + '</span></div>';
        html += '</div></div>';

        // اگه bundle بود
        if (d.bundle_info) {
            html += '<div class="md:col-span-2 bg-orange-50 p-3 rounded border border-orange-200">';
            html += '<div class="font-bold text-orange-700 mb-2">📦 اطلاعات پکیج:</div>';
            html += '<div class="text-sm">تعداد زیرمجموعه: <span class="font-bold">' + d.bundle_info.children_count + '</span></div>';
            html += '<div class="mt-2 space-y-1">';
            d.bundle_info.children.forEach(function(child, i) {
                const weightColor = child.weight > 0 ? 'text-green-600' : 'text-red-600';
                html += '<div class="flex items-center gap-2 text-xs">';
                html += '<span class="text-gray-500">' + (i + 1) + '.</span>';
                html += '<span class="flex-1">' + child.name + '</span>';
                html += '<span class="' + weightColor + ' font-bold">' + child.weight + 'g × ' + child.quantity + ' = ' + child.total_weight + 'g</span>';
                html += '</div>';
            });
            html += '</div>';
            html += '<div class="mt-3 pt-3 border-t border-orange-200 font-bold text-orange-800">';
            html += 'وزن محاسبه شده پکیج: <span class="text-lg">' + d.bundle_info.calculated_bundle_weight + ' گرم</span>';
            html += '</div></div>';
        }

        html += '</div>';
        content.innerHTML = html;
    })
    .catch(err => {
        content.innerHTML = '<div class="text-red-600">خطا: ' + err.message + '</div>';
    });
}
</script>
@endpush
@endsection
