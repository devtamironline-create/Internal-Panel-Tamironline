<?php

namespace Modules\Warehouse\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\WarehouseSetting;

class AmadestService
{
    protected ?string $apiKey;
    protected ?string $apiUrl;
    protected ?string $storeId;

    public function __construct()
    {
        $this->apiKey = WarehouseSetting::get('amadest_api_key');
        $this->apiUrl = rtrim(WarehouseSetting::get('amadest_api_url', 'https://api.amadest.com'), '/');
        $this->storeId = WarehouseSetting::get('amadest_store_id');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Detect which API path to use based on the configured URL
     */
    protected function endpoint(string $path): string
    {
        // shop-integration.amadast.com uses /v1/...
        // api.amadest.com uses /api/v1/...
        if (str_contains($this->apiUrl, 'shop-integration')) {
            return $this->apiUrl . '/v1/' . ltrim($path, '/');
        }
        return $this->apiUrl . '/api/v1/' . ltrim($path, '/');
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'کلید API آمادست وارد نشده.'];
        }

        try {
            // Try cities endpoint first (works on both APIs)
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint('cities'));

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'اتصال به آمادست برقرار است.',
                    'data' => $response->json(),
                ];
            }

            // Fallback: try profile endpoint
            $response2 = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint('profile'));

            if ($response2->successful()) {
                return [
                    'success' => true,
                    'message' => 'اتصال به آمادست برقرار است.',
                    'data' => $response2->json(),
                ];
            }

            return ['success' => false, 'message' => 'خطا در اتصال: HTTP ' . $response->status() . ' - ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Amadest connection test failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطا در اتصال: ' . $e->getMessage()];
        }
    }

    /**
     * Get provinces list (= cities endpoint without province_id)
     */
    public function getProvinces(): array
    {
        $cached = Cache::get('amadest_provinces');
        if (!empty($cached)) return $cached;

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint('cities'));

            if ($response->successful()) {
                $data = $response->json()['data'] ?? $response->json();
                if (!empty($data) && is_array($data)) {
                    Cache::put('amadest_provinces', $data, 86400);
                    return $data;
                }
            }
            Log::error('Amadest getProvinces failed', ['status' => $response->status() ?? 'N/A']);
            return [];
        } catch (\Exception $e) {
            Log::error('Amadest getProvinces error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get cities within a province (separate API call with province_id)
     */
    public function getCities(?int $provinceId = null): array
    {
        if (!$provinceId) return $this->getProvinces();

        $cacheKey = 'amadest_cities_prov_' . $provinceId;
        $cached = Cache::get($cacheKey);
        if (!empty($cached)) return $cached;

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint('cities'), ['province_id' => $provinceId]);

            if ($response->successful()) {
                $data = $response->json()['data'] ?? $response->json();
                if (!empty($data) && is_array($data)) {
                    Cache::put($cacheKey, $data, 86400);
                    return $data;
                }
            }
            Log::error('Amadest getCities failed', ['province_id' => $provinceId, 'status' => $response->status() ?? 'N/A']);
            return [];
        } catch (\Exception $e) {
            Log::error('Amadest getCities error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Find city ID by city/state name
     */
    public function findCityId(?string $city, ?string $state = null): ?int
    {
        if (empty($city) && empty($state)) return null;

        $normalize = fn($s) => trim(str_replace(['ي', 'ك', 'ة', ' '], ['ی', 'ک', 'ه', ''], $s ?? ''));
        $cityNorm = $normalize($city);
        $stateNorm = $normalize($state);

        // مپ استان‌های مخفف ووکامرس
        $stateMap = [
            'THR' => 'تهران', 'ESF' => 'اصفهان', 'FRS' => 'فارس', 'KHZ' => 'خوزستان',
            'AZS' => 'آذربایجان شرقی', 'AZG' => 'آذربایجان غربی', 'KRN' => 'کرمان',
            'KRS' => 'کرمانشاه', 'GIL' => 'گیلان', 'MZN' => 'مازندران', 'MKZ' => 'مرکزی',
            'HMD' => 'همدان', 'QZV' => 'قزوین', 'QOM' => 'قم', 'GLS' => 'گلستان',
            'ZJN' => 'زنجان', 'LRS' => 'لرستان', 'BHR' => 'بوشهر', 'KRD' => 'کردستان',
            'ARD' => 'اردبیل', 'YZD' => 'یزد', 'SMN' => 'سمنان', 'SBN' => 'خراسان جنوبی',
            'RKH' => 'خراسان رضوی', 'SKH' => 'خراسان شمالی', 'SBS' => 'سیستان و بلوچستان',
            'CHB' => 'چهارمحال و بختیاری', 'ILM' => 'ایلام', 'KBD' => 'کهگیلویه و بویراحمد',
            'HDN' => 'هرمزگان', 'ABZ' => 'البرز',
        ];
        if (isset($stateMap[$state])) {
            $stateNorm = $normalize($stateMap[$state]);
        }

        // اول استان رو پیدا کن
        $provinces = $this->getProvinces();
        $provinceId = null;

        foreach ($provinces as $p) {
            $name = $normalize($p['name'] ?? $p['title'] ?? '');
            if ($name === $stateNorm || $name === $cityNorm) {
                $provinceId = (int) $p['id'];
                break;
            }
        }
        if (!$provinceId && !empty($stateNorm)) {
            foreach ($provinces as $p) {
                $name = $normalize($p['name'] ?? $p['title'] ?? '');
                if (str_contains($name, $stateNorm) || str_contains($stateNorm, $name)) {
                    $provinceId = (int) $p['id'];
                    break;
                }
            }
        }

        if (!$provinceId) {
            Log::warning('Amadest province not found', ['city' => $city, 'state' => $state]);
            return null;
        }

        // حالا شهرهای اون استان رو بگیر
        $cities = $this->getCities($provinceId);
        if (empty($cities)) return null;

        // جستجوی دقیق
        foreach ($cities as $c) {
            $name = $normalize($c['name'] ?? $c['title'] ?? '');
            if ($name === $cityNorm) {
                return (int) $c['id'];
            }
        }

        // جستجوی شامل بودن
        foreach ($cities as $c) {
            $name = $normalize($c['name'] ?? $c['title'] ?? '');
            if (!empty($cityNorm) && (str_contains($name, $cityNorm) || str_contains($cityNorm, $name))) {
                return (int) $c['id'];
            }
        }

        // اگه شهر پیدا نشد، اولین شهر استان
        if (!empty($cities)) {
            Log::warning('Amadest city not found, using first city of province', ['city' => $city, 'province_id' => $provinceId]);
            return (int) $cities[0]['id'];
        }

        Log::warning('Amadest city not found', ['city' => $city, 'state' => $state]);
        return null;
    }

    /**
     * Create a location (warehouse address)
     */
    public function createLocation(array $data): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('locations'), [
                    'title' => $data['title'],
                    'address' => $data['address'],
                    'province_id' => $data['province_id'],
                    'city_id' => $data['city_id'],
                    'postal_code' => $data['postal_code'],
                    'latitude' => $data['latitude'] ?? 35.6892,
                    'longitude' => $data['longitude'] ?? 51.3890,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                if ($result['success'] ?? $result['data'] ?? false) {
                    $id = $result['data']['id'] ?? null;
                    if ($id) WarehouseSetting::set('amadest_location_id', $id);
                }
                return $result;
            }

            return ['success' => false, 'message' => 'خطا در ایجاد مکان: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Amadest createLocation error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create a store
     */
    public function createStore(array $data): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('stores'), [
                    'title' => $data['title'],
                    'location_id' => $data['location_id'],
                    'admin_name' => $data['admin_name'],
                    'phone' => $data['phone'],
                ]);

            if ($response->successful()) {
                $result = $response->json();
                $id = $result['data']['id'] ?? null;
                if ($id) {
                    WarehouseSetting::set('amadest_store_id', $id);
                    $this->storeId = $id;
                }
                return $result;
            }

            return ['success' => false, 'message' => 'خطا در ایجاد فروشگاه: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Amadest createStore error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Setup: create location + store
     */
    public function setup(array $data): array
    {
        $locationResult = $this->createLocation([
            'title' => $data['warehouse_title'] ?? 'انبار اصلی',
            'address' => $data['warehouse_address'],
            'province_id' => $data['province_id'],
            'city_id' => $data['city_id'],
            'postal_code' => $data['postal_code'],
        ]);

        if (!($locationResult['success'] ?? false) && !($locationResult['data']['id'] ?? false)) {
            return $locationResult;
        }

        $locationId = $locationResult['data']['id'] ?? null;

        $storeResult = $this->createStore([
            'title' => $data['store_title'] ?? 'فروشگاه اصلی',
            'location_id' => $locationId,
            'admin_name' => $data['sender_name'],
            'phone' => $data['sender_mobile'],
        ]);

        if (!($storeResult['success'] ?? false) && !($storeResult['data']['id'] ?? false)) {
            return $storeResult;
        }

        WarehouseSetting::set('amadest_sender_name', $data['sender_name']);
        WarehouseSetting::set('amadest_sender_mobile', $data['sender_mobile']);
        WarehouseSetting::set('amadest_warehouse_address', $data['warehouse_address']);

        return [
            'success' => true,
            'message' => 'تنظیمات آمادست با موفقیت انجام شد',
            'data' => [
                'location_id' => $locationId,
                'store_id' => $this->storeId,
            ]
        ];
    }

    /**
     * Create an order/shipment in Amadest
     */
    public function createOrder(array $orderData): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'کلید API آمادست وارد نشده'];
        }

        if (empty($this->storeId)) {
            return ['success' => false, 'message' => 'فروشگاه آمادست تنظیم نشده. ابتدا از صفحه تنظیمات آمادست، فروشگاه را راه‌اندازی کنید.'];
        }

        try {
            $senderName = WarehouseSetting::get('amadest_sender_name') ?: 'فروشگاه';
            $senderMobile = WarehouseSetting::get('amadest_sender_mobile') ?: '09000000000';

            // استخراج عدد از شماره سفارش (WC-35388 → 35388)
            $externalId = $orderData['external_order_id'] ?? '0';
            $externalIdInt = (int) preg_replace('/\D/', '', $externalId);

            // وزن به گرم (حداقل 10 گرم)
            $weightGrams = (int) ($orderData['weight'] ?? 500);
            $weightGrams = max($weightGrams, 10);

            $payload = [
                'store_id' => (int) ($this->storeId ?: 0),
                'external_order_id' => $externalIdInt,
                'recipient_name' => $orderData['recipient_name'] ?: 'مشتری',
                'sender_name' => $senderName,
                'recipient_mobile' => $this->formatMobile($orderData['recipient_mobile']),
                'sender_mobile' => $this->formatMobile($senderMobile),
                'recipient_address' => $orderData['recipient_address'] ?: 'آدرس نامشخص',
                'weight' => $weightGrams,
                'value' => (int) ($orderData['value'] ?? 100000),
                'product_type' => $orderData['product_type'] ?? 1,
                'package_type' => $orderData['package_type'] ?? 1,
            ];

            // فیلدهای اختیاری - فقط اگه مقدار دارن اضافه شن
            if (!empty($orderData['recipient_city_id'])) {
                $payload['recipient_city_id'] = (int) $orderData['recipient_city_id'];
            }
            if (!empty($orderData['recipient_postal_code'])) {
                $payload['recipient_postal_code'] = $orderData['recipient_postal_code'];
            }
            if (!empty($orderData['description'])) {
                $payload['description'] = $orderData['description'];
            }
            if (!empty($orderData['products'])) {
                $payload['products'] = $orderData['products'];
            }

            Log::info('Amadest createOrder payload', $payload);

            $response = Http::timeout(30)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('orders'), $payload);

            if ($response->successful()) {
                $result = $response->json();
                Log::info('Amadest createOrder response', $result);
                return $result;
            }

            Log::error('Amadest createOrder failed', ['response' => $response->body()]);
            return ['success' => false, 'message' => 'خطا در ثبت سفارش: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Amadest createOrder error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create shipment (alias for backward compat)
     */
    public function createShipment(array $data): array
    {
        return $this->createOrder($data);
    }

    /**
     * Search orders by phone number
     */
    public function searchOrders(array $phoneNumbers): array
    {
        try {
            $formattedPhones = collect($phoneNumbers)->map(fn($p) => $this->formatMobile($p))->filter()->toArray();
            $query = collect($formattedPhones)->map(fn($p) => "phone_number={$p}")->implode('&');

            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint('orders/search') . '?' . $query);

            if ($response->successful()) {
                return $response->json();
            }

            return ['success' => false, 'message' => 'خطا در جستجو: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Amadest searchOrders error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Track shipment by tracking code or order number
     * Tries multiple endpoints to find the order
     */
    public function trackShipment(string $trackingCode): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'کلید API آمادست وارد نشده.'];
        }

        $endpoints = [
            'orders/' . $trackingCode,
            'tracking/' . $trackingCode,
            'orders/search?tracking_code=' . $trackingCode,
            'orders/search?external_order_id=' . $trackingCode,
            'orders?tracking_code=' . $trackingCode,
        ];

        // If it looks like a phone number, also try phone search
        if (preg_match('/^09\d{9}$/', $trackingCode) || preg_match('/^9\d{9}$/', $trackingCode)) {
            $endpoints[] = 'orders/search?phone_number=' . $this->formatMobile($trackingCode);
        }

        foreach ($endpoints as $ep) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders($this->getHeaders())
                    ->get($this->endpoint($ep));

                Log::info('Amadest track attempt', ['endpoint' => $ep, 'status' => $response->status()]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (!empty($data['data']) || !empty($data['success'])) {
                        return ['success' => true, 'data' => $data];
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return ['success' => false, 'message' => 'سفارش یافت نشد. کد رهگیری یا شماره سفارش را بررسی کنید.'];
    }

    /**
     * Get tracking info for an order by phone + external ID
     */
    public function getTrackingInfo(string $phoneNumber, $externalOrderId): ?array
    {
        $result = $this->searchOrders([$phoneNumber]);

        if ($result['success'] ?? false) {
            foreach ($result['data'] ?? [] as $order) {
                if (($order['external_order_id'] ?? null) == $externalOrderId) {
                    return [
                        'amadest_tracking_code' => $order['tracking_code'] ?? $order['barcode'] ?? null,
                        'courier_tracking_code' => $order['post_tracking_code'] ?? $order['courier_tracking_code'] ?? null,
                        'courier_title' => $order['courier_name'] ?? $order['courier_title'] ?? null,
                        'status' => $order['status'] ?? null,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Format mobile number to 09... format
     */
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
