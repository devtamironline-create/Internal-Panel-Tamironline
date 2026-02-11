<?php

namespace Modules\Warehouse\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\WarehouseSetting;

class TapinService
{
    protected string $apiUrl;
    protected ?string $apiKey;
    protected ?string $shopId;

    public function __construct()
    {
        $this->apiUrl = rtrim(WarehouseSetting::get('tapin_api_url', 'https://api.tapin.ir'), '/');
        $this->apiKey = WarehouseSetting::get('tapin_api_key');
        $this->shopId = WarehouseSetting::get('tapin_shop_id');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->shopId);
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    protected function endpoint(string $path): string
    {
        return $this->apiUrl . '/api/v2/' . ltrim($path, '/');
    }

    protected function endpointV1(string $path): string
    {
        return $this->apiUrl . '/api/v1/webservice/' . ltrim($path, '/');
    }

    // ==========================================
    // تست اتصال
    // ==========================================

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات تاپین کامل نیست (API Key یا Shop ID وارد نشده)'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint('public/provinces/'));

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'اتصال به تاپین برقرار است.',
                ];
            }

            return ['success' => false, 'message' => 'خطا در اتصال: HTTP ' . $response->status() . ' - ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Tapin connection test failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطا در اتصال: ' . $e->getMessage()];
        }
    }

    // ==========================================
    // شهرها و استان‌ها
    // ==========================================

    public function getProvinces(): array
    {
        $cached = Cache::get('tapin_provinces');
        if (!empty($cached)) return $cached;

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint('public/provinces/'));

            if ($response->successful()) {
                $data = $response->json()['results'] ?? $response->json()['data'] ?? $response->json();
                if (!empty($data) && is_array($data)) {
                    Cache::put('tapin_provinces', $data, 86400);
                    return $data;
                }
            }
            return [];
        } catch (\Exception $e) {
            Log::error('Tapin getProvinces error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getCities(?string $provinceCode = null): array
    {
        if (!$provinceCode) return [];

        $cacheKey = 'tapin_cities_' . $provinceCode;
        $cached = Cache::get($cacheKey);
        if (!empty($cached)) return $cached;

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint("public/cities/{$provinceCode}/"));

            if ($response->successful()) {
                $data = $response->json()['results'] ?? $response->json()['data'] ?? $response->json();
                if (!empty($data) && is_array($data)) {
                    Cache::put($cacheKey, $data, 86400);
                    return $data;
                }
            }
            return [];
        } catch (\Exception $e) {
            Log::error('Tapin getCities error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ==========================================
    // فروشگاه‌ها
    // ==========================================

    public function getShops(): array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint('public/shops/'));

            if ($response->successful()) {
                return $response->json();
            }

            return ['success' => false, 'message' => 'خطا: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Tapin getShops error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getShopDetails(): array
    {
        if (empty($this->shopId)) {
            return ['success' => false, 'message' => 'Shop ID وارد نشده.'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint("public/shops/{$this->shopId}/"));

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return ['success' => false, 'message' => 'خطا: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Tapin getShopDetails error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getShopCredit(): array
    {
        if (empty($this->shopId)) {
            return ['success' => false, 'message' => 'Shop ID وارد نشده.'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint("public/shops/{$this->shopId}/credit/"));

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return ['success' => false, 'message' => 'خطا: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Tapin getShopCredit error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ==========================================
    // استعلام قیمت
    // ==========================================

    public function checkPrice(array $orderData): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات تاپین کامل نیست.'];
        }

        try {
            $payload = $this->buildOrderPayload($orderData);

            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->post($this->endpointV1('order/check-price'), $payload);

            $result = $response->json() ?? [];
            Log::info('Tapin checkPrice response', ['status' => $response->status(), 'body' => $result]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $result];
            }

            return ['success' => false, 'message' => 'خطا در استعلام قیمت: ' . ($result['message'] ?? $response->body())];
        } catch (\Exception $e) {
            Log::error('Tapin checkPrice error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ==========================================
    // ثبت سفارش
    // ==========================================

    public function createOrder(array $orderData): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات تاپین کامل نیست (API Key یا Shop ID وارد نشده)'];
        }

        try {
            $payload = $this->buildOrderPayload($orderData);

            Log::info('Tapin createOrder payload', $payload);

            $response = Http::timeout(30)
                ->withHeaders($this->getHeaders())
                ->post($this->endpointV1('order/register'), $payload);

            $result = $response->json() ?? [];
            Log::info('Tapin createOrder response', ['status' => $response->status(), 'body' => $result]);

            if ($response->successful() && ($result['status'] ?? false) !== false) {
                $data = $result['data'] ?? $result;
                $trackingCode = $data['tracking_code'] ?? $data['barcode'] ?? null;
                $orderId = $data['id'] ?? $data['order_id'] ?? null;

                return [
                    'success' => true,
                    'message' => $result['message'] ?? 'سفارش ثبت شد',
                    'data' => [
                        'order_id' => $orderId,
                        'tracking_code' => $trackingCode,
                        'barcode' => $data['barcode'] ?? $trackingCode,
                        'send_price' => $data['send_price'] ?? null,
                        'tax' => $data['tax'] ?? null,
                        'total' => $data['total'] ?? null,
                    ],
                ];
            }

            return ['success' => false, 'message' => 'خطا در ثبت سفارش: ' . ($result['message'] ?? $response->body())];
        } catch (\Exception $e) {
            Log::error('Tapin createOrder error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Alias
     */
    public function createShipment(array $data): array
    {
        return $this->createOrder($data);
    }

    // ==========================================
    // لیست سفارشات
    // ==========================================

    public function getOrders(int $page = 1, int $count = 20, ?string $status = null): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات تاپین کامل نیست.'];
        }

        try {
            $payload = [
                'shop_id' => $this->shopId,
                'page' => $page,
                'count' => $count,
            ];
            if ($status) {
                $payload['status'] = $status;
            }

            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->post($this->endpointV1('order/list'), $payload);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return ['success' => false, 'message' => 'خطا: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Tapin getOrders error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ==========================================
    // رهگیری
    // ==========================================

    public function trackShipment(string $trackingCode): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات تاپین کامل نیست.'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpointV1("order/change-status/{$trackingCode}"));

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return ['success' => false, 'message' => 'سفارش یافت نشد.'];
        } catch (\Exception $e) {
            Log::error('Tapin trackShipment error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ==========================================
    // ساخت payload سفارش
    // ==========================================

    protected function buildOrderPayload(array $orderData): array
    {
        $senderName = WarehouseSetting::get('tapin_sender_name') ?: WarehouseSetting::get('amadest_sender_name') ?: 'فروشگاه';
        $senderMobile = WarehouseSetting::get('tapin_sender_mobile') ?: WarehouseSetting::get('amadest_sender_mobile') ?: '09000000000';

        $weightGrams = (int) ($orderData['weight'] ?? 500);
        $weightGrams = max($weightGrams, 10);

        // تاپین شهر و استان رو با نام میخواد نه ID
        $products = [];
        if (!empty($orderData['products'])) {
            $products = $orderData['products'];
        } else {
            $products[] = [
                'name' => $orderData['product_name'] ?? 'کالا',
                'count' => 1,
                'weight' => $weightGrams,
                'value' => (int) ($orderData['value'] ?? 100000),
            ];
        }

        $payload = [
            'shop_id' => $this->shopId,
            'first_name' => $this->getFirstName($orderData['recipient_name'] ?? 'مشتری'),
            'last_name' => $this->getLastName($orderData['recipient_name'] ?? 'مشتری'),
            'mobile' => $this->formatMobile($orderData['recipient_mobile'] ?? ''),
            'address' => $orderData['recipient_address'] ?? 'آدرس نامشخص',
            'postal_code' => $orderData['recipient_postal_code'] ?? '',
            'province' => $orderData['recipient_province'] ?? $orderData['recipient_state'] ?? '',
            'city' => $orderData['recipient_city_name'] ?? $orderData['recipient_city'] ?? '',
            'pay_type' => $orderData['pay_type'] ?? 0, // 0 = آنلاین پرداخت شده
            'send_type' => $orderData['send_type'] ?? 1, // 1 = پیشتاز
            'weight' => $weightGrams,
            'product' => $products,
        ];

        if (!empty($orderData['external_order_id'])) {
            $payload['order_id'] = (int) preg_replace('/\D/', '', $orderData['external_order_id']);
        }

        return $payload;
    }

    protected function getFirstName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName), 2);
        return $parts[0] ?? $fullName;
    }

    protected function getLastName(string $fullName): string
    {
        $parts = explode(' ', trim($fullName), 2);
        return $parts[1] ?? $parts[0];
    }

    protected function formatMobile(?string $mobile): string
    {
        if (!$mobile) return '';
        $mobile = preg_replace('/\D/', '', $mobile);
        if (str_starts_with($mobile, '98') && strlen($mobile) == 12) {
            $mobile = '0' . substr($mobile, 2);
        } elseif (!str_starts_with($mobile, '0') && strlen($mobile) == 10) {
            $mobile = '0' . $mobile;
        }
        return $mobile;
    }
}
