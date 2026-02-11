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

    // نگاشت کد استان ووکامرس به نام فارسی
    protected static array $wcStateMap = [
        'THR' => 'تهران', 'IS' => 'اصفهان', 'KHZ' => 'خوزستان', 'FRS' => 'فارس',
        'KHRZ' => 'خراسان رضوی', 'AZAR' => 'آذربایجان شرقی', 'AZARGH' => 'آذربایجان غربی',
        'ARD' => 'اردبیل', 'ILM' => 'ایلام', 'BSH' => 'بوشهر', 'CHBK' => 'چهارمحال و بختیاری',
        'KHRJ' => 'خراسان جنوبی', 'KHRSH' => 'خراسان شمالی', 'ZNJ' => 'زنجان',
        'SMN' => 'سمنان', 'SBL' => 'سیستان و بلوچستان', 'QZV' => 'قزوین', 'QOM' => 'قم',
        'KRD' => 'کردستان', 'KRM' => 'کرمان', 'KRMSH' => 'کرمانشاه',
        'KHGB' => 'کهگیلویه و بویراحمد', 'GLS' => 'گلستان', 'GIL' => 'گیلان',
        'LRS' => 'لرستان', 'MZN' => 'مازندران', 'MRK' => 'مرکزی',
        'HRM' => 'هرمزگان', 'HMD' => 'همدان', 'YZD' => 'یزد', 'ALB' => 'البرز',
    ];

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
            'Authorization' => $this->apiKey,
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
                ->post($this->endpoint('public/transaction/credit/'), [
                    'shop_id' => $this->shopId,
                ]);

            $data = $response->json() ?? [];
            $status = $data['returns']['status'] ?? 0;

            if ($status === 200) {
                $credit = $data['entries']['credit'] ?? null;
                $creditFormatted = $credit !== null ? number_format($credit) . ' ریال' : '';
                return [
                    'success' => true,
                    'message' => 'اتصال برقرار است.' . ($creditFormatted ? ' اعتبار: ' . $creditFormatted : ''),
                ];
            }

            return ['success' => false, 'message' => 'خطا: ' . ($data['returns']['message'] ?? $response->body())];
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
                $json = $response->json();
                $data = $json['entries'] ?? $json['results'] ?? $json['data'] ?? $json;

                Log::info('Tapin provinces response sample', [
                    'count' => is_array($data) ? count($data) : 0,
                    'first' => is_array($data) && !empty($data) ? $data[0] ?? $data : 'empty',
                ]);

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

    public function getCities($provinceCode): array
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
                $json = $response->json();
                $data = $json['entries'] ?? $json['results'] ?? $json['data'] ?? $json;

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

    /**
     * پیدا کردن province_code از نام استان یا کد ووکامرس
     */
    public function findProvinceCode(?string $stateNameOrCode): ?int
    {
        if (!$stateNameOrCode) return null;

        // اگه کد ووکامرس هست، به نام فارسی تبدیل کن
        $persianName = self::$wcStateMap[strtoupper($stateNameOrCode)] ?? $stateNameOrCode;

        $provinces = $this->getProvinces();
        if (empty($provinces)) return null;

        foreach ($provinces as $province) {
            $name = $province['name'] ?? $province['title'] ?? '';
            $code = $province['id'] ?? $province['code'] ?? $province['province_code'] ?? null;

            if (!$code || !$name) continue;

            // تطبیق دقیق
            if ($name === $persianName) return (int) $code;

            // تطبیق شامل (مثلا "تهران" در "استان تهران")
            if (str_contains($name, $persianName) || str_contains($persianName, $name)) {
                return (int) $code;
            }
        }

        Log::warning('Tapin province not found', ['input' => $stateNameOrCode, 'persian' => $persianName]);
        return null;
    }

    /**
     * پیدا کردن city_code از نام شهر و کد استان
     */
    public function findCityCode(?string $cityName, $provinceCode): ?int
    {
        if (!$cityName || !$provinceCode) return null;

        $cities = $this->getCities($provinceCode);
        if (empty($cities)) return null;

        foreach ($cities as $city) {
            $name = $city['name'] ?? $city['title'] ?? '';
            $code = $city['id'] ?? $city['code'] ?? $city['city_code'] ?? null;

            if (!$code || !$name) continue;

            if ($name === $cityName) return (int) $code;
            if (str_contains($name, $cityName) || str_contains($cityName, $name)) {
                return (int) $code;
            }
        }

        Log::warning('Tapin city not found', ['city' => $cityName, 'province_code' => $provinceCode]);
        return null;
    }

    // ==========================================
    // فروشگاه‌ها
    // ==========================================

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
                ->post($this->endpoint('public/transaction/credit/'), [
                    'shop_id' => $this->shopId,
                ]);

            $data = $response->json() ?? [];

            if ($response->successful() && ($data['returns']['status'] ?? 0) === 200) {
                $credit = $data['entries']['credit'] ?? null;
                return [
                    'success' => true,
                    'data' => [
                        'credit' => $credit,
                        'formatted' => $credit !== null ? number_format($credit) . ' ریال' : 'نامشخص',
                    ],
                ];
            }

            return ['success' => false, 'message' => $data['returns']['message'] ?? 'خطا: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Tapin getShopCredit error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ==========================================
    // ثبت سفارش
    // POST /api/v2/public/order/post/register/
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
                ->post($this->endpoint('public/order/post/register/'), $payload);

            $result = $response->json() ?? [];
            Log::info('Tapin createOrder response', ['status' => $response->status(), 'body' => $result]);

            $apiStatus = $result['returns']['status'] ?? 0;

            if ($apiStatus === 200) {
                $entries = $result['entries'] ?? [];
                return [
                    'success' => true,
                    'message' => $result['returns']['message'] ?? 'سفارش ثبت شد',
                    'data' => [
                        'order_id' => $entries['order_id'] ?? null,
                        'barcode' => $entries['barcode'] ?? null,
                        'tracking_code' => $entries['barcode'] ?? null,
                        'status' => $entries['status'] ?? null,
                        'insurance_price' => $entries['insurance_price'] ?? 0,
                        'insurance_tax' => $entries['insurance_tax'] ?? 0,
                    ],
                ];
            }

            $errorMessage = $result['returns']['message'] ?? $response->body();
            // اگه entries خطاهای فیلد داره نشون بده
            $fieldErrors = $result['entries'] ?? [];
            if (is_array($fieldErrors) && !empty($fieldErrors)) {
                $errorDetails = [];
                foreach ($fieldErrors as $field => $errors) {
                    if (is_array($errors)) {
                        $errorDetails[] = $field . ': ' . implode(', ', $errors);
                    }
                }
                if (!empty($errorDetails)) {
                    $errorMessage .= ' | ' . implode(' | ', $errorDetails);
                }
            }

            return ['success' => false, 'message' => 'خطا در ثبت سفارش: ' . $errorMessage];
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
    // ساخت payload ثبت سفارش
    // طبق داکیومنت: POST /api/v2/public/order/post/register/
    // ==========================================

    protected function buildOrderPayload(array $orderData): array
    {
        $weightGrams = (int) ($orderData['weight'] ?? 500);
        $weightGrams = max($weightGrams, 100);

        // پیدا کردن کد استان و شهر
        $state = $orderData['recipient_province'] ?? $orderData['recipient_state'] ?? '';
        $city = $orderData['recipient_city_name'] ?? $orderData['recipient_city'] ?? '';

        $provinceCode = $this->findProvinceCode($state);
        $cityCode = $provinceCode ? $this->findCityCode($city, $provinceCode) : null;

        Log::info('Tapin location lookup', [
            'state_input' => $state,
            'city_input' => $city,
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
        ]);

        // ساخت لیست محصولات
        $products = [];
        if (!empty($orderData['products'])) {
            foreach ($orderData['products'] as $p) {
                $products[] = [
                    'title' => $p['title'] ?? $p['name'] ?? 'کالا',
                    'count' => (int) ($p['count'] ?? $p['quantity'] ?? 1),
                    'price' => (int) ($p['price'] ?? 0),
                    'weight' => (int) ($p['weight'] ?? $weightGrams),
                    'discount' => (int) ($p['discount'] ?? 0),
                    'product_id' => $p['product_id'] ?? null,
                ];
            }
        } else {
            $products[] = [
                'title' => $orderData['product_name'] ?? 'کالا',
                'count' => 1,
                'price' => (int) ($orderData['value'] ?? 100000),
                'weight' => $weightGrams,
                'discount' => 0,
                'product_id' => null,
            ];
        }

        $orderNumber = $orderData['external_order_id'] ?? '';
        $manualId = preg_replace('/\D/', '', $orderNumber) ?: (string) time();

        $payload = [
            'register_type' => 0,
            'shop_id' => $this->shopId,
            'first_name' => $this->getFirstName($orderData['recipient_name'] ?? 'مشتری'),
            'last_name' => $this->getLastName($orderData['recipient_name'] ?? 'مشتری'),
            'mobile' => $this->formatMobile($orderData['recipient_mobile'] ?? ''),
            'phone' => null,
            'email' => null,
            'address' => $orderData['recipient_address'] ?? 'آدرس نامشخص',
            'postal_code' => $orderData['recipient_postal_code'] ?? '0000000000',
            'province_code' => $provinceCode ?: 1,
            'city_code' => $cityCode ?: 1,
            'description' => null,
            'employee_code' => -1,
            'pay_type' => (int) ($orderData['pay_type'] ?? 0), // 0 = آنلاین پرداخت شده
            'order_type' => 0,
            'box_id' => (int) ($orderData['box_id'] ?? 0),
            'kiosk_id' => null,
            'package_weight' => $weightGrams,
            'manual_id' => $manualId,
            'has_insurance' => $orderData['has_insurance'] ?? 'false',
            'content_type' => 1,
            'products' => $products,
        ];

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
