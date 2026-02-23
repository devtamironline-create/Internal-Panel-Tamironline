@extends('layouts.admin')
@section('page-title', 'ویرایش سفارش دستی')
@section('main')
<div x-data="editManualOrder()" class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('warehouse.show', $order) }}" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-gray-900">ویرایش سفارش دستی</h1>
            <p class="text-gray-500 text-sm mt-0.5">شماره سفارش: <span dir="ltr" class="text-brand-600 font-medium">{{ $order->order_number }}</span></p>
        </div>
    </div>

    <form @submit.prevent="submitOrder()" id="editOrderForm">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- ستون اصلی --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- اطلاعات مشتری --}}
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="font-bold text-gray-800 mb-4">اطلاعات مشتری</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">نام <span class="text-red-500">*</span></label>
                            <input type="text" x-model="form.customer_first_name" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">نام خانوادگی <span class="text-red-500">*</span></label>
                            <input type="text" x-model="form.customer_last_name" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">موبایل <span class="text-red-500">*</span></label>
                            <input type="text" x-model="form.customer_mobile" required dir="ltr" placeholder="09xxxxxxxxx"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ایمیل</label>
                            <input type="email" x-model="form.customer_email" dir="ltr"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm">
                        </div>
                    </div>
                </div>

                {{-- آدرس --}}
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="font-bold text-gray-800 mb-4">آدرس ارسال</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">استان <span class="text-red-500">*</span></label>
                            <input type="text" x-model="form.state" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">شهر <span class="text-red-500">*</span></label>
                            <input type="text" x-model="form.city" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">کد پستی <span class="text-red-500">*</span></label>
                            <input type="text" x-model="form.postcode" required dir="ltr" maxlength="10" pattern="\d{10}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">آدرس کامل <span class="text-red-500">*</span></label>
                        <textarea x-model="form.address" required rows="2"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm"></textarea>
                    </div>
                </div>

                {{-- محصولات --}}
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="font-bold text-gray-800 mb-4">محصولات</h2>
                    <div class="relative mb-4">
                        <div class="flex-1 relative">
                            <input type="text"
                                x-model="productSearch"
                                @input.debounce.300ms="searchProducts()"
                                @focus="showProductResults = productResults.length > 0"
                                placeholder="جستجوی محصول (نام، SKU، شناسه)..."
                                class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm">
                            <svg class="w-5 h-5 text-gray-400 absolute top-2.5 right-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <div x-show="showProductResults && productResults.length > 0"
                             @click.away="showProductResults = false"
                             x-transition
                             class="absolute left-0 right-0 top-full mt-1 bg-white border rounded-xl shadow-lg z-50 max-h-64 overflow-y-auto">
                            <template x-for="product in productResults" :key="product.id">
                                <button type="button" @click="addProduct(product)"
                                    class="w-full text-right px-4 py-3 hover:bg-gray-50 border-b last:border-0 flex items-center justify-between">
                                    <div>
                                        <div class="font-medium text-sm" x-text="product.name"></div>
                                        <div class="text-xs text-gray-500">
                                            <span x-show="product.sku">SKU: <span x-text="product.sku"></span> | </span>
                                            ID: <span x-text="product.id"></span>
                                        </div>
                                    </div>
                                    <div class="text-left">
                                        <div class="font-bold text-sm text-brand-600" x-text="formatPrice(product.price) + ' تومان'"></div>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div x-show="form.items.length > 0">
                        <div class="border rounded-lg overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-right font-medium text-gray-600">محصول</th>
                                        <th class="px-4 py-2 text-center font-medium text-gray-600 w-28">تعداد</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600 w-32">قیمت واحد</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-600 w-32">جمع</th>
                                        <th class="px-4 py-2 w-12"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(item, index) in form.items" :key="index">
                                        <tr class="border-t">
                                            <td class="px-4 py-3">
                                                <div class="font-medium" x-text="item.name"></div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center justify-center gap-1">
                                                    <button type="button" @click="decreaseQty(index)" class="w-7 h-7 flex items-center justify-center rounded bg-gray-100 hover:bg-gray-200 text-gray-600">-</button>
                                                    <input type="number" x-model.number="item.quantity" min="1"
                                                        class="w-12 text-center border rounded py-1 text-sm">
                                                    <button type="button" @click="increaseQty(index)" class="w-7 h-7 flex items-center justify-center rounded bg-gray-100 hover:bg-gray-200 text-gray-600">+</button>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-left" dir="ltr" x-text="formatPrice(item.price)"></td>
                                            <td class="px-4 py-3 text-left font-bold" dir="ltr" x-text="formatPrice(item.price * item.quantity)"></td>
                                            <td class="px-4 py-3">
                                                <button type="button" @click="removeItem(index)" class="text-red-400 hover:text-red-600">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div x-show="form.items.length === 0" class="text-center py-8 text-gray-400">
                        <p>محصولی اضافه نشده</p>
                    </div>
                </div>

                {{-- یادداشت --}}
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="font-bold text-gray-800 mb-4">یادداشت‌ها</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">یادداشت مشتری</label>
                            <textarea x-model="form.customer_note" rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">یادداشت داخلی</label>
                            <textarea x-model="form.notes" rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- سایدبار --}}
            <div class="space-y-6">
                {{-- ارسال --}}
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="font-bold text-gray-800 mb-4">روش ارسال</h2>
                    <div class="space-y-2">
                        @foreach($shippingTypes as $type)
                        <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition"
                               :class="form.shipping_type === '{{ $type->slug }}' ? 'border-brand-500 bg-brand-50' : 'border-gray-200'"
                               @click="selectShippingType('{{ $type->slug }}')">
                            <input type="radio" x-model="form.shipping_type" value="{{ $type->slug }}" class="text-brand-600 focus:ring-brand-500">
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium text-sm">{{ $type->name }}</span>
                                    @if(isset($shippingCosts[$type->slug]))
                                    <span class="text-xs font-medium text-brand-600" dir="ltr">{{ number_format($shippingCosts[$type->slug]['cost']) }} تومان</span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500">{{ $type->timer_label }}</div>
                            </div>
                        </label>
                        @endforeach
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">هزینه ارسال (تومان)</label>
                        <input type="number" x-model.number="form.shipping_cost" min="0" dir="ltr"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm">
                        <p class="text-xs text-gray-400 mt-1">هزینه از ووکامرس خوانده شده. در صورت نیاز قابل تغییر است.</p>
                    </div>
                </div>

                {{-- پرداخت --}}
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="font-bold text-gray-800 mb-4">روش پرداخت</h2>
                    <div class="space-y-2">
                        @foreach($paymentGateways as $gw)
                        <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition"
                               :class="form.payment_method === '{{ $gw['id'] }}' ? 'border-brand-500 bg-brand-50' : 'border-gray-200'">
                            <input type="radio" x-model="form.payment_method" value="{{ $gw['id'] }}"
                                @click="form.payment_method_title = '{{ $gw['title'] }}'"
                                class="text-brand-600 focus:ring-brand-500">
                            <span class="font-medium text-sm">{{ $gw['title'] }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- خلاصه --}}
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="font-bold text-gray-800 mb-4">خلاصه سفارش</h2>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">جمع محصولات:</span>
                            <span class="font-medium" x-text="formatPrice(subtotal()) + ' تومان'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">هزینه ارسال:</span>
                            <span class="font-medium" x-text="form.shipping_cost > 0 ? formatPrice(form.shipping_cost) + ' تومان' : 'رایگان'"></span>
                        </div>
                        <div class="border-t pt-3 flex justify-between">
                            <span class="font-bold text-gray-800">مبلغ کل:</span>
                            <span class="font-bold text-lg text-brand-600" x-text="formatPrice(total()) + ' تومان'"></span>
                        </div>
                    </div>
                </div>

                <button type="submit" :disabled="submitting || form.items.length === 0"
                    class="w-full py-3 bg-brand-600 text-white rounded-xl hover:bg-brand-700 font-bold text-base disabled:opacity-50 disabled:cursor-not-allowed transition">
                    <span x-show="!submitting">ذخیره تغییرات</span>
                    <span x-show="submitting">در حال ذخیره...</span>
                </button>

                <a href="{{ route('warehouse.show', $order) }}" class="block w-full py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 font-medium text-base text-center">
                    انصراف
                </a>
            </div>
        </div>
    </form>
