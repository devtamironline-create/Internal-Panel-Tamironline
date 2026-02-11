<?php

namespace Modules\Warehouse\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\WarehouseSetting;

class AmadestService
{
    protected ?string $apiUrl;
    protected ?string $clientCode;

    public function __construct()
    {
        $this->apiUrl = rtrim(WarehouseSetting::get('amadest_api_url', 'https://shop-integration.amadast.com'), '/');
        $this->clientCode = WarehouseSetting::get('amadest_client_code');
    }

    public function isConfigured(): bool
    {
        // فقط توکن لازمه - X-Client-Code اختیاریه
        return !empty($this->getAccessToken());
    }

    /**
     * هدرهای بدون توکن (برای ساخت کاربر و گرفتن توکن)
     */
    protected function getPublicHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        // X-Client-Code اختیاریه - اگه وارد شده بفرست
        if (!empty($this->clientCode)) {
            $headers['X-Client-Code'] = $this->clientCode;
        }
        return $headers;
    }

    /**
     * هدرهای با توکن (برای بقیه درخواست‌ها)
     */
    protected function getHeaders(): array
    {
        $token = $this->getAccessToken();
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        // X-Client-Code اختیاریه - اگه وارد شده بفرست
        if (!empty($this->clientCode)) {
            $headers['X-Client-Code'] = $this->clientCode;
        }
        return $headers;
    }

    /**
     * Detect which API path to use based on the configured URL
     */
    protected function endpoint(string $path): string
    {
        if (str_contains($this->apiUrl, 'shop-integration')) {
            return $this->apiUrl . '/v1/' . ltrim($path, '/');
        }
        return $this->apiUrl . '/api/v1/' . ltrim($path, '/');
    }

    // ==========================================
    // احراز هویت (Authentication)
    // ==========================================

    /**
     * ساخت کاربر جدید در آمادست
     * POST /v1/users
     */
    public function createUser(string $fullName, string $mobile, ?string $nationalCode = null): array
    {
        try {
            $payload = [
                'full_name' => $fullName,
                'mobile' => $this->formatMobile($mobile),
            ];
            if ($nationalCode) {
                $payload['national_code'] = $nationalCode;
            }

            $response = Http::timeout(15)
                ->withHeaders($this->getPublicHeaders())
                ->post($this->endpoint('users'), $payload);

            $result = $response->json();
            Log::info('Amadest createUser response', ['status' => $response->status(), 'body' => $result]);

            if ($response->successful() && ($result['success'] ?? false)) {
                $userId = $result['data']['id'] ?? null;
                if ($userId) {
                    WarehouseSetting::set('amadest_user_id', (string) $userId);
                }
                return $result;
            }

            return ['success' => false, 'message' => 'خطا در ساخت کاربر: ' . ($result['message'] ?? $response->body())];
        } catch (\Exception $e) {
            Log::error('Amadest createUser error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * دریافت توکن با user_id
     * POST /v1/auth/token/{user_id}
     */
    public function fetchToken(?int $userId = null): array
    {
        $userId = $userId ?: (int) WarehouseSetting::get('amadest_user_id');
        if (!$userId) {
            return ['success' => false, 'message' => 'شناسه کاربر آمادست (user_id) وارد نشده.'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getPublicHeaders())
                ->post($this->endpoint("auth/token/{$userId}"));

            $result = $response->json();
            Log::info('Amadest fetchToken response', ['status' => $response->status(), 'user_id' => $userId]);

            if ($response->successful() && ($result['success'] ?? false)) {
                $data = $result['data'] ?? [];
                $accessToken = $data['access_token'] ?? null;
                $refreshToken = $data['refresh_token'] ?? null;
                $expiresIn = $data['expires_in'] ?? 3600;

                if ($accessToken) {
                    // ذخیره توکن و زمان انقضا
                    WarehouseSetting::set('amadest_api_key', $accessToken);
                    WarehouseSetting::set('amadest_refresh_token', $refreshToken ?? '');
                    WarehouseSetting::set('amadest_token_expires_at', (string) (time() + $expiresIn - 60)); // 60 ثانیه زودتر

                    // کش هم آپدیت کن
                    Cache::put('amadest_access_token', $accessToken, $expiresIn - 60);
                }

                return ['success' => true, 'message' => 'توکن با موفقیت دریافت شد.', 'data' => $data];
            }

            return ['success' => false, 'message' => 'خطا در دریافت توکن: ' . ($result['message'] ?? $response->body())];
        } catch (\Exception $e) {
            Log::error('Amadest fetchToken error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * گرفتن access token معتبر (با auto-refresh)
     */
    public function getAccessToken(): ?string
    {
        // اول از کش بخون (سریعتره)
        $cached = Cache::get('amadest_access_token');
        if ($cached) {
            return $cached;
        }

        // چک کن آیا توکن ذخیره‌شده هنوز معتبره
        $token = WarehouseSetting::get('amadest_api_key');
        $expiresAt = (int) WarehouseSetting::get('amadest_token_expires_at', '0');

        if ($token && $expiresAt > time()) {
            // هنوز معتبره - بذار تو کش
            Cache::put('amadest_access_token', $token, $expiresAt - time());
            return $token;
        }

        // توکن منقضی شده - سعی کن تمدید کنی
        $userId = (int) WarehouseSetting::get('amadest_user_id');
        if ($userId) {
            Log::info('Amadest token expired, auto-refreshing', ['user_id' => $userId]);
            $result = $this->fetchToken($userId);
            if ($result['success'] ?? false) {
                return WarehouseSetting::get('amadest_api_key');
            }
            Log::warning('Amadest token refresh failed', ['error' => $result['message'] ?? 'unknown']);
        }

        // اگه هیچ‌کدوم کار نکرد، توکن قدیمی رو برگردون (شاید هنوز کار کنه)
        return $token;
    }

    // ==========================================
    // تست اتصال
    // ==========================================

    public function testConnection(): array
    {
        $token = $this->getAccessToken();
        if (empty($token)) {
            return ['success' => false, 'message' => 'توکن احراز هویت آمادست موجود نیست. لطفا ابتدا کاربر بسازید و توکن بگیرید.'];
        }

        try {
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

            // اگه 401 بود، توکن منقضی شده
            if ($response->status() === 401) {
                // سعی کن توکن رو تمدید کنی
                $refreshResult = $this->fetchToken();
                if ($refreshResult['success'] ?? false) {
                    // دوباره تست کن با توکن جدید
                    $response2 = Http::timeout(15)
                        ->withHeaders($this->getHeaders())
                        ->get($this->endpoint('cities'));
                    if ($response2->successful()) {
                        return ['success' => true, 'message' => 'اتصال برقرار است (توکن تمدید شد).', 'data' => $response2->json()];
                    }
                }
                return ['success' => false, 'message' => 'توکن منقضی شده و تمدید نشد. لطفا دوباره توکن بگیرید.'];
            }

            return ['success' => false, 'message' => 'خطا در اتصال: HTTP ' . $response->status() . ' - ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Amadest connection test failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطا در اتصال: ' . $e->getMessage()];
        }
    }

    // ==========================================
    // شهرها و استان‌ها
    // ==========================================

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

        $cities = $this->getCities($provinceId);
        if (empty($cities)) return null;

        foreach ($cities as $c) {
            $name = $normalize($c['name'] ?? $c['title'] ?? '');
            if ($name === $cityNorm) {
                return (int) $c['id'];
            }
        }

        foreach ($cities as $c) {
            $name = $normalize($c['name'] ?? $c['title'] ?? '');
            if (!empty($cityNorm) && (str_contains($name, $cityNorm) || str_contains($cityNorm, $name))) {
                return (int) $c['id'];
            }
        }

        if (!empty($cities)) {
            Log::warning('Amadest city not found, using first city of province', ['city' => $city, 'province_id' => $provinceId]);
            return (int) $cities[0]['id'];
        }

        Log::warning('Amadest city not found', ['city' => $city, 'state' => $state]);
        return null;
    }

    // ==========================================
    // مکان و فروشگاه
    // ==========================================

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
                return $result;
            }

            return ['success' => false, 'message' => 'خطا در ایجاد فروشگاه: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Amadest createStore error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ==========================================
    // سفارش‌ها
    // ==========================================

    /**
     * ثبت سفارش جدید در آمادست
     * POST /v1/orders
     *
     * بعد از ثبت، خودکار جستجو میکنه تا کد پیگیری آمادست و پست رو بگیره
     */
    public function createOrder(array $orderData): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات آمادست کامل نیست (توکن وارد نشده)'];
        }

        try {
            $senderName = WarehouseSetting::get('amadest_sender_name') ?: 'فروشگاه';
            $senderMobile = WarehouseSetting::get('amadest_sender_mobile') ?: '09000000000';

            $externalId = $orderData['external_order_id'] ?? '0';
            $externalIdInt = (int) preg_replace('/\D/', '', $externalId);

            $weightGrams = (int) ($orderData['weight'] ?? 500);
            $weightGrams = max($weightGrams, 10);

            $recipientMobile = $this->formatMobile($orderData['recipient_mobile']);

            $payload = [
                'store_id' => 0,
                'external_order_id' => $externalIdInt,
                'recipient_name' => $orderData['recipient_name'] ?: 'مشتری',
                'sender_name' => $senderName,
                'recipient_mobile' => $recipientMobile,
                'sender_mobile' => $this->formatMobile($senderMobile),
                'recipient_address' => $orderData['recipient_address'] ?: 'آدرس نامشخص',
                'weight' => $weightGrams,
                'value' => (int) ($orderData['value'] ?? 100000),
                'product_type' => $orderData['product_type'] ?? 1,
                'package_type' => $orderData['package_type'] ?? 1,
            ];

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

            $result = $response->json() ?? [];
            Log::info('Amadest createOrder response', ['status' => $response->status(), 'body' => $result]);

            if ($response->successful() && ($result['success'] ?? false)) {
                $amadestOrderId = $result['data']['id'] ?? null;

                // بلافاصله جستجو کن تا کد پیگیری آمادست و پست رو بگیری
                $trackingInfo = $this->fetchTrackingCodes($recipientMobile, $externalIdInt);

                return [
                    'success' => true,
                    'message' => $result['message'] ?? 'سفارش ثبت شد',
                    'data' => array_merge($result['data'] ?? [], [
                        'amadest_order_id' => $amadestOrderId,
                        'amadast_tracking_code' => $trackingInfo['amadast_tracking_code'] ?? null,
                        'courier_tracking_code' => $trackingInfo['courier_tracking_code'] ?? null,
                        'courier_title' => $trackingInfo['courier_title'] ?? null,
                    ]),
                ];
            }

            // اگه 401 بود، توکن منقضی شده - یه بار refresh کن و دوباره تلاش کن
            if ($response->status() === 401) {
                Log::info('Amadest createOrder got 401, trying token refresh');
                $refreshResult = $this->fetchToken();
                if ($refreshResult['success'] ?? false) {
                    // دوباره بفرست
                    $response2 = Http::timeout(30)
                        ->withHeaders($this->getHeaders())
                        ->post($this->endpoint('orders'), $payload);

                    $result2 = $response2->json() ?? [];
                    Log::info('Amadest createOrder retry response', ['status' => $response2->status(), 'body' => $result2]);

                    if ($response2->successful() && ($result2['success'] ?? false)) {
                        $amadestOrderId = $result2['data']['id'] ?? null;
                        $trackingInfo = $this->fetchTrackingCodes($recipientMobile, $externalIdInt);

                        return [
                            'success' => true,
                            'message' => $result2['message'] ?? 'سفارش ثبت شد (توکن تمدید شد)',
                            'data' => array_merge($result2['data'] ?? [], [
                                'amadest_order_id' => $amadestOrderId,
                                'amadast_tracking_code' => $trackingInfo['amadast_tracking_code'] ?? null,
                                'courier_tracking_code' => $trackingInfo['courier_tracking_code'] ?? null,
                                'courier_title' => $trackingInfo['courier_title'] ?? null,
                            ]),
                        ];
                    }
                    return ['success' => false, 'message' => 'خطا در ثبت سفارش (بعد از تمدید توکن): ' . ($result2['message'] ?? $response2->body())];
                }
                return ['success' => false, 'message' => 'توکن منقضی شده و تمدید نشد. لطفا دوباره توکن بگیرید.'];
            }

            return ['success' => false, 'message' => 'خطا در ثبت سفارش: ' . ($result['message'] ?? $response->body())];
        } catch (\Exception $e) {
            Log::error('Amadest createOrder error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Alias for backward compat
     */
    public function createShipment(array $data): array
    {
        return $this->createOrder($data);
    }

    /**
     * بعد از ثبت سفارش، کدهای پیگیری رو جستجو کن
     */
    protected function fetchTrackingCodes(string $recipientMobile, int $externalOrderId): array
    {
        try {
            // یه لحظه صبر کن تا آمادست سفارش رو پردازش کنه
            usleep(500000); // 0.5 ثانیه

            $result = $this->searchOrders([$recipientMobile]);

            if ($result['success'] ?? false) {
                foreach ($result['data'] ?? [] as $order) {
                    if (($order['external_order_id'] ?? null) == $externalOrderId) {
                        Log::info('Amadest tracking codes found', [
                            'external_order_id' => $externalOrderId,
                            'amadast_tracking_code' => $order['amadast_tracking_code'] ?? null,
                            'courier_tracking_code' => $order['courier_tracking_code'] ?? null,
                        ]);
                        return [
                            'amadast_tracking_code' => $order['amadast_tracking_code'] ?? null,
                            'courier_tracking_code' => $order['courier_tracking_code'] ?? null,
                            'courier_title' => $order['courier_title'] ?? null,
                            'postal_code' => $order['postal_code'] ?? null,
                        ];
                    }
                }
            }

            Log::info('Amadest tracking codes not yet available', ['external_order_id' => $externalOrderId]);
            return [];
        } catch (\Exception $e) {
            Log::warning('Amadest fetchTrackingCodes error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * جستجوی سفارش با شماره موبایل
     * GET /v1/orders/search
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
     * رهگیری مرسوله
     */
    public function trackShipment(string $trackingCode): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات آمادست کامل نیست.'];
        }

        // اگه شماره موبایل هست، جستجو با موبایل
        if (preg_match('/^09\d{9}$/', $trackingCode) || preg_match('/^9\d{9}$/', $trackingCode)) {
            $result = $this->searchOrders([$trackingCode]);
            if (($result['success'] ?? false) && !empty($result['data'])) {
                return ['success' => true, 'data' => $result];
            }
        }

        // جستجو با شماره سفارش
        $endpoints = [
            'orders/search?phone_number=' . $this->formatMobile($trackingCode),
            'orders/' . $trackingCode,
        ];

        foreach ($endpoints as $ep) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders($this->getHeaders())
                    ->get($this->endpoint($ep));

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

        return ['success' => false, 'message' => 'سفارش یافت نشد.'];
    }

    /**
     * دریافت کدهای پیگیری سفارش
     */
    public function getTrackingInfo(string $phoneNumber, $externalOrderId): ?array
    {
        $result = $this->searchOrders([$phoneNumber]);

        if ($result['success'] ?? false) {
            foreach ($result['data'] ?? [] as $order) {
                if (($order['external_order_id'] ?? null) == $externalOrderId) {
                    return [
                        'amadest_tracking_code' => $order['amadast_tracking_code'] ?? null,
                        'courier_tracking_code' => $order['courier_tracking_code'] ?? null,
                        'courier_title' => $order['courier_title'] ?? null,
                        'postal_code' => $order['postal_code'] ?? null,
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
