@extends('layouts.admin')
@section('page-title', 'اتصال ووکامرس')
@section('main')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ route('warehouse.index') }}" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-900">اتصال به ووکامرس</h1>
                <p class="text-gray-600 mt-1">سینک سفارشات از فروشگاه ووکامرسی</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Settings Card -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <h2 class="text-lg font-bold text-gray-900">تنظیمات API</h2>
            </div>

            <form action="{{ route('warehouse.woocommerce.save') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">آدرس سایت <span class="text-red-500">*</span></label>
                    <input type="url" name="site_url" value="{{ old('site_url', $settings['site_url']) }}" required dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-sm"
                           placeholder="https://yourstore.com">
                    @error('site_url')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Consumer Key <span class="text-red-500">*</span></label>
                    <input type="text" name="consumer_key" value="{{ old('consumer_key', $settings['consumer_key']) }}" required dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-sm font-mono"
                           placeholder="ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                    @error('consumer_key')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Consumer Secret <span class="text-red-500">*</span></label>
                    <input type="password" name="consumer_secret" value="{{ old('consumer_secret', $settings['consumer_secret']) }}" required dir="ltr"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-sm font-mono"
                           placeholder="cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                    @error('consumer_secret')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-3 pt-4 border-t">
                    <button type="submit" class="px-6 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium text-sm">ذخیره تنظیمات</button>
                    <button type="button" onclick="testWcConnection()" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium text-sm">تست اتصال</button>
                </div>
            </form>

            <!-- Connection Test Result -->
            <div id="wc-test-result" class="hidden mt-4 p-4 rounded-lg text-sm"></div>
        </div>

        <!-- Sync Card -->
        <div class="space-y-6">
            <!-- Status Card -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </div>
                    <h2 class="text-lg font-bold text-gray-900">سینک سفارشات</h2>
                </div>

                @if($lastSync)
                <div class="flex items-center gap-2 mb-4 p-3 bg-green-50 rounded-lg">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-sm text-green-800">آخرین سینک: {{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($lastSync))->format('Y/m/d H:i') }}</span>
                </div>
                @else
                <div class="flex items-center gap-2 mb-4 p-3 bg-yellow-50 rounded-lg">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    <span class="text-sm text-yellow-800">هنوز سینکی انجام نشده است.</span>
                </div>
                @endif

                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">وضعیت سفارشات ووکامرس</label>
                        <select id="wc-sync-status" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="processing,bslm-preparation,completed" selected>همه سفارشات (پردازش + باسلام + حضوری)</option>
                            <option value="processing">در حال پردازش (Processing)</option>
                            <option value="bslm-preparation">سفارش باسلام (Basalam)</option>
                            <option value="completed">تکمیل شده / حضوری (Completed)</option>
                            <option value="on-hold">در انتظار (On Hold)</option>
                            <option value="pending">در انتظار پرداخت (Pending)</option>
                            <option value="any">همه وضعیت‌ها</option>
                        </select>
                    </div>
                    <button onclick="syncOrders()" id="sync-btn" class="w-full px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" id="sync-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span id="sync-text">شروع سینک سفارشات</span>
                    </button>
                </div>

                <!-- Sync Result -->
                <div id="wc-sync-result" class="hidden mt-4 p-4 rounded-lg text-sm"></div>
            </div>

            <!-- Help Card -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-3">راهنمای اتصال</h3>
                <ol class="space-y-2 text-sm text-gray-600 list-decimal list-inside">
                    <li>وارد پنل مدیریت وردپرس شوید</li>
                    <li>به <strong class="text-gray-900">ووکامرس &rarr; تنظیمات &rarr; پیشرفته &rarr; REST API</strong> بروید</li>
                    <li>روی <strong class="text-gray-900">افزودن کلید</strong> کلیک کنید</li>
                    <li>توضیحات را وارد کنید و سطح دسترسی <strong class="text-gray-900">خواندن/نوشتن (Read/Write)</strong> را انتخاب کنید</li>
                    <li>کلیدهای Consumer Key و Consumer Secret را کپی کنید</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- WC Status Sync Card -->
    <div class="bg-white rounded-xl shadow-sm p-6 lg:col-span-2">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-gray-900">سینک وضعیت به ووکامرس</h2>
                <p class="text-sm text-gray-500">وقتی وضعیت سفارش در پنل تغییر کنه، وضعیت سفارش در ووکامرس هم به‌روز میشه.</p>
            </div>
        </div>

        <div class="mb-5 p-4 bg-gray-50 rounded-lg">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" id="wc-status-sync-toggle" {{ $statusSyncEnabled ? 'checked' : '' }}
                    onchange="toggleStatusSync(this.checked)"
                    class="w-5 h-5 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                <div>
                    <span class="text-sm font-bold text-gray-800">فعال‌سازی سینک وضعیت</span>
                    <span class="block text-xs text-gray-500">هر تغییر وضعیت در پنل، خودکار به ووکامرس ارسال می‌شود</span>
                </div>
            </label>
        </div>

        <h3 class="text-sm font-bold text-gray-700 mb-3">نگاشت وضعیت‌ها (پنل → ووکامرس)</h3>
        <div class="border rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2.5 text-right font-medium text-gray-700">وضعیت پنل</th>
                        <th class="px-4 py-2.5 text-center font-medium text-gray-400">→</th>
                        <th class="px-4 py-2.5 text-right font-medium text-gray-700">وضعیت ووکامرس</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php
                        $panelLabels = \Modules\Warehouse\Models\WarehouseOrder::statusLabels();
                    @endphp
                    @foreach($statusMap as $panelStatus => $wcStatus)
                    <tr>
                        <td class="px-4 py-3">
                            <span class="font-medium text-gray-800">{{ $panelLabels[$panelStatus] ?? $panelStatus }}</span>
                            <span class="text-xs text-gray-400 mr-1">({{ $panelStatus }})</span>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-400">→</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium
                                {{ $wcStatus === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                {{ $wcStatus === 'processing' ? 'bg-blue-100 text-blue-700' : '' }}
                                {{ $wcStatus === 'on-hold' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                {{ $wcStatus === 'cancelled' ? 'bg-red-100 text-red-700' : '' }}
                            ">
                                {{ $wcStatusLabels[$wcStatus] ?? $wcStatus }}
                                <span class="text-gray-400">({{ $wcStatus }})</span>
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-xs text-gray-400 mt-3">توجه: سینک فقط برای سفارشاتی انجام می‌شود که از ووکامرس وارد شده باشند. سفارشات دستی پنل سینک نمی‌شوند.</p>
    </div>

    <!-- Product Sync Card (Full Width) -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-900">کاتالوگ محصولات</h2>
                    <p class="text-sm text-gray-500">محصولات و وزن آن‌ها را از ووکامرس دریافت کنید. وزن سفارشات بر اساس این اطلاعات محاسبه می‌شود.</p>
                </div>
            </div>
            <button onclick="syncProducts()" id="product-sync-btn" class="px-5 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium text-sm flex items-center gap-2">
                <svg class="w-5 h-5" id="product-sync-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span id="product-sync-text">سینک محصولات</span>
            </button>
        </div>

        @php
            $productsLastSync = \Modules\Warehouse\Models\WarehouseSetting::get('wc_products_last_sync');
            $productCount = \Schema::hasTable('warehouse_products') ? \Modules\Warehouse\Models\WarehouseProduct::count() : 0;
            $zeroWeightCount = \Schema::hasTable('warehouse_products') ? \Modules\Warehouse\Models\WarehouseProduct::where('weight', 0)->count() : 0;
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
            <div class="p-3 bg-gray-50 rounded-lg text-center">
                <div class="text-2xl font-bold text-gray-900">{{ number_format($productCount) }}</div>
                <div class="text-xs text-gray-500 mt-1">محصول ذخیره شده</div>
            </div>
            <div class="p-3 {{ $zeroWeightCount > 0 ? 'bg-yellow-50' : 'bg-green-50' }} rounded-lg text-center">
                <div class="text-2xl font-bold {{ $zeroWeightCount > 0 ? 'text-yellow-700' : 'text-green-700' }}">{{ number_format($zeroWeightCount) }}</div>
                <div class="text-xs {{ $zeroWeightCount > 0 ? 'text-yellow-600' : 'text-green-600' }} mt-1">بدون وزن</div>
            </div>
            <div class="p-3 bg-blue-50 rounded-lg text-center">
                <div class="text-sm font-medium text-blue-900">
                    @if($productsLastSync)
                        {{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($productsLastSync))->format('Y/m/d H:i') }}
                    @else
                        هنوز سینک نشده
                    @endif
                </div>
                <div class="text-xs text-blue-600 mt-1">آخرین سینک محصولات</div>
            </div>
        </div>

        <div id="product-sync-result" class="hidden p-4 rounded-lg text-sm"></div>

        {{-- لیست محصولات بدون وزن --}}
        @if($zeroWeightCount > 0)
        @php
            $zeroWeightProducts = \Modules\Warehouse\Models\WarehouseProduct::where('weight', 0)
                ->whereNotIn('type', ['variation'])
                ->orderBy('name')
                ->get();
            $zeroWeightVariations = \Modules\Warehouse\Models\WarehouseProduct::where('weight', 0)
                ->where('type', 'variation')
                ->orderBy('parent_id')
                ->get();
        @endphp
        <div class="mt-5 pt-5 border-t" x-data="{ showList: true }">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-yellow-700 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    محصولات بدون وزن ({{ $zeroWeightProducts->count() + $zeroWeightVariations->count() }})
                </h3>
                <button @click="showList = !showList" class="text-xs text-gray-500 hover:text-gray-700" x-text="showList ? 'بستن' : 'نمایش لیست'"></button>
            </div>

            <div x-show="showList" x-transition>
                @if($zeroWeightProducts->count() > 0)
                <div class="border border-yellow-200 rounded-xl overflow-hidden mb-4">
                    <table class="w-full text-sm">
                        <thead class="bg-yellow-50">
                            <tr>
                                <th class="px-4 py-2.5 text-right font-medium text-yellow-800 w-10">#</th>
                                <th class="px-4 py-2.5 text-right font-medium text-yellow-800">نام محصول</th>
                                <th class="px-4 py-2.5 text-right font-medium text-yellow-800 w-24">نوع</th>
                                <th class="px-4 py-2.5 text-right font-medium text-yellow-800 w-20">SKU</th>
                                <th class="px-4 py-2.5 text-right font-medium text-yellow-800 w-20">WC ID</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-yellow-100 bg-white">
                            @foreach($zeroWeightProducts as $i => $product)
                            <tr class="hover:bg-yellow-50/50">
                                <td class="px-4 py-2.5 text-gray-400 text-xs">{{ $i + 1 }}</td>
                                <td class="px-4 py-2.5 font-medium text-gray-800">{{ $product->name }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="px-2 py-0.5 rounded text-xs
                                        {{ $product->type === 'simple' ? 'bg-blue-100 text-blue-700' : '' }}
                                        {{ $product->type === 'variable' ? 'bg-purple-100 text-purple-700' : '' }}
                                        {{ in_array($product->type, ['bundle', 'yith_bundle', 'woosb', 'grouped']) ? 'bg-orange-100 text-orange-700' : '' }}
                                    ">{{ $product->type }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-gray-500 text-xs" dir="ltr">{{ $product->sku ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-gray-500 text-xs" dir="ltr">{{ $product->wc_product_id }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                @if($zeroWeightVariations->count() > 0)
                <div x-data="{ showVars: false }">
                    <button @click="showVars = !showVars" class="flex items-center gap-2 text-xs text-yellow-700 hover:text-yellow-900 mb-2">
                        <svg class="w-4 h-4 transition-transform" :class="showVars && 'rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        تنوع‌های بدون وزن ({{ $zeroWeightVariations->count() }} تنوع)
                    </button>
                    <div x-show="showVars" x-transition class="border border-yellow-200 rounded-xl overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-yellow-50">
                                <tr>
                                    <th class="px-4 py-2 text-right font-medium text-yellow-800 w-10">#</th>
                                    <th class="px-4 py-2 text-right font-medium text-yellow-800">نام تنوع</th>
                                    <th class="px-4 py-2 text-right font-medium text-yellow-800 w-20">SKU</th>
                                    <th class="px-4 py-2 text-right font-medium text-yellow-800 w-20">WC ID</th>
                                    <th class="px-4 py-2 text-right font-medium text-yellow-800 w-20">Parent ID</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-yellow-100 bg-white">
                                @foreach($zeroWeightVariations as $i => $var)
                                <tr class="hover:bg-yellow-50/50">
                                    <td class="px-4 py-2 text-gray-400 text-xs">{{ $i + 1 }}</td>
                                    <td class="px-4 py-2 text-gray-700 text-xs">{{ $var->full_name }}</td>
                                    <td class="px-4 py-2 text-gray-500 text-xs" dir="ltr">{{ $var->sku ?: '—' }}</td>
                                    <td class="px-4 py-2 text-gray-500 text-xs" dir="ltr">{{ $var->wc_product_id }}</td>
                                    <td class="px-4 py-2 text-gray-500 text-xs" dir="ltr">{{ $var->parent_id }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        <p class="text-xs text-gray-400 mt-3">نکته: ابتدا محصولات را سینک کنید، سپس سفارشات. وزن هر سفارش از جدول محصولات محاسبه می‌شود.</p>
    </div>
</div>

@push('scripts')
<script>
function showResult(divId, success, message) {
    const div = document.getElementById(divId);
    div.classList.remove('hidden', 'bg-green-50', 'text-green-800', 'bg-red-50', 'text-red-800', 'bg-gray-50', 'text-gray-600');
    if (success) {
        div.classList.add('bg-green-50', 'text-green-800');
    } else {
        div.classList.add('bg-red-50', 'text-red-800');
    }
    // Replace newlines with <br> for multi-line messages
    div.innerHTML = message.replace(/\n/g, '<br>');
}

function testWcConnection() {
    const resultDiv = document.getElementById('wc-test-result');
    resultDiv.classList.remove('hidden', 'bg-green-50', 'text-green-800', 'bg-red-50', 'text-red-800');
    resultDiv.classList.add('bg-gray-50', 'text-gray-600');
    resultDiv.textContent = 'در حال تست اتصال...';

    fetch('{{ route("warehouse.woocommerce.test") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    })
    .then(data => {
        if (data.success) {
            let msg = '<strong>&#10003; ' + data.message + '</strong>';
            if (data.wc_version) msg += '<br>نسخه ووکامرس: ' + data.wc_version;
            showResult('wc-test-result', true, msg);
        } else {
            showResult('wc-test-result', false, data.message);
        }
    })
    .catch(err => {
        showResult('wc-test-result', false, 'خطا: ' + err.message);
    });
}

function syncProducts() {
    const btn = document.getElementById('product-sync-btn');
    const icon = document.getElementById('product-sync-icon');
    const text = document.getElementById('product-sync-text');

    btn.disabled = true;
    icon.classList.add('animate-spin');
    text.textContent = 'در حال سینک محصولات...';
    document.getElementById('product-sync-result').classList.add('hidden');

    fetch('{{ route("warehouse.woocommerce.sync-products") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    .then(r => {
        if (!r.ok) throw new Error('سرور خطا برگرداند (HTTP ' + r.status + ')');
        return r.json();
    })
    .then(data => {
        btn.disabled = false;
        icon.classList.remove('animate-spin');
        text.textContent = 'سینک محصولات';
        showResult('product-sync-result', data.success, data.message);
        if (data.success) {
            setTimeout(() => location.reload(), 2000);
        }
    })
    .catch(err => {
        btn.disabled = false;
        icon.classList.remove('animate-spin');
        text.textContent = 'سینک محصولات';
        showResult('product-sync-result', false, 'خطا: ' + err.message);
    });
}

function toggleStatusSync(enabled) {
    fetch('{{ route("warehouse.woocommerce.toggle-status-sync") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ enabled: enabled }),
    })
    .then(function(r) { return r.json(); })
    .then(function(d) { /* saved */ })
    .catch(function() {
        document.getElementById('wc-status-sync-toggle').checked = !enabled;
    });
}

function syncOrders() {
    const btn = document.getElementById('sync-btn');
    const icon = document.getElementById('sync-icon');
    const text = document.getElementById('sync-text');
    const status = document.getElementById('wc-sync-status').value;

    btn.disabled = true;
    icon.classList.add('animate-spin');
    text.textContent = 'در حال سینک...';
    document.getElementById('wc-sync-result').classList.add('hidden');

    fetch('{{ route("warehouse.woocommerce.sync") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ wc_status: status }),
    })
    .then(r => {
        if (!r.ok) {
            return r.text().then(t => {
                throw new Error('سرور خطا برگرداند (HTTP ' + r.status + ')');
            });
        }
        return r.json();
    })
    .then(data => {
        btn.disabled = false;
        icon.classList.remove('animate-spin');
        text.textContent = 'شروع سینک سفارشات';
        showResult('wc-sync-result', data.success, data.message);
        if (data.success && data.imported > 0) {
            setTimeout(() => location.reload(), 2000);
        }
    })
    .catch(err => {
        btn.disabled = false;
        icon.classList.remove('animate-spin');
        text.textContent = 'شروع سینک سفارشات';
        showResult('wc-sync-result', false, 'خطا: ' + err.message);
    });
}
</script>
@endpush
@endsection
