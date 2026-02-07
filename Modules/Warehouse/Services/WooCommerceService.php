<?php

namespace Modules\Warehouse\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\WooOrder;
use Modules\Warehouse\Models\WooSyncLog;

class WooCommerceService
{
    protected ?Client $client = null;
    protected string $baseUrl;
    protected string $consumerKey;
    protected string $consumerSecret;
    protected bool $isConfigured = false;

    public function __construct()
    {
        $this->baseUrl = config('warehouse.woocommerce.store_url', '');
        $this->consumerKey = config('warehouse.woocommerce.consumer_key', '');
        $this->consumerSecret = config('warehouse.woocommerce.consumer_secret', '');

        if ($this->baseUrl && $this->consumerKey && $this->consumerSecret) {
            $this->isConfigured = true;
            $this->initClient();
        }
    }

    protected function initClient(): void
    {
        $baseUri = rtrim($this->baseUrl, '/') . '/wp-json/wc/v3/';

        $this->client = new Client([
            'base_uri' => $baseUri,
            'timeout' => 30,
            'auth' => [$this->consumerKey, $this->consumerSecret],
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function isConfigured(): bool
    {
        return $this->isConfigured;
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured) {
            return [
                'success' => false,
                'message' => 'تنظیمات WooCommerce انجام نشده است.',
            ];
        }

        try {
            $response = $this->client->get('');
            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'success' => true,
                'message' => 'اتصال برقرار شد.',
                'store_name' => $data['store']['name'] ?? null,
                'wc_version' => $data['version'] ?? null,
            ];
        } catch (GuzzleException $e) {
            Log::error('WooCommerce connection test failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'خطا در اتصال: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch orders from WooCommerce
     */
    public function getOrders(array $params = []): array
    {
        if (!$this->isConfigured) {
            throw new \Exception('تنظیمات WooCommerce انجام نشده است.');
        }

        $defaultParams = [
            'per_page' => 100,
            'page' => 1,
            'orderby' => 'date',
            'order' => 'desc',
        ];

        $queryParams = array_merge($defaultParams, $params);

        try {
            $response = $this->client->get('orders', [
                'query' => $queryParams,
            ]);

            $orders = json_decode($response->getBody()->getContents(), true);
            $totalPages = (int) $response->getHeader('X-WP-TotalPages')[0] ?? 1;
            $totalOrders = (int) $response->getHeader('X-WP-Total')[0] ?? count($orders);

            return [
                'success' => true,
                'orders' => $orders,
                'total' => $totalOrders,
                'total_pages' => $totalPages,
                'current_page' => $queryParams['page'],
            ];
        } catch (GuzzleException $e) {
            Log::error('WooCommerce getOrders failed', [
                'error' => $e->getMessage(),
                'params' => $queryParams,
            ]);

            throw new \Exception('خطا در دریافت سفارشات: ' . $e->getMessage());
        }
    }

    /**
     * Fetch single order from WooCommerce
     */
    public function getOrder(int $orderId): array
    {
        if (!$this->isConfigured) {
            throw new \Exception('تنظیمات WooCommerce انجام نشده است.');
        }

        try {
            $response = $this->client->get("orders/{$orderId}");
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('WooCommerce getOrder failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
            ]);

            throw new \Exception('خطا در دریافت سفارش: ' . $e->getMessage());
        }
    }

    /**
     * Update order status in WooCommerce
     */
    public function updateOrderStatus(int $orderId, string $status): array
    {
        if (!$this->isConfigured) {
            throw new \Exception('تنظیمات WooCommerce انجام نشده است.');
        }

        try {
            $response = $this->client->put("orders/{$orderId}", [
                'json' => ['status' => $status],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('WooCommerce updateOrderStatus failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
                'status' => $status,
            ]);

            throw new \Exception('خطا در بروزرسانی وضعیت سفارش: ' . $e->getMessage());
        }
    }

    /**
     * Add note to order in WooCommerce
     */
    public function addOrderNote(int $orderId, string $note, bool $customerNote = false): array
    {
        if (!$this->isConfigured) {
            throw new \Exception('تنظیمات WooCommerce انجام نشده است.');
        }

        try {
            $response = $this->client->post("orders/{$orderId}/notes", [
                'json' => [
                    'note' => $note,
                    'customer_note' => $customerNote,
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('WooCommerce addOrderNote failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
            ]);

            throw new \Exception('خطا در افزودن یادداشت: ' . $e->getMessage());
        }
    }

    /**
     * Sync all orders from WooCommerce
     */
    public function syncOrders(?int $userId = null, array $params = []): array
    {
        $log = WooSyncLog::startLog(WooSyncLog::ACTION_SYNC_ORDERS, $userId);

        $created = 0;
        $updated = 0;
        $failed = 0;
        $processed = 0;

        try {
            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $result = $this->getOrders(array_merge($params, ['page' => $page]));

                foreach ($result['orders'] as $orderData) {
                    try {
                        $existingOrder = WooOrder::where('woo_order_id', $orderData['id'])->first();
                        WooOrder::createFromWooCommerce($orderData);

                        if ($existingOrder) {
                            $updated++;
                        } else {
                            $created++;
                        }
                        $processed++;
                    } catch (\Exception $e) {
                        $failed++;
                        Log::error('Failed to sync order', [
                            'woo_order_id' => $orderData['id'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $hasMore = $page < $result['total_pages'];
                $page++;
            }

            $log->complete($processed, $created, $updated, $failed);

            return [
                'success' => true,
                'processed' => $processed,
                'created' => $created,
                'updated' => $updated,
                'failed' => $failed,
            ];
        } catch (\Exception $e) {
            $log->fail($e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'processed' => $processed,
                'created' => $created,
                'updated' => $updated,
                'failed' => $failed,
            ];
        }
    }

    /**
     * Sync single order from WooCommerce
     */
    public function syncOrder(int $wooOrderId, ?int $userId = null): array
    {
        $log = WooSyncLog::startLog(
            WooSyncLog::ACTION_SYNC_SINGLE_ORDER,
            $userId,
            'order',
            $wooOrderId
        );

        try {
            $orderData = $this->getOrder($wooOrderId);
            $existingOrder = WooOrder::where('woo_order_id', $wooOrderId)->first();
            $order = WooOrder::createFromWooCommerce($orderData);

            $log->complete(1, $existingOrder ? 0 : 1, $existingOrder ? 1 : 0, 0);

            return [
                'success' => true,
                'order' => $order,
                'was_new' => !$existingOrder,
            ];
        } catch (\Exception $e) {
            $log->fail($e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update order status in WooCommerce and local database
     */
    public function updateStatus(WooOrder $order, string $newStatus, ?int $userId = null): array
    {
        $log = WooSyncLog::startLog(
            WooSyncLog::ACTION_UPDATE_STATUS,
            $userId,
            'order',
            $order->woo_order_id
        );

        try {
            // Update in WooCommerce
            $this->updateOrderStatus($order->woo_order_id, $newStatus);

            // Update locally
            $order->update([
                'status' => $newStatus,
                'last_synced_at' => now(),
            ]);

            $log->complete(1, 0, 1, 0);

            return [
                'success' => true,
                'order' => $order->fresh(),
            ];
        } catch (\Exception $e) {
            $log->fail($e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get recent orders with specific statuses for dashboard
     */
    public function syncRecentOrders(?int $userId = null, int $days = 7): array
    {
        $after = now()->subDays($days)->toIso8601String();

        return $this->syncOrders($userId, [
            'after' => $after,
            'status' => 'any',
        ]);
    }

    /**
     * Sync only processing orders
     */
    public function syncProcessingOrders(?int $userId = null): array
    {
        return $this->syncOrders($userId, [
            'status' => 'processing',
        ]);
    }
}
