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
        $result = $this->fetchOrders(1, 100, $wcStatus);

        if (!$result['success']) {
            return $result;
        }

        $imported = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($result['orders'] as $wcOrder) {
            try {
                $wcOrderId = $wcOrder['id'];

                // Skip if already synced
                $exists = WarehouseOrder::where('wc_order_id', $wcOrderId)->exists();
                if ($exists) {
                    $skipped++;
                    continue;
                }

                $customerName = trim(($wcOrder['billing']['first_name'] ?? '') . ' ' . ($wcOrder['billing']['last_name'] ?? ''));
                if (empty($customerName)) {
                    $customerName = 'مشتری ووکامرس #' . $wcOrderId;
                }

                // Determine shipping type from WC shipping methods
                $shippingType = $this->detectShippingType($wcOrder);

                // Calculate total weight from line items
                $totalWeight = $this->calculateTotalWeight($wcOrder['line_items'] ?? []);

                // Build description
                $lineItems = collect($wcOrder['line_items'] ?? [])
                    ->map(fn($item) => ($item['name'] ?? '') . ' x' . ($item['quantity'] ?? 1))
                    ->implode("\n");

                // Create order
                $order = WarehouseOrder::create([
                    'order_number' => 'WC-' . $wcOrderId,
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

                // Create order items
                $this->createOrderItems($order, $wcOrder['line_items'] ?? []);

                $imported++;
            } catch (\Exception $e) {
                Log::error('WooCommerce order sync failed', [
                    'wc_order_id' => $wcOrder['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        WarehouseSetting::set('wc_last_sync', now()->toDateTimeString());

        return [
            'success' => true,
            'message' => "سینک انجام شد. وارد شده: {$imported} | تکراری: {$skipped} | خطا: {$failed}",
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    protected function detectShippingType(array $wcOrder): string
    {
        $shippingLines = $wcOrder['shipping_lines'] ?? [];

        foreach ($shippingLines as $line) {
            $methodId = strtolower($line['method_id'] ?? '');
            $methodTitle = strtolower($line['method_title'] ?? '');

            // Check for courier/local delivery
            if (str_contains($methodId, 'local') || str_contains($methodId, 'courier')
                || str_contains($methodTitle, 'پیک') || str_contains($methodTitle, 'courier')) {
                return 'courier';
            }
        }

        // Default to post
        return 'post';
    }

    protected function calculateTotalWeight(array $lineItems): float
    {
        $totalWeight = 0;
        foreach ($lineItems as $item) {
            $weight = (float)($item['weight'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 1);
            $totalWeight += $weight * $quantity;
        }
        return $totalWeight;
    }

    protected function createOrderItems(WarehouseOrder $order, array $lineItems): void
    {
        foreach ($lineItems as $item) {
            WarehouseOrderItem::create([
                'warehouse_order_id' => $order->id,
                'product_name' => $item['name'] ?? 'محصول',
                'product_sku' => $item['sku'] ?? null,
                'product_barcode' => $item['sku'] ?? null,
                'quantity' => (int)($item['quantity'] ?? 1),
                'weight' => (float)($item['weight'] ?? 0),
                'price' => (float)($item['total'] ?? 0),
                'wc_product_id' => $item['product_id'] ?? null,
            ]);
        }
    }
}
