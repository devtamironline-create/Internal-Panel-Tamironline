<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\OrderLog;
use Modules\Warehouse\Models\WarehouseOrder;
use Modules\Warehouse\Models\WarehouseOrderItem;
use Modules\Warehouse\Models\WarehouseProduct;
use Modules\Warehouse\Models\WarehouseSetting;
use Modules\Warehouse\Models\WarehouseShippingType;
use Modules\Warehouse\Services\WooCommerceService;

class ManualOrderController extends Controller
{
    /**
     * فرم ثبت سفارش دستی
     */
    public function create()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $shippingTypes = WarehouseShippingType::getActiveTypes();

        // روش‌های پرداخت و هزینه ارسال از ووکامرس
        $paymentGateways = [];
        $shippingCosts = [];
        try {
            $wcService = new WooCommerceService();
            if ($wcService->isConfigured()) {
                $paymentGateways = $wcService->fetchPaymentGateways();
                $shippingCosts = $wcService->getShippingCostsMap();
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch WC data', ['error' => $e->getMessage()]);
        }

        // روش‌های پرداخت پیش‌فرض اگه WC در دسترس نبود
        if (empty($paymentGateways)) {
            $paymentGateways = [
                ['id' => 'cod', 'title' => 'پرداخت در محل (پسکرایه)'],
                ['id' => 'bacs', 'title' => 'کارت به کارت'],
                ['id' => 'online', 'title' => 'پرداخت آنلاین'],
            ];
        }

        return view('warehouse::manual-order.create', compact('shippingTypes', 'paymentGateways', 'shippingCosts'));
    }

