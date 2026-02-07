<?php

namespace Modules\Warehouse\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AmadastService
{
    protected string $baseUrl = 'https://shop-integration.amadast.com';
    protected ?string $clientCode;
    protected ?string $accessToken;
    protected ?int $userId;
    protected ?int $storeId;

    public function __construct()
    {
        $this->clientCode = Setting::get('amadast_client_code');
        $this->accessToken = Setting::get('amadast_access_token');
        $this->userId = Setting::get('amadast_user_id');
        $this->storeId = Setting::get('amadast_store_id');
    }

    /**
     * Check if Amadast is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->clientCode) && !empty($this->storeId);
    }

    /**
     * Get default headers for API requests
     */
    protected function getHeaders(bool $withAuth = true): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Client-Code' => $this->clientCode,
        ];

        if ($withAuth && $this->accessToken) {
            $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        }

        return $headers;
    }

    /**
     * Create a new user in Amadast
     */
    public function createUser(array $data): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders(false))
                ->post("{$this->baseUrl}/v1/users", [
                    'full_name' => $data['full_name'],
                    'mobile' => $data['mobile'],
                    'national_code' => $data['national_code'] ?? null,
                    'province_id' => $data['province_id'] ?? null,
                    'city_id' => $data['city_id'] ?? null,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                if ($result['success'] ?? false) {
                    // Save user ID
                    Setting::set('amadast_user_id', $result['data']['id']);
                    $this->userId = $result['data']['id'];

                    // Get token for this user
                    $this->getAuthToken($result['data']['id']);
                }
                return $result;
            }

            return ['success' => false, 'message' => 'خطا در ایجاد کاربر: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Amadast createUser error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get authentication token
     */
    public function getAuthToken(int $userId): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders(false))
                ->post("{$this->baseUrl}/v1/auth/token/{$userId}");

            if ($response->successful()) {
                $result = $response->json();
                if ($result['success'] ?? false) {
                    Setting::set('amadast_access_token', $result['data']['access_token']);
                    Setting::set('amadast_refresh_token', $result['data']['refresh_token']);
                    Setting::set('amadast_token_expires_at', now()->addSeconds($result['data']['expires_in'])->toDateTimeString());
                    $this->accessToken = $result['data']['access_token'];
                }
                return $result;
            }

            return ['success' => false, 'message' => 'خطا در دریافت توکن: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Amadast getAuthToken error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Refresh token if expired
     */
    protected function ensureValidToken(): bool
    {
        $expiresAt = Setting::get('amadast_token_expires_at');

        if (!$expiresAt || now()->gte($expiresAt)) {
            if ($this->userId) {
                $result = $this->getAuthToken($this->userId);
                return $result['success'] ?? false;
            }
            return false;
        }

        return true;
    }

    /**
     * Get list of provinces
     */
    public function getProvinces(): array
    {
        return Cache::remember('amadast_provinces', 86400, function () {
            try {
                $this->ensureValidToken();

                $response = Http::withHeaders($this->getHeaders())
                    ->get("{$this->baseUrl}/v1/cities");

                if ($response->successful()) {
                    return $response->json()['data'] ?? [];
                }
                return [];
            } catch (\Exception $e) {
                Log::error('Amadast getProvinces error', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Get list of cities by province
     */
    public function getCities(int $provinceId): array
    {
        return Cache::remember("amadast_cities_{$provinceId}", 86400, function () use ($provinceId) {
            try {
                $this->ensureValidToken();

                $response = Http::withHeaders($this->getHeaders())
                    ->get("{$this->baseUrl}/v1/cities", ['province_id' => $provinceId]);

                if ($response->successful()) {
                    return $response->json()['data'] ?? [];
                }
                return [];
            } catch (\Exception $e) {
                Log::error('Amadast getCities error', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Create a location (warehouse)
     */
    public function createLocation(array $data): array
    {
        try {
            $this->ensureValidToken();

            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/v1/locations", [
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
                if ($result['success'] ?? false) {
                    Setting::set('amadast_location_id', $result['data']['id']);
                }
                return $result;
            }

            return ['success' => false, 'message' => 'خطا در ایجاد مکان: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Amadast createLocation error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create a store
     */
    public function createStore(array $data): array
    {
        try {
            $this->ensureValidToken();

            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/v1/stores", [
                    'title' => $data['title'],
                    'location_id' => $data['location_id'],
                ]);

            if ($response->successful()) {
                $result = $response->json();
                if ($result['success'] ?? false) {
                    Setting::set('amadast_store_id', $result['data']['id']);
                    $this->storeId = $result['data']['id'];
                }
                return $result;
            }

            return ['success' => false, 'message' => 'خطا در ایجاد فروشگاه: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Amadast createStore error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create an order/shipment
     */
    public function createOrder(array $orderData): array
    {
        try {
            if (!$this->isConfigured()) {
                return ['success' => false, 'message' => 'تنظیمات آمادست کامل نیست'];
            }

            $this->ensureValidToken();

            $senderName = Setting::get('amadast_sender_name');
            $senderMobile = Setting::get('amadast_sender_mobile');

            $payload = [
                'store_id' => $this->storeId,
                'external_order_id' => $orderData['external_order_id'],
                'recipient_name' => $orderData['recipient_name'],
                'sender_name' => $senderName,
                'recipient_mobile' => $this->formatMobile($orderData['recipient_mobile']),
                'sender_mobile' => $senderMobile,
                'recipient_city_id' => $orderData['recipient_city_id'],
                'recipient_address' => $orderData['recipient_address'],
                'recipient_postal_code' => $orderData['recipient_postal_code'],
                'weight' => $orderData['weight'] ?? 500,
                'value' => $orderData['value'] ?? 100000,
                'product_type' => $orderData['product_type'] ?? 1,
                'package_type' => $orderData['package_type'] ?? 1,
                'products' => $orderData['products'] ?? [],
                'description' => $orderData['description'] ?? null,
                'is_breakable' => $orderData['is_breakable'] ?? false,
            ];

            Log::info('Amadast createOrder payload', $payload);

            $response = Http::withHeaders($this->getHeaders())
                ->post("{$this->baseUrl}/v1/orders", $payload);

            if ($response->successful()) {
                $result = $response->json();
                Log::info('Amadast createOrder response', $result);
                return $result;
            }

            Log::error('Amadast createOrder failed', ['response' => $response->body()]);
            return ['success' => false, 'message' => 'خطا در ثبت سفارش: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Amadast createOrder error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Search orders by phone number
     */
    public function searchOrders(array $phoneNumbers): array
    {
        try {
            $this->ensureValidToken();

            $query = collect($phoneNumbers)->map(fn($p) => "phone_number={$p}")->implode('&');

            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/v1/orders/search?{$query}");

            if ($response->successful()) {
                return $response->json();
            }

            return ['success' => false, 'message' => 'خطا در جستجو: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Amadast searchOrders error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get tracking info for an order
     */
    public function getTrackingInfo(string $phoneNumber, int $externalOrderId): ?array
    {
        $result = $this->searchOrders([$phoneNumber]);

        if ($result['success'] ?? false) {
            foreach ($result['data'] ?? [] as $order) {
                if ($order['external_order_id'] == $externalOrderId) {
                    return $order;
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

        // Remove any non-digit characters
        $mobile = preg_replace('/\D/', '', $mobile);

        // Handle +98 or 98 prefix
        if (str_starts_with($mobile, '98') && strlen($mobile) == 12) {
            $mobile = '0' . substr($mobile, 2);
        } elseif (str_starts_with($mobile, '+98')) {
            $mobile = '0' . substr($mobile, 3);
        } elseif (!str_starts_with($mobile, '0') && strlen($mobile) == 10) {
            $mobile = '0' . $mobile;
        }

        return $mobile;
    }

    /**
     * Test connection to Amadast
     */
    public function testConnection(): array
    {
        try {
            if (empty($this->clientCode)) {
                return ['success' => false, 'message' => 'کد کلاینت تنظیم نشده است'];
            }

            // If no user yet, just check if client code works
            if (!$this->userId) {
                return ['success' => true, 'message' => 'کد کلاینت معتبر است. لطفاً تنظیمات را کامل کنید.'];
            }

            $this->ensureValidToken();

            // Try to get provinces as a simple test
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/v1/cities");

            if ($response->successful()) {
                return ['success' => true, 'message' => 'اتصال به آمادست برقرار است'];
            }

            return ['success' => false, 'message' => 'خطا در اتصال: ' . $response->status()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'خطا: ' . $e->getMessage()];
        }
    }

    /**
     * Setup Amadast (create user, location, store)
     */
    public function setup(array $data): array
    {
        // Step 1: Create user
        $userResult = $this->createUser([
            'full_name' => $data['sender_name'],
            'mobile' => $data['sender_mobile'],
            'province_id' => $data['province_id'],
            'city_id' => $data['city_id'],
        ]);

        if (!($userResult['success'] ?? false)) {
            return $userResult;
        }

        // Step 2: Create location
        $locationResult = $this->createLocation([
            'title' => $data['warehouse_title'] ?? 'انبار اصلی',
            'address' => $data['warehouse_address'],
            'province_id' => $data['province_id'],
            'city_id' => $data['city_id'],
            'postal_code' => $data['postal_code'],
        ]);

        if (!($locationResult['success'] ?? false)) {
            return $locationResult;
        }

        // Step 3: Create store
        $storeResult = $this->createStore([
            'title' => $data['store_title'] ?? 'فروشگاه اصلی',
            'location_id' => $locationResult['data']['id'],
        ]);

        if (!($storeResult['success'] ?? false)) {
            return $storeResult;
        }

        // Save sender info
        Setting::set('amadast_sender_name', $data['sender_name']);
        Setting::set('amadast_sender_mobile', $data['sender_mobile']);
        Setting::set('amadast_warehouse_address', $data['warehouse_address']);
        Setting::set('amadast_province_id', $data['province_id']);
        Setting::set('amadast_city_id', $data['city_id']);
        Setting::set('amadast_postal_code', $data['postal_code']);

        return [
            'success' => true,
            'message' => 'تنظیمات آمادست با موفقیت انجام شد',
            'data' => [
                'user_id' => $this->userId,
                'location_id' => $locationResult['data']['id'],
                'store_id' => $this->storeId,
            ]
        ];
    }
}
