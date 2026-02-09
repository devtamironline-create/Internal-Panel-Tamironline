<?php

namespace Modules\Warehouse\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\WarehouseOrder;
use Modules\Warehouse\Models\WarehouseOrderItem;
use Modules\Warehouse\Models\WarehouseSetting;
use Modules\Warehouse\Models\WarehouseShippingType;

class WooCommerceService
{
    protected ?string $siteUrl;
    protected ?string $consumerKey;
    protected ?string $consumerSecret;

    public function __construct()
    {
        $this->siteUrl = rtrim(WarehouseSetting::get('wc_site_url', ''), '/');
        $this->consumerKey = WarehouseSetting::get('wc_consumer_key');
        $this->consumerSecret = WarehouseSetting::get('wc_consumer_secret');
    }

    public function isConfigured(): bool
    {
        return !empty($this->siteUrl) && !empty($this->consumerKey) && !empty($this->consumerSecret);
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات ووکامرس کامل نیست.'];
        }

        try {
            $response = Http::timeout(15)
                ->withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get($this->siteUrl . '/wp-json/wc/v3/system_status');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message' => 'اتصال برقرار است.',
                    'store_name' => $data['environment']['site_url'] ?? $this->siteUrl,
                    'wc_version' => $data['environment']['version'] ?? 'نامشخص',
                ];
            }

            return ['success' => false, 'message' => 'خطا در اتصال: ' . $response->status()];
        } catch (\Exception $e) {
            Log::error('WooCommerce connection test failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطا در اتصال: ' . $e->getMessage()];
        }
    }

    public function fetchOrders(int $page = 1, int $perPage = 50, ?string $status = null): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات ووکامرس کامل نیست.', 'orders' => []];
        }

        try {
            $params = [
                'page' => $page,
                'per_page' => $perPage,
                'orderby' => 'date',
                'order' => 'desc',
            ];

            if (!empty($status)) {
                $params['status'] = $status;
            }

            $response = Http::timeout(60)
                ->withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get($this->siteUrl . '/wp-json/wc/v3/orders', $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'orders' => $response->json(),
                    'total' => (int) $response->header('X-WP-Total', 0),
                    'total_pages' => (int) $response->header('X-WP-TotalPages', 0),
                ];
            }

            return ['success' => false, 'message' => 'خطا: ' . $response->status(), 'orders' => []];
        } catch (\Exception $e) {
            Log::error('WooCommerce fetch orders failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطا: ' . $e->getMessage(), 'orders' => []];
        }
    }

    public function syncOrders(?string $wcStatus = 'processing'): array
    {
        // Check if required DB columns exist (migration must be run first)
        if (!\Schema::hasColumn('warehouse_orders', 'wc_order_id')) {
            return [
                'success' => false,
                'message' => 'ابتدا باید مایگریشن اجرا شود. دستور php artisan migrate را روی سرور اجرا کنید.',
            ];
        }

        $result = $this->fetchOrders(1, 100, $wcStatus);

        if (!$result['success']) {
            return $result;
        }

        if (empty($result['orders'])) {
            WarehouseSetting::set('wc_last_sync', now()->toDateTimeString());
            return [
                'success' => true,
                'message' => 'سفارشی با وضعیت انتخاب شده در ووکامرس یافت نشد.',
                'imported' => 0,
                'skipped' => 0,
                'failed' => 0,
            ];
        }

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $lastError = '';

        // جمع‌آوری همه product_id ها از همه سفارشات برای دریافت دسته‌ای وزن
        $allLineItems = collect($result['orders'])->flatMap(fn($o) => $o['line_items'] ?? [])->toArray();
        $productWeights = $this->fetchProductWeights($allLineItems);

        foreach ($result['orders'] as $wcOrder) {
            try {
                $wcOrderId = $wcOrder['id'];
                $orderNumber = 'WC-' . $wcOrderId;

                // Check if already synced (by wc_order_id OR order_number)
                $existingOrder = WarehouseOrder::where('wc_order_id', $wcOrderId)
                    ->orWhere('order_number', $orderNumber)
                    ->first();

                if ($existingOrder) {
                    // If old order exists without wc_order_id, update it with journey fields
                    if (!$existingOrder->wc_order_id) {
                        $shippingType = $this->detectShippingType($wcOrder);
                        $totalWeight = $this->calculateTotalWeight($wcOrder['line_items'] ?? [], $productWeights);

                        $existingOrder->update([
                            'wc_order_id' => $wcOrderId,
                            'wc_order_data' => $wcOrder,
                            'shipping_type' => $shippingType,
                            'barcode' => $existingOrder->barcode ?: WarehouseOrder::generateBarcode(),
                            'total_weight' => $totalWeight,
                            'customer_mobile' => $existingOrder->customer_mobile ?: ($wcOrder['billing']['phone'] ?? null),
                        ]);

                        // Create order items if not already created
                        if ($existingOrder->items()->count() === 0) {
                            $this->createOrderItems($existingOrder, $wcOrder['line_items'] ?? [], $productWeights);
                        }

                        // Set timer if not set
                        if (!$existingOrder->timer_deadline) {
                            $existingOrder->setTimerFromShippingType();
                        }

                        $imported++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                $customerName = trim(($wcOrder['billing']['first_name'] ?? '') . ' ' . ($wcOrder['billing']['last_name'] ?? ''));
                if (empty($customerName)) {
                    $customerName = 'مشتری ووکامرس #' . $wcOrderId;
                }

                // Determine shipping type from WC shipping methods
                $shippingType = $this->detectShippingType($wcOrder);

                // Calculate total weight from product weights
                $totalWeight = $this->calculateTotalWeight($wcOrder['line_items'] ?? [], $productWeights);

                // Build description
                $lineItems = collect($wcOrder['line_items'] ?? [])
                    ->map(fn($item) => ($item['name'] ?? '') . ' x' . ($item['quantity'] ?? 1))
                    ->implode("\n");

                // Create order
                $order = WarehouseOrder::create([
                    'order_number' => $orderNumber,
                    'wc_order_id' => $wcOrderId,
                    'wc_order_data' => $wcOrder,
                    'customer_name' => $customerName,
                    'customer_mobile' => $wcOrder['billing']['phone'] ?? null,
                    'description' => $lineItems ?: null,
                    'status' => WarehouseOrder::STATUS_PENDING,
                    'shipping_type' => $shippingType,
                    'barcode' => WarehouseOrder::generateBarcode(),
                    'total_weight' => $totalWeight,
                    'created_by' => auth()->id(),
                    'notes' => 'مبلغ: ' . number_format((float)($wcOrder['total'] ?? 0)) . ' تومان',
                ]);

                // Set timer based on shipping type
                $order->setTimerFromShippingType();

                // Create order items with product weights
                $this->createOrderItems($order, $wcOrder['line_items'] ?? [], $productWeights);

                $imported++;
            } catch (\Exception $e) {
                Log::error('WooCommerce order sync failed', [
                    'wc_order_id' => $wcOrder['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
                $lastError = $e->getMessage();
                $failed++;
            }
        }

        WarehouseSetting::set('wc_last_sync', now()->toDateTimeString());

        $totalFound = count($result['orders']);
        $message = "از {$totalFound} سفارش: وارد شده: {$imported} | تکراری: {$skipped} | خطا: {$failed}";
        if ($failed > 0 && $lastError) {
            $message .= "\nآخرین خطا: " . \Illuminate\Support\Str::limit($lastError, 150);
        }

        return [
            'success' => $imported > 0 || $failed === 0,
            'message' => $message,
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    public function fetchShippingMethods(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات ووکامرس کامل نیست.', 'methods' => []];
        }

        try {
            $methods = [];

            // Fetch shipping zones
            $zonesResponse = Http::timeout(15)
                ->withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get($this->siteUrl . '/wp-json/wc/v3/shipping/zones');

            if ($zonesResponse->successful()) {
                foreach ($zonesResponse->json() as $zone) {
                    $zoneId = $zone['id'];
                    $zoneName = $zone['name'] ?? 'Zone ' . $zoneId;

                    // Fetch methods for each zone
                    $methodsResponse = Http::timeout(15)
                        ->withBasicAuth($this->consumerKey, $this->consumerSecret)
                        ->get($this->siteUrl . '/wp-json/wc/v3/shipping/zones/' . $zoneId . '/methods');

                    if ($methodsResponse->successful()) {
                        foreach ($methodsResponse->json() as $method) {
                            $methods[] = [
                                'id' => $method['id'] ?? 0,
                                'method_id' => $method['method_id'] ?? '',
                                'method_title' => $method['title'] ?? $method['method_title'] ?? '',
                                'zone_name' => $zoneName,
                                'enabled' => $method['enabled'] ?? false,
                            ];
                        }
                    }
                }
            }

            return ['success' => true, 'methods' => $methods];
        } catch (\Exception $e) {
            Log::error('WooCommerce fetch shipping methods failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطا: ' . $e->getMessage(), 'methods' => []];
        }
    }

    protected function detectShippingType(array $wcOrder): string
    {
        $shippingLines = $wcOrder['shipping_lines'] ?? [];

        // Load saved mappings from settings
        $mappingsJson = WarehouseSetting::get('wc_shipping_mappings');
        $mappings = $mappingsJson ? json_decode($mappingsJson, true) : [];

        foreach ($shippingLines as $line) {
            $methodId = $line['method_id'] ?? '';
            $methodTitle = $line['method_title'] ?? '';

            // Check saved mappings first (by method_id)
            if (!empty($mappings[$methodId])) {
                return $mappings[$methodId];
            }

            // Check saved mappings by method_title
            foreach ($mappings as $key => $mappedType) {
                if (mb_strtolower($key) === mb_strtolower($methodTitle) && !empty($mappedType)) {
                    return $mappedType;
                }
            }

            // Fallback: auto-detect courier/local delivery
            $lowerMethodId = strtolower($methodId);
            $lowerTitle = strtolower($methodTitle);
            if (str_contains($lowerMethodId, 'local') || str_contains($lowerMethodId, 'courier')
                || str_contains($lowerTitle, 'پیک') || str_contains($lowerTitle, 'courier')) {
                return 'courier';
            }
        }

        // Default to post
        return 'post';
    }

    /**
     * دریافت وزن محصولات از WooCommerce Products API
     */
    protected function fetchProductWeights(array $lineItems): array
    {
        $weights = [];

        $productIds = collect($lineItems)
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($productIds)) {
            return $weights;
        }

        // دریافت دسته‌ای محصولات (حداکثر 100 تا در هر درخواست)
        foreach (array_chunk($productIds, 100) as $chunk) {
            try {
                $response = Http::timeout(30)
                    ->withBasicAuth($this->consumerKey, $this->consumerSecret)
                    ->get($this->siteUrl . '/wp-json/wc/v3/products', [
                        'include' => implode(',', $chunk),
                        'per_page' => 100,
                    ]);

                if ($response->successful()) {
                    foreach ($response->json() as $product) {
                        $weights[$product['id']] = (float)($product['weight'] ?? 0);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch product weights from WooCommerce', [
                    'product_ids' => $chunk,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // بررسی محصولات متغیر (variation) — اگر variation_id داشت وزن variation رو بگیر
        $variationIds = collect($lineItems)
            ->filter(fn($item) => !empty($item['variation_id']) && $item['variation_id'] > 0)
            ->pluck('variation_id')
            ->unique()
            ->values()
            ->toArray();

        if (!empty($variationIds)) {
            // گروه‌بندی variation ها بر اساس product_id
            $variationsByProduct = collect($lineItems)
                ->filter(fn($item) => !empty($item['variation_id']) && $item['variation_id'] > 0)
                ->groupBy('product_id');

            foreach ($variationsByProduct as $productId => $items) {
                foreach ($items as $item) {
                    try {
                        $response = Http::timeout(15)
                            ->withBasicAuth($this->consumerKey, $this->consumerSecret)
                            ->get($this->siteUrl . "/wp-json/wc/v3/products/{$productId}/variations/{$item['variation_id']}");

                        if ($response->successful()) {
                            $variation = $response->json();
                            $varWeight = (float)($variation['weight'] ?? 0);
                            if ($varWeight > 0) {
                                // ذخیره با کلید variation_id
                                $weights['var_' . $item['variation_id']] = $varWeight;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to fetch variation weight', [
                            'product_id' => $productId,
                            'variation_id' => $item['variation_id'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        return $weights;
    }

    /**
     * تعیین وزن یک آیتم بر اساس وزن‌های دریافت شده
     */
    protected function getItemWeight(array $item, array $weights): float
    {
        // اول variation رو چک کن
        if (!empty($item['variation_id']) && $item['variation_id'] > 0) {
            $varWeight = $weights['var_' . $item['variation_id']] ?? 0;
            if ($varWeight > 0) {
                return $varWeight;
            }
        }

        // بعد وزن محصول اصلی
        return $weights[$item['product_id'] ?? 0] ?? 0;
    }

    protected function calculateTotalWeight(array $lineItems, array $weights = []): float
    {
        $totalWeight = 0;
        foreach ($lineItems as $item) {
            $weight = !empty($weights) ? $this->getItemWeight($item, $weights) : (float)($item['weight'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 1);
            $totalWeight += $weight * $quantity;
        }
        return round($totalWeight, 2);
    }

    protected function createOrderItems(WarehouseOrder $order, array $lineItems, array $weights = []): void
    {
        foreach ($lineItems as $item) {
            $weight = !empty($weights) ? $this->getItemWeight($item, $weights) : (float)($item['weight'] ?? 0);

            WarehouseOrderItem::create([
                'warehouse_order_id' => $order->id,
                'product_name' => $item['name'] ?? 'محصول',
                'product_sku' => $item['sku'] ?? null,
                'product_barcode' => $item['sku'] ?? null,
                'quantity' => (int)($item['quantity'] ?? 1),
                'weight' => $weight,
                'price' => (float)($item['total'] ?? 0),
                'wc_product_id' => $item['product_id'] ?? null,
            ]);
        }
    }
}