</div>

<script>
function editManualOrder() {
    return {
        form: {
            customer_first_name: @json($orderInfo['first_name']),
            customer_last_name: @json($orderInfo['last_name']),
            customer_mobile: @json($orderInfo['phone']),
            customer_email: @json($orderInfo['email']),
            state: @json($orderInfo['state']),
            city: @json($orderInfo['city']),
            address: @json($orderInfo['address']),
            postcode: @json($orderInfo['postcode']),
            shipping_type: @json($order->shipping_type),
            payment_method: @json($orderInfo['payment_method']),
            payment_method_title: @json($orderInfo['payment_method_title']),
            shipping_cost: {{ $orderInfo['shipping_cost'] }},
            coupon_code: '',
            notes: @json($order->notes ?? ''),
            customer_note: @json($orderInfo['customer_note']),
            items: @json($order->items->map(fn($item) => [
                'wc_product_id' => $item->wc_product_id,
                'variation_id' => null,
                'name' => $item->product_name,
                'sku' => $item->product_sku,
                'price' => (float) $item->price,
                'weight' => (float) $item->weight,
                'quantity' => $item->quantity,
            ])),
        },
        shippingCostsMap: @json(collect($shippingCosts)->mapWithKeys(fn($v, $k) => [$k => $v['cost']])),
        submitting: false,
        productSearch: '',
        productResults: [],
        productLoading: false,
        showProductResults: false,

        selectShippingType(slug) {
            this.form.shipping_type = slug;
            if (this.shippingCostsMap[slug] !== undefined) {
                this.form.shipping_cost = this.shippingCostsMap[slug];
            }
        },

        formatPrice(num) {
            if (!num) return '0';
            return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        subtotal() {
            return this.form.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        },

        total() {
            return Math.max(0, this.subtotal() + (this.form.shipping_cost || 0));
        },

        async searchProducts() {
            if (this.productSearch.length < 2) {
                this.productResults = [];
                this.showProductResults = false;
                return;
            }
            this.productLoading = true;
            try {
                const res = await fetch(`{{ route('warehouse.manual-order.search-products') }}?q=${encodeURIComponent(this.productSearch)}`);
                this.productResults = await res.json();
                this.showProductResults = this.productResults.length > 0;
            } catch (e) {
                console.error(e);
            }
            this.productLoading = false;
        },

        addProduct(product) {
            const existing = this.form.items.find(i => i.wc_product_id === product.id || (product.is_variation && i.variation_id === product.id));
            if (existing) {
                existing.quantity++;
                this.showProductResults = false;
                this.productSearch = '';
                return;
            }
            this.form.items.push({
                wc_product_id: product.parent_id || product.id,
                variation_id: product.is_variation ? product.id : null,
                name: product.name,
                sku: product.sku,
                price: product.price,
                weight: product.weight,
                quantity: 1,
            });
            this.showProductResults = false;
            this.productSearch = '';
        },

        removeItem(index) { this.form.items.splice(index, 1); },
        increaseQty(index) { this.form.items[index].quantity++; },
        decreaseQty(index) { if (this.form.items[index].quantity > 1) this.form.items[index].quantity--; },

        async submitOrder() {
            if (this.form.items.length === 0) { alert('حداقل یک محصول اضافه کنید.'); return; }
            if (!this.form.customer_first_name || !this.form.customer_last_name) { alert('نام و نام خانوادگی الزامی است.'); return; }
            if (!this.form.customer_mobile) { alert('شماره موبایل الزامی است.'); return; }
            if (!this.form.state || !this.form.city || !this.form.address || !this.form.postcode) { alert('اطلاعات آدرس کامل نیست.'); return; }

            this.submitting = true;
            try {
                const res = await fetch('{{ route('warehouse.manual-order.update', $order) }}', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.form),
                });

                if (res.redirected) {
                    window.location.href = res.url;
                    return;
                }

                const data = await res.json();
                if (res.status === 422 && data.errors) {
                    alert(Object.values(data.errors).flat().join('\n'));
                } else if (!res.ok) {
                    alert(data.message || 'خطا در ویرایش سفارش');
                } else {
                    window.location.href = data.redirect || '{{ route('warehouse.show', $order) }}';
                }
            } catch (e) {
                console.error(e);
                alert('خطا در ارتباط با سرور');
            }
            this.submitting = false;
        },
    };
}
</script>
@endsection