    /**
     * ذخیره سفارش دستی - ابتدا در WC سپس سینک به پنل
     */
    public function store(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'customer_id' => 'nullable|integer',
            'customer_first_name' => 'required|string|max:255',
            'customer_last_name' => 'required|string|max:255',
            'customer_mobile' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'state' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'required|string|max:1000',
            'postcode' => 'required|string|size:10',
            'shipping_type' => 'required|string|max:50',
            'payment_method' => 'required|string|max:50',
            'payment_method_title' => 'nullable|string|max:255',
            'coupon_code' => 'nullable|string|max:100',
            'shipping_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'customer_note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.wc_product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.name' => 'required|string',
            'items.*.variation_id' => 'nullable|integer',
        ]);

        $customerName = trim($validated['customer_first_name'] . ' ' . $validated['customer_last_name']);

        // ساخت داده سفارش WooCommerce
        $wcOrderData = [
            'status' => 'processing',
            'billing' => [
                'first_name' => $validated['customer_first_name'],
                'last_name' => $validated['customer_last_name'],
                'phone' => $validated['customer_mobile'],
                'email' => $validated['customer_email'] ?? '',
                'address_1' => $validated['address'],
                'city' => $validated['city'],
                'state' => $validated['state'],
                'postcode' => $validated['postcode'],
                'country' => 'IR',
            ],
            'shipping' => [
                'first_name' => $validated['customer_first_name'],
                'last_name' => $validated['customer_last_name'],
                'address_1' => $validated['address'],
                'city' => $validated['city'],
                'state' => $validated['state'],
                'postcode' => $validated['postcode'],
                'country' => 'IR',
            ],
            'payment_method' => $validated['payment_method'],
            'payment_method_title' => $validated['payment_method_title'] ?? $validated['payment_method'],
            'set_paid' => true,
            'line_items' => [],
            'shipping_lines' => [],
            'coupon_lines' => [],
        ];

        // اتصال به مشتری موجود در ووکامرس
        if (!empty($validated['customer_id'])) {
            $wcOrderData['customer_id'] = $validated['customer_id'];
        }

        // آیتم‌های سفارش
        foreach ($validated['items'] as $item) {
            $lineItem = [
                'product_id' => $item['wc_product_id'],
                'quantity' => $item['quantity'],
            ];
            if (!empty($item['variation_id'])) {
                $lineItem['variation_id'] = $item['variation_id'];
            }
            $wcOrderData['line_items'][] = $lineItem;
        }

        // هزینه ارسال
        $shippingType = WarehouseShippingType::where('slug', $validated['shipping_type'])->first();
        $shippingCost = $validated['shipping_cost'] ?? 0;
        $wcOrderData['shipping_lines'][] = [
            'method_id' => 'flat_rate',
            'method_title' => $shippingType ? $shippingType->name : $validated['shipping_type'],
            'total' => (string) $shippingCost,
        ];

        // کد تخفیف
        if (!empty($validated['coupon_code'])) {
            $wcOrderData['coupon_lines'][] = [
                'code' => $validated['coupon_code'],
            ];
        }

        // یادداشت مشتری
        if (!empty($validated['customer_note'])) {
            $wcOrderData['customer_note'] = $validated['customer_note'];
        }

        // متادیتا: مشخص کردن سفارش دستی
        $wcOrderData['meta_data'] = [
            ['key' => '_manual_order', 'value' => 'yes'],
            ['key' => '_manual_order_by', 'value' => auth()->user()->full_name ?? auth()->user()->name],
            ['key' => '_manual_order_date', 'value' => now()->toDateTimeString()],
        ];

        // گنجه شیپینگ متد
        $ganjehMap = [
            'post' => 'post',
            'courier' => 'collection',
            'urgent' => 'express',
            'pickup' => 'pickup',
            'courier_5day' => 'collection',
        ];
        if (isset($ganjehMap[$validated['shipping_type']])) {
            $wcOrderData['meta_data'][] = [
                'key' => '_ganjeh_shipping_method',
                'value' => $ganjehMap[$validated['shipping_type']],
            ];
        }

        // ارسال به ووکامرس
        $wcCreated = false;
        $wcResult = null;

        try {
            $wcService = new WooCommerceService();
            if ($wcService->isConfigured()) {
                $wcResult = $wcService->createOrder($wcOrderData);
                $wcCreated = $wcResult['success'] ?? false;
            }
        } catch (\Throwable $e) {
            Log::warning('WC create order failed in store', ['error' => $e->getMessage()]);
        }

        try {
            if ($wcCreated) {
                // سفارش در WC ساخته شد - سینک به پنل
                $wcOrder = $wcResult['order'];
                $wcOrderId = $wcOrder['id'];

                $order = $this->createLocalOrder($wcOrder, $validated, $customerName);

                OrderLog::log($order, OrderLog::ACTION_CREATED, 'سفارش دستی ایجاد شد و در ووکامرس ثبت شد (WC #' . $wcOrderId . ')');

                if ($request->expectsJson()) {
                    return response()->json(['redirect' => route('warehouse.show', $order)]);
                }
                return redirect()->route('warehouse.show', $order)
                    ->with('success', 'سفارش دستی با موفقیت ثبت شد. شماره سفارش ووکامرس: #' . ($wcOrder['number'] ?? $wcOrderId));
            } else {
                // WC در دسترس نبود - ذخیره لوکال با علامت سینک نشده
                $order = $this->createLocalOrderWithoutWc($validated, $customerName, $wcOrderData);

                $errorMsg = $wcResult ? ($wcResult['message'] ?? '') : 'ووکامرس در دسترس نیست';
                OrderLog::log($order, OrderLog::ACTION_CREATED, 'سفارش دستی ایجاد شد (بدون سینک WC: ' . $errorMsg . ')');

                if ($request->expectsJson()) {
                    return response()->json(['redirect' => route('warehouse.show', $order)]);
                }
                return redirect()->route('warehouse.show', $order)
                    ->with('warning', 'سفارش ثبت شد ولی سینک با ووکامرس انجام نشد.');
            }
        } catch (\Throwable $e) {
            Log::error('Manual order store failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'خطا در ثبت سفارش: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->withInput()
                ->with('error', 'خطا در ثبت سفارش: ' . $e->getMessage());
        }
    }

    /**
     * ساخت سفارش محلی از داده ووکامرس
     */
    protected function createLocalOrder(array $wcOrder, array $validated, string $customerName): WarehouseOrder
    {
        $wcService = new WooCommerceService();
        $shippingType = $wcService->detectShippingType($wcOrder);

        // محاسبه وزن کل
        $totalWeight = 0;
        $filteredItems = $wcService->filterBundleChildren($wcOrder['line_items'] ?? []);

        $order = WarehouseOrder::create([
            'order_number' => 'WC-' . $wcOrder['id'],
            'customer_name' => $customerName,
            'customer_mobile' => $validated['customer_mobile'],
            'description' => collect($filteredItems)->map(fn($i) => ($i['name'] ?? '') . ' x' . ($i['quantity'] ?? 1))->implode("\n"),
            'status' => WarehouseOrder::STATUS_PENDING,
            'shipping_type' => $shippingType,
            'order_source' => WarehouseOrder::SOURCE_MANUAL,
            'created_by' => auth()->id(),
            'wc_order_id' => $wcOrder['id'],
            'wc_order_data' => $wcOrder,
            'barcode' => WarehouseOrder::generateBarcode(),
            'notes' => $validated['notes'] ?? null,
        ]);

        // ایجاد آیتم‌ها
        $productIds = collect($validated['items'])->pluck('wc_product_id')->toArray();
        $variationIds = collect($validated['items'])->pluck('variation_id')->filter()->toArray();
        $weightsMap = WarehouseProduct::getWeightsMap($productIds, $variationIds);
        $dimensionsMap = WarehouseProduct::getDimensionsMap($productIds, $variationIds);

        foreach ($validated['items'] as $item) {
            $lookupId = !empty($item['variation_id']) ? $item['variation_id'] : $item['wc_product_id'];
            $weight = $weightsMap[$lookupId] ?? ($weightsMap[$item['wc_product_id']] ?? 0);
            $dims = $dimensionsMap[$lookupId] ?? ($dimensionsMap[$item['wc_product_id']] ?? []);

            $product = WarehouseProduct::where('wc_product_id', $lookupId)->first()
                ?? WarehouseProduct::where('wc_product_id', $item['wc_product_id'])->first();

            WarehouseOrderItem::create([
                'warehouse_order_id' => $order->id,
                'product_name' => $item['name'],
                'product_sku' => $product?->sku,
                'wc_product_id' => $item['wc_product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'weight' => $weight,
                'length' => $dims['length'] ?? 0,
                'width' => $dims['width'] ?? 0,
                'height' => $dims['height'] ?? 0,
            ]);

            $totalWeight += $weight * $item['quantity'];
        }

        $order->update(['total_weight' => $totalWeight]);
        $order->setTimerFromShippingType();

        return $order;
    }

    /**
     * ساخت سفارش لوکال بدون ووکامرس (برای سینک بعدی)
     */
    protected function createLocalOrderWithoutWc(array $validated, string $customerName, array $wcOrderData): WarehouseOrder
    {
        $order = WarehouseOrder::create([
            'order_number' => WarehouseOrder::generateOrderNumber(),
            'customer_name' => $customerName,
            'customer_mobile' => $validated['customer_mobile'],
            'description' => collect($validated['items'])->map(fn($i) => ($i['name'] ?? '') . ' x' . ($i['quantity'] ?? 1))->implode("\n"),
            'status' => WarehouseOrder::STATUS_PENDING,
            'shipping_type' => $validated['shipping_type'],
            'order_source' => WarehouseOrder::SOURCE_MANUAL,
            'created_by' => auth()->id(),
            'wc_order_data' => array_merge($wcOrderData, ['_pending_wc_sync' => true]),
            'barcode' => WarehouseOrder::generateBarcode(),
            'notes' => $validated['notes'] ?? null,
        ]);

        // آیتم‌ها
        $productIds = collect($validated['items'])->pluck('wc_product_id')->toArray();
        $variationIds = collect($validated['items'])->pluck('variation_id')->filter()->toArray();
        $weightsMap = WarehouseProduct::getWeightsMap($productIds, $variationIds);
        $dimensionsMap = WarehouseProduct::getDimensionsMap($productIds, $variationIds);
        $totalWeight = 0;

        foreach ($validated['items'] as $item) {
            $lookupId = !empty($item['variation_id']) ? $item['variation_id'] : $item['wc_product_id'];
            $weight = $weightsMap[$lookupId] ?? ($weightsMap[$item['wc_product_id']] ?? 0);
            $dims = $dimensionsMap[$lookupId] ?? ($dimensionsMap[$item['wc_product_id']] ?? []);

            $product = WarehouseProduct::where('wc_product_id', $lookupId)->first()
                ?? WarehouseProduct::where('wc_product_id', $item['wc_product_id'])->first();

            WarehouseOrderItem::create([
                'warehouse_order_id' => $order->id,
                'product_name' => $item['name'],
                'product_sku' => $product?->sku,
                'wc_product_id' => $item['wc_product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'weight' => $weight,
                'length' => $dims['length'] ?? 0,
                'width' => $dims['width'] ?? 0,
                'height' => $dims['height'] ?? 0,
            ]);

            $totalWeight += $weight * $item['quantity'];
        }

        $order->update(['total_weight' => $totalWeight]);
        $order->setTimerFromShippingType();

        return $order;
    }

    /**
     * فرم ویرایش سفارش دستی
     */
    public function edit(WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        // فقط سفارشات pending قابل ویرایش هستن
        if ($order->status !== WarehouseOrder::STATUS_PENDING) {
            return redirect()->route('warehouse.show', $order)
                ->with('error', 'فقط سفارشات در حال پردازش قابل ویرایش هستند.');
        }

        $order->load(['items']);
        $shippingTypes = WarehouseShippingType::getActiveTypes();

        $paymentGateways = [];
        $shippingCosts = [];
        try {
            $wcService = new WooCommerceService();
            if ($wcService->isConfigured()) {
                $paymentGateways = $wcService->fetchPaymentGateways();
                $shippingCosts = $wcService->getShippingCostsMap();
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch WC data', ['error' => $e->getMessage()]);
        }

        if (empty($paymentGateways)) {
            $paymentGateways = [
                ['id' => 'cod', 'title' => 'پرداخت در محل (پسکرایه)'],
                ['id' => 'bacs', 'title' => 'کارت به کارت'],
                ['id' => 'online', 'title' => 'پرداخت آنلاین'],
            ];
        }

        // اطلاعات فعلی سفارش
        $wcData = $order->wc_order_data ?? [];
        $billing = $wcData['billing'] ?? [];
        $shipping = $wcData['shipping'] ?? $billing;

        $orderInfo = [
            'first_name' => $billing['first_name'] ?? '',
            'last_name' => $billing['last_name'] ?? '',
            'phone' => $billing['phone'] ?? $order->customer_mobile,
            'email' => $billing['email'] ?? '',
            'state' => $shipping['state'] ?? '',
            'city' => $shipping['city'] ?? '',
            'address' => $shipping['address_1'] ?? '',
            'postcode' => $shipping['postcode'] ?? '',
            'payment_method' => $wcData['payment_method'] ?? '',
            'payment_method_title' => $wcData['payment_method_title'] ?? '',
            'customer_note' => $wcData['customer_note'] ?? '',
            'shipping_cost' => collect($wcData['shipping_lines'] ?? [])->sum(fn($l) => (float)($l['total'] ?? 0)),
        ];

        return view('warehouse::manual-order.edit', compact('order', 'shippingTypes', 'paymentGateways', 'shippingCosts', 'orderInfo'));
    }

    /**
     * ذخیره ویرایش سفارش
     */
    public function update(Request $request, WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        if ($order->status !== WarehouseOrder::STATUS_PENDING) {
            return redirect()->route('warehouse.show', $order)
                ->with('error', 'فقط سفارشات در حال پردازش قابل ویرایش هستند.');
        }

        $validated = $request->validate([
            'customer_first_name' => 'required|string|max:255',
            'customer_last_name' => 'required|string|max:255',
            'customer_mobile' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'state' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'required|string|max:1000',
            'postcode' => 'required|string|size:10',
            'shipping_type' => 'required|string|max:50',
            'payment_method' => 'required|string|max:50',
            'payment_method_title' => 'nullable|string|max:255',
            'coupon_code' => 'nullable|string|max:100',
            'shipping_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'customer_note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.wc_product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.name' => 'required|string',
            'items.*.variation_id' => 'nullable|integer',
        ]);

        $customerName = trim($validated['customer_first_name'] . ' ' . $validated['customer_last_name']);

        // بروزرسانی اطلاعات محلی
        $order->update([
            'customer_name' => $customerName,
            'customer_mobile' => $validated['customer_mobile'],
            'shipping_type' => $validated['shipping_type'],
            'notes' => $validated['notes'] ?? null,
            'description' => collect($validated['items'])->map(fn($i) => ($i['name'] ?? '') . ' x' . ($i['quantity'] ?? 1))->implode("\n"),
        ]);

        // حذف آیتم‌های قبلی و ایجاد جدید
        $order->items()->delete();

        $productIds = collect($validated['items'])->pluck('wc_product_id')->toArray();
        $variationIds = collect($validated['items'])->pluck('variation_id')->filter()->toArray();
        $weightsMap = WarehouseProduct::getWeightsMap($productIds, $variationIds);
        $dimensionsMap = WarehouseProduct::getDimensionsMap($productIds, $variationIds);
        $totalWeight = 0;

        foreach ($validated['items'] as $item) {
            $lookupId = !empty($item['variation_id']) ? $item['variation_id'] : $item['wc_product_id'];
            $weight = $weightsMap[$lookupId] ?? ($weightsMap[$item['wc_product_id']] ?? 0);
            $dims = $dimensionsMap[$lookupId] ?? ($dimensionsMap[$item['wc_product_id']] ?? []);

            $product = WarehouseProduct::where('wc_product_id', $lookupId)->first()
                ?? WarehouseProduct::where('wc_product_id', $item['wc_product_id'])->first();

            WarehouseOrderItem::create([
                'warehouse_order_id' => $order->id,
                'product_name' => $item['name'],
                'product_sku' => $product?->sku,
                'wc_product_id' => $item['wc_product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'weight' => $weight,
                'length' => $dims['length'] ?? 0,
                'width' => $dims['width'] ?? 0,
                'height' => $dims['height'] ?? 0,
            ]);

            $totalWeight += $weight * $item['quantity'];
        }

        $order->update(['total_weight' => $totalWeight]);

        // سینک با ووکامرس
        if ($order->wc_order_id) {
            $shippingType = WarehouseShippingType::where('slug', $validated['shipping_type'])->first();

            $wcUpdateData = [
                'billing' => [
                    'first_name' => $validated['customer_first_name'],
                    'last_name' => $validated['customer_last_name'],
                    'phone' => $validated['customer_mobile'],
                    'email' => $validated['customer_email'] ?? '',
                    'address_1' => $validated['address'],
                    'city' => $validated['city'],
                    'state' => $validated['state'],
                    'postcode' => $validated['postcode'],
                    'country' => 'IR',
                ],
                'shipping' => [
                    'first_name' => $validated['customer_first_name'],
                    'last_name' => $validated['customer_last_name'],
                    'address_1' => $validated['address'],
                    'city' => $validated['city'],
                    'state' => $validated['state'],
                    'postcode' => $validated['postcode'],
                    'country' => 'IR',
                ],
                'payment_method' => $validated['payment_method'],
                'payment_method_title' => $validated['payment_method_title'] ?? $validated['payment_method'],
                'line_items' => [],
                'shipping_lines' => [
                    [
                        'method_id' => 'flat_rate',
                        'method_title' => $shippingType ? $shippingType->name : $validated['shipping_type'],
                        'total' => (string)($validated['shipping_cost'] ?? 0),
                    ]
                ],
            ];

            foreach ($validated['items'] as $item) {
                $lineItem = [
                    'product_id' => $item['wc_product_id'],
                    'quantity' => $item['quantity'],
                ];
                if (!empty($item['variation_id'])) {
                    $lineItem['variation_id'] = $item['variation_id'];
                }
                $wcUpdateData['line_items'][] = $lineItem;
            }

            if (!empty($validated['coupon_code'])) {
                $wcUpdateData['coupon_lines'] = [['code' => $validated['coupon_code']]];
            }

            $wcService = new WooCommerceService();
            $updateResult = $wcService->updateWcOrder($order->wc_order_id, $wcUpdateData);

            if ($updateResult['success']) {
                // بروزرسانی wc_order_data محلی
                $order->update(['wc_order_data' => $updateResult['order']]);
                OrderLog::log($order, OrderLog::ACTION_EDITED, 'سفارش دستی ویرایش و در ووکامرس بروزرسانی شد');
            } else {
                OrderLog::log($order, OrderLog::ACTION_EDITED, 'سفارش ویرایش شد اما سینک ووکامرس ناموفق: ' . ($updateResult['message'] ?? ''));
            }
        } else {
            // بروزرسانی wc_order_data محلی
            $wcData = $order->wc_order_data ?? [];
            $wcData['billing'] = [
                'first_name' => $validated['customer_first_name'],
                'last_name' => $validated['customer_last_name'],
                'phone' => $validated['customer_mobile'],
                'email' => $validated['customer_email'] ?? '',
                'address_1' => $validated['address'],
                'city' => $validated['city'],
                'state' => $validated['state'],
                'postcode' => $validated['postcode'],
                'country' => 'IR',
            ];
            $wcData['shipping'] = $wcData['billing'];
            $wcData['payment_method'] = $validated['payment_method'];
            $order->update(['wc_order_data' => $wcData]);

            OrderLog::log($order, OrderLog::ACTION_EDITED, 'سفارش دستی ویرایش شد (بدون سینک WC)');
        }

        if ($request->expectsJson()) {
            return response()->json(['redirect' => route('warehouse.show', $order)]);
        }
        return redirect()->route('warehouse.show', $order)
            ->with('success', 'سفارش با موفقیت ویرایش شد.');
    }

    /**
     * جستجوی محصولات (AJAX)
     */
    public function searchProducts(Request $request)
    {
        $search = $request->get('q', '');
        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $products = WarehouseProduct::where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%")
                  ->orWhere('wc_product_id', $search);
            })
            ->inStock()
            ->where('status', 'publish')
            ->whereNotIn('type', ['variable', 'grouped']) // فقط محصولات قابل خرید
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->wc_product_id,
                    'name' => $product->full_name,
                    'sku' => $product->sku,
                    'price' => (float) $product->price,
                    'weight' => (float) $product->weight,
                    'stock_quantity' => $product->stock_quantity,
                    'stock_status' => $product->stock_status,
                    'type' => $product->type,
                    'parent_id' => $product->parent_id,
                    'is_variation' => $product->type === 'variation',
                ];
            });

        return response()->json($products);
    }

    /**
     * جستجوی مشتریان ووکامرس (AJAX)
     */
    public function searchCustomers(Request $request)
    {
        $search = $request->get('q', '');
        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $wcService = new WooCommerceService();
        if (!$wcService->isConfigured()) {
            return response()->json([]);
        }

        $customers = $wcService->searchCustomers($search);

        $results = collect($customers)->map(function ($customer) {
            return [
                'id' => $customer['id'],
                'first_name' => $customer['first_name'] ?? '',
                'last_name' => $customer['last_name'] ?? '',
                'email' => $customer['email'] ?? '',
                'phone' => $customer['billing']['phone'] ?? '',
                'billing' => $customer['billing'] ?? [],
                'shipping' => $customer['shipping'] ?? [],
            ];
        });

        return response()->json($results);
    }

    /**
     * اعتبارسنجی کد تخفیف (AJAX)
     */
    public function validateCoupon(Request $request)
    {
        $code = $request->input('code', '');
        if (empty($code)) {
            return response()->json(['valid' => false, 'message' => 'کد تخفیف را وارد کنید.']);
        }

        $wcService = new WooCommerceService();
        return response()->json($wcService->validateCoupon($code));
    }
}
