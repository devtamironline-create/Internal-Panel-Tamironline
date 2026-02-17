<?php

namespace Modules\Warehouse\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\WarehouseSetting;

class PostexService
{
    protected string $apiUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiUrl = rtrim(WarehouseSetting::get('postex_api_url', 'https://api.postex.ir'), '/');
        $this->apiKey = WarehouseSetting::get('postex_api_key');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    protected function getHeaders(): array
    {
        return [
            'x-api-key' => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    protected function endpoint(string $path): string
    {
        return $this->apiUrl . '/api/v1/' . ltrim($path, '/');
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات پستکس کامل نیست (API Key وارد نشده)'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint('wallet/balance'));

            if ($response->successful()) {
                $data = $response->json() ?? [];
                $amount = $data['amount'] ?? null;

                return [
                    'success' => true,
                    'message' => 'اتصال برقرار است.' . ($amount !== null ? ' موجودی: ' . number_format($amount) . ' ریال' : ''),
                    'data' => $data,
                ];
            }

            return ['success' => false, 'message' => 'خطا: ' . $response->body()];
        } catch (\Exception $e) {
            Log::error('Postex connection test failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطا در اتصال: ' . $e->getMessage()];
        }
    }

    public function getWalletBalance(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات پستکس کامل نیست'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint('wallet/balance'));

            if ($response->successful()) {
                $data = $response->json() ?? [];
                return [
                    'success' => true,
                    'data' => [
                        'balance' => $data['amount'] ?? 0,
                        'formatted' => number_format($data['amount'] ?? 0) . ' ریال',
                    ],
                ];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Postex getWalletBalance error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getUserProfile(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات پستکس کامل نیست'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint('user/profile'));

            if ($response->successful()) {
                $data = $response->json() ?? [];
                return ['success' => true, 'data' => $data];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Postex getUserProfile error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * استخراج آرایه از پاسخ API که ممکنه توی data/result/results wrap شده باشه
     */
    protected function unwrapList(array $raw): array
    {
        if (isset($raw['data']) && is_array($raw['data'])) return $raw['data'];
        if (isset($raw['result']) && is_array($raw['result'])) return $raw['result'];
        if (isset($raw['results']) && is_array($raw['results'])) return $raw['results'];
        if (isset($raw['items']) && is_array($raw['items'])) return $raw['items'];
        // اگه آرایه خود indexed بود (PHP 8.0 compatible)
        if (!empty($raw) && array_keys($raw) === range(0, count($raw) - 1)) return $raw;
        return [];
    }

    public function getProvinces(): array
    {
        $cached = Cache::get('postex_provinces');
        if (!empty($cached)) return $cached;

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint('location/provinces'));

            if ($response->successful()) {
                $raw  = $response->json() ?? [];
                $data = $this->unwrapList($raw);
                if (!empty($data)) {
                    Cache::put('postex_provinces', $data, 86400);
                    return $data;
                }
                Log::warning('Postex getProvinces empty', ['raw' => $raw]);
            }

            Log::warning('Postex getProvinces failed', ['status' => $response->status(), 'body' => substr($response->body(), 0, 300)]);
            return [];
        } catch (\Exception $e) {
            Log::error('Postex getProvinces error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getCities(?int $provinceCode = null): array
    {
        $cacheKey = 'postex_cities_' . ($provinceCode ?? 'all');
        $cached = Cache::get($cacheKey);
        if (!empty($cached)) return $cached;

        try {
            $url = $provinceCode
                ? $this->endpoint("location/province/{$provinceCode}/cities")
                : $this->endpoint('location/cities');

            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($url);

            if ($response->successful()) {
                $raw  = $response->json() ?? [];
                $data = $this->unwrapList($raw);
                if (!empty($data)) {
                    Cache::put($cacheKey, $data, 86400);
                    return $data;
                }
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Postex getCities error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * محاسبه هزینه جمع آوری و ارسال
     * POST /api/v1/shipping/price
     */
    public function calculateShippingCost(array $data): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات پستکس کامل نیست'];
        }

        try {
            $collectionType = $data['collection_type']
                ?? WarehouseSetting::get('postex_collection_type', 'pick_up');
            $fromCityCode = $data['from_city_code']
                ?? (int) WarehouseSetting::get('postex_from_city_code', 444);

            $payload = [
                'collection_type' => $collectionType,
                'from_city_code'  => $fromCityCode,
                'parcels'         => $data['parcels'] ?? [],
            ];

            if (!empty($data['courier'])) {
                $payload['courier'] = $data['courier'];
            }

            if (!empty($data['value_added_service'])) {
                $payload['value_added_service'] = $data['value_added_service'];
            }

            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('shipping/price'), $payload);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json() ?? []];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Postex calculateShippingCost error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * رهگیری مرسوله با شناسه مرسوله (parcel-no)
     * GET /api/v1/tracking/{parcel-no}
     */
    public function trackShipment(string $parcelNo): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات پستکس کامل نیست.'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint("tracking/{$parcelNo}"));

            if ($response->successful()) {
                $result = $response->json() ?? [];
                return ['success' => true, 'data' => $result];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Postex trackShipment error', ['parcel_no' => $parcelNo, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * رهگیری سفارش با بارکد (courier + tracking-code)
     * GET /api/v1/tracking/{courier}/{tracking-code}
     */
    public function trackByBarcode(string $courier, string $trackingCode): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات پستکس کامل نیست.'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint("tracking/{$courier}/{$trackingCode}"));

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json() ?? []];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Postex trackByBarcode error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * تست endpoint های مختلف برای یافتن مسیر صحیح ثبت مرسوله
     * با payload صحیح بر اساس مستندات رسمی
     */
    public function probeCreateEndpoints(): array
    {
        $collectionType = WarehouseSetting::get('postex_collection_type', 'pick_up');

        $fromCityCode = (int) WarehouseSetting::get('postex_from_city_code', 444);
        $courier      = WarehouseSetting::get('postex_courier', 'post');

        $fromPhone = $this->formatMobile(WarehouseSetting::get('postex_from_phone', '09000000000'));
        [$fromFirst, $fromLast] = $this->splitName(WarehouseSetting::get('postex_from_name', 'فرستنده تست'));

        // payload با ساختار صحیح API پستکس
        $correctPayload = [
            'collection_type' => $collectionType,
            'custom_order_no' => 'TEST-PROBE-' . now()->timestamp,
            'request'         => [
                'label' => false,
            ],
            'remark'          => 'تست اتصال از پنل',
            'courier'         => [
                'name'         => $courier,
                'payment_type' => (string) WarehouseSetting::get('postex_payment_type', '0'),
                'service_type' => (string) WarehouseSetting::get('postex_service_type', '0'),
            ],
            'to' => [
                'contact' => [
                    'first_name'   => 'تست',
                    'last_name'    => 'تست',
                    'mobile_no'    => '09120000000',
                    'telephone_no' => '09120000000',
                ],
                'location' => [
                    'city_code' => 444,
                    'address'   => 'تهران، آدرس تست',
                    'postcode'  => '1234567890',
                ],
            ],
            'from' => [
                'contact' => [
                    'first_name'   => $fromFirst,
                    'last_name'    => $fromLast,
                    'mobile_no'    => $fromPhone,
                    'telephone_no' => WarehouseSetting::get('postex_from_telephone', $fromPhone),
                ],
                'location' => [
                    'city_code' => $fromCityCode,
                    'address'   => WarehouseSetting::get('postex_from_address', 'آدرس مبدا'),
                    'postcode'  => WarehouseSetting::get('postex_from_postcode', '1234567890'),
                ],
            ],
            'parcel_properties' => [
                'total_value'  => 100000,
                'total_weight' => 500,
                'is_fragile'   => false,
                'is_liquid'    => false,
            ],
        ];

        // مسیرهای مختلف برای تست (v1 و v2)
        $paths = [
            'parcel',
            'parcels',
            'order',
            'orders',
            'order/create',
            'order/add',
            'order/batch',
            'parcel/batch',
            'parcel/add',
            'parcel/create',
            'parcel/register',
            'shipment',
            'shipments',
        ];

        $results = [];

        foreach ($paths as $path) {
            foreach (['v1', 'v2'] as $ver) {
                $url = $this->apiUrl . '/api/' . $ver . '/' . $path;
                $key = $ver . '/' . $path;
                try {
                    $response = Http::timeout(8)
                        ->withHeaders($this->getHeaders())
                        ->post($url, $correctPayload);

                    $status = $response->status();
                    if ($status !== 404) {
                        // هر چیزی که 404 نیست مهمه
                        $results['★ ' . $key] = [
                            'status' => $status,
                            'url'    => $url,
                            'body'   => $response->json() ?? substr($response->body(), 0, 400),
                        ];
                    } else {
                        $results[$key] = ['status' => 404, 'url' => $url];
                    }
                } catch (\Exception $e) {
                    $results[$key] = ['status' => 'err', 'url' => $url, 'err' => $e->getMessage()];
                }
            }
        }

        // مرتب‌سازی: نتایج مثبت اول
        uksort($results, fn($a, $b) => str_starts_with($b, '★') <=> str_starts_with($a, '★'));

        return $results;
    }

    /**
     * دریافت اطلاعات مرسوله با شماره سفارش مشتری (custom_order_no)
     * GET /api/v1/parcel/custom-order-no/{custom-order-no}
     */
    public function getParcelByCustomOrderNo(string $customOrderNo): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات پستکس کامل نیست'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint("parcel/custom-order-no/{$customOrderNo}"));

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json() ?? []];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Postex getParcelByCustomOrderNo error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * دریافت اطلاعات مرسوله با شناسه مرسوله (parcel_no)
     * GET /api/v1/parcel/{parcel-no}
     */
    public function getParcelByNo(int $parcelNo): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات پستکس کامل نیست'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint("parcel/{$parcelNo}"));

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json() ?? []];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Postex getParcelByNo error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * تولید برچسب پستی
     * GET /api/v1/parcel/{parcel-no}/label
     */
    public function getLabel(string $parcelNo): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات پستکس کامل نیست'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint("parcel/{$parcelNo}/label"));

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json() ?? []];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Postex getLabel error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * درخواست انصراف از ارسال مرسوله
     * POST /api/v1/parcel/{parcel-no}/cancel
     */
    public function cancelParcel(int $parcelNo, string $reason = ''): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات پستکس کامل نیست'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint("parcel/{$parcelNo}/cancel"), [
                    'reason' => $reason ?: 'انصراف از ارسال',
                ]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json() ?? []];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Postex cancelParcel error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * جستجوی کد شهر مقصد بر اساس نام شهر و استان
     */
    /**
     * نقشه کدهای WooCommerce Iran به نام استان به فارسی
     * WooCommerce ایران استان‌ها را با کد دو حرفی ذخیره می‌کند
     */
    protected static array $wcProvinceCodes = [
        // کدهای ۲ حرفی
        'TH' => 'تهران',       'IS' => 'اصفهان',      'FA' => 'فارس',
        'KH' => 'خراسان رضوی', 'MZ' => 'مازندران',    'GB' => 'گیلان',
        'KR' => 'کرمان',       'KG' => 'کردستان',     'AE' => 'آذربایجان شرقی',
        'AW' => 'آذربایجان غربی', 'AR' => 'اردبیل',   'SM' => 'سمنان',
        'SB' => 'سیستان و بلوچستان', 'CM' => 'چهارمحال و بختیاری',
        'GS' => 'گلستان',      'ZJ' => 'زنجان',       'LO' => 'لرستان',
        'KV' => 'خراسان شمالی','KS' => 'خراسان جنوبی','KZ' => 'خوزستان',
        'KJ' => 'کهگیلویه و بویراحمد', 'QM' => 'قم', 'QZ' => 'قزوین',
        'YZ' => 'یزد',         'EW' => 'کرمانشاه',    'IL' => 'ایلام',
        'BU' => 'بوشهر',       'MK' => 'مرکزی',       'HM' => 'همدان',
        'AL' => 'البرز',       'TE' => 'تهران',        'EF' => 'اصفهان',
        'GI' => 'گیلان',       'GN' => 'گلستان',      'KE' => 'کرمانشاه',
        'KU' => 'کردستان',     'MN' => 'مازندران',    'SE' => 'سمنان',
        'YA' => 'یزد',         'ZA' => 'زنجان',       'KM' => 'کرمان',
        'BS' => 'بوشهر',       'CH' => 'چهارمحال و بختیاری',
        // کدهای ۳ حرفی (برخی سیستم‌های WooCommerce ایران)
        'FRS' => 'فارس',       'THR' => 'تهران',      'ISF' => 'اصفهان',
        'KHR' => 'خراسان رضوی','MZN' => 'مازندران',   'GIL' => 'گیلان',
        'KRM' => 'کرمان',      'KRD' => 'کردستان',    'KHZ' => 'خوزستان',
        'AZE' => 'آذربایجان شرقی', 'AZW' => 'آذربایجان غربی',
        'ARD' => 'اردبیل',     'SEM' => 'سمنان',      'SIS' => 'سیستان و بلوچستان',
        'CHB' => 'چهارمحال و بختیاری', 'GLN' => 'گلستان',
        'ZNJ' => 'زنجان',      'LRS' => 'لرستان',     'KHN' => 'خراسان شمالی',
        'KHS' => 'خراسان جنوبی', 'KBK' => 'کهگیلویه و بویراحمد',
        'QOM' => 'قم',         'QAZ' => 'قزوین',      'YZD' => 'یزد',
        'KRH' => 'کرمانشاه',   'ILM' => 'ایلام',      'BSH' => 'بوشهر',
        'MRK' => 'مرکزی',      'HMD' => 'همدان',      'ALB' => 'البرز',
    ];

    public function findCityCode(string $cityName, string $provinceName = ''): ?int
    {
        $cityName = trim($cityName);
        if (empty($cityName)) return null;

        // ابتدا از جدول mapping دستی ادمین استفاده کن (اولویت بالا)
        $cityMapRaw = WarehouseSetting::get('postex_city_map', '');
        if (!empty($cityMapRaw)) {
            foreach (explode("\n", $cityMapRaw) as $line) {
                $line = trim($line);
                if (empty($line) || !str_contains($line, ':')) continue;
                [$mapCity, $mapCode] = explode(':', $line, 2);
                if (trim($mapCity) === $cityName && is_numeric(trim($mapCode))) {
                    return (int) trim($mapCode);
                }
            }
        }

        // تبدیل کد WooCommerce به نام فارسی استان (مثلاً FA → فارس)
        $provinceName = trim($provinceName);
        if (!empty($provinceName) && isset(self::$wcProvinceCodes[strtoupper($provinceName)])) {
            $provinceName = self::$wcProvinceCodes[strtoupper($provinceName)];
        }

        // اگر استان داریم، اول از طریق استان پیدا کن
        if (!empty($provinceName)) {
            $provinces = $this->getProvinces();
            $provinceCode = null;
            foreach ($provinces as $prov) {
                $pName = $prov['name'] ?? $prov['title'] ?? '';
                if (str_contains($pName, $provinceName) || str_contains($provinceName, $pName)) {
                    $provinceCode = $prov['code'] ?? $prov['id'] ?? null;
                    break;
                }
            }
            if ($provinceCode) {
                $cities = $this->getCities($provinceCode);
                foreach ($cities as $city) {
                    $cName = $city['name'] ?? $city['title'] ?? '';
                    if (str_contains($cName, $cityName) || str_contains($cityName, $cName)) {
                        return (int)($city['code'] ?? $city['id'] ?? 0) ?: null;
                    }
                }
            }
        }

        // fallback: جستجو در همه شهرها
        $allCities = $this->getCities();
        foreach ($allCities as $city) {
            $cName = $city['name'] ?? $city['title'] ?? '';
            if (str_contains($cName, $cityName) || str_contains($cityName, $cName)) {
                return (int)($city['code'] ?? $city['id'] ?? 0) ?: null;
            }
        }

        return null;
    }

    /**
     * ثبت مرسوله در پستکس
     * endpoint صحیح: POST /api/v1/parcels (جمع)
     * ساختار تخت (flat) با فیلدهای اجباری: to, from, courier, parcelproperties
     */
    public function createShipment(array $data): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'پستکس تنظیم نشده (API Key خالی)'];
        }

        $collectionType = WarehouseSetting::get('postex_collection_type', 'pick_up');
        $courierName    = WarehouseSetting::get('postex_courier', 'post');

        $postcode = $this->normalizePostalCode($data['recipient_postal_code'] ?? '');
        if (empty($postcode) || strlen($postcode) !== 10) {
            return ['success' => false, 'message' => 'کد پستی نامعتبر است (باید ۱۰ رقم باشد): "' . ($postcode ?: 'خالی') . '"'];
        }

        $toCityCode = $data['to_city_code'] ?? null;
        if (empty($toCityCode)) {
            // اگه شهر پیدا نشد از fallback city code استفاده کن
            $fallback = (int) WarehouseSetting::get('postex_fallback_city_code', 0);
            if ($fallback > 0) {
                Log::warning('Postex: city not found, using fallback city code', [
                    'order' => $data['external_order_id'] ?? '',
                    'city'  => $data['recipient_city'] ?? '',
                    'fallback_city_code' => $fallback,
                ]);
                $toCityCode = $fallback;
            } else {
                return ['success' => false, 'message' => 'کد شهر مقصد یافت نشد — شهر "' . ($data['recipient_city'] ?? '?') . '" در سیستم پستکس ثبت نشده. یک "کد شهر پیش‌فرض" در تنظیمات پستکس وارد کنید.'];
            }
        }
        $toCityCode = (int) $toCityCode;

        $weight      = (int)($data['weight'] ?? 500);
        $totalValue  = (int)($data['value'] ?? 100000);
        $description = $data['description'] ?? ('سفارش ' . ($data['external_order_id'] ?? ''));
        $orderNo     = $data['external_order_id'] ?? '';

        // اطلاعات فرستنده از تنظیمات
        $fromCityCode = (int) WarehouseSetting::get('postex_from_city_code', 444);
        $fromName     = WarehouseSetting::get('postex_from_name', 'فرستنده');
        $fromPhone    = $this->formatMobile(WarehouseSetting::get('postex_from_phone', '09000000000'));
        $fromAddress  = WarehouseSetting::get('postex_from_address', 'آدرس مبدا');
        $fromPostcode = $this->normalizePostalCode(WarehouseSetting::get('postex_from_postcode', '1234567890'));

        // تفکیک نام و نام خانوادگی گیرنده
        $recipientFullName = $data['recipient_name'] ?? '';
        [$recipientFirst, $recipientLast] = $this->splitName($recipientFullName);

        // تفکیک نام و نام خانوادگی فرستنده
        [$fromFirst, $fromLast] = $this->splitName($fromName);

        // شماره ثابت (اگه نداریم از موبایل استفاده میکنیم)
        $recipientMobile = $this->formatMobile($data['recipient_mobile'] ?? '');
        $recipientPhone  = $data['recipient_phone'] ?? $recipientMobile;
        $fromTelephone   = WarehouseSetting::get('postex_from_telephone', $fromPhone);

        // ساختار payload - snake_case مثل بقیه فیلدهای API که کار میکنن:
        // collection_type, custom_order_no, city_code همه snake_case هستن و کار میکنن
        // پس فیلدهای ترکیبی هم باید snake_case باشن
        $payload = [
            'collection_type' => $collectionType,
            'custom_order_no' => (string) $orderNo,
            'request'         => [
                'label' => false,
            ],
            'remark'          => $description,
            'courier'         => [
                'name'         => $courierName,
                'payment_type' => (string) WarehouseSetting::get('postex_payment_type', '0'),
                'service_type' => (string) WarehouseSetting::get('postex_service_type', '0'),
            ],
            'to' => [
                'contact' => [
                    'first_name'   => $recipientFirst,
                    'last_name'    => $recipientLast,
                    'mobile_no'    => $recipientMobile,
                    'telephone_no' => $recipientPhone,
                ],
                'location' => [
                    'city_code' => $toCityCode,
                    'address'   => $data['recipient_address'] ?? '',
                    'postcode'  => $postcode,
                ],
            ],
            'from' => [
                'contact' => [
                    'first_name'   => $fromFirst,
                    'last_name'    => $fromLast,
                    'mobile_no'    => $fromPhone,
                    'telephone_no' => $fromTelephone,
                ],
                'location' => [
                    'city_code' => $fromCityCode,
                    'address'   => $fromAddress,
                    'postcode'  => $fromPostcode,
                ],
            ],
            'parcel_properties' => [
                'total_value'  => $totalValue,
                'total_weight' => $weight,
                'is_fragile'   => false,
                'is_liquid'    => false,
            ],
        ];

        Log::info('Postex createShipment payload', ['order' => $orderNo, 'payload' => $payload]);

        try {
            // endpoint صحیح: POST /api/v1/parcels (جمع با s)
            $response = Http::timeout(30)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('parcels'), $payload);

            $body = $response->json() ?? [];
            Log::info('Postex createShipment response', [
                'order'  => $orderNo,
                'status' => $response->status(),
                'body'   => $body,
            ]);

            if ($response->status() === 402) {
                return ['success' => false, 'message' => 'پستکس: موجودی کیف پول کافی نیست. لطفاً کیف پول را شارژ کنید.'];
            }

            if ($response->successful() && ($body['isSuccess'] ?? !empty($body))) {
                // استخراج بارکد / tracking از پاسخ
                $barcode = $this->extractBarcode($body);

                if (!empty($barcode)) {
                    return ['success' => true, 'data' => ['barcode' => (string)$barcode, 'raw' => $body]];
                }

                return ['success' => false, 'message' => 'پستکس: ثبت شد ولی بارکد در پاسخ نیامد — ' . json_encode($body, JSON_UNESCAPED_UNICODE)];
            }

            $errorMsg = $body['message'] ?? '';
            if (!empty($body['invalid_fields'])) {
                $errorMsg .= ' | فیلدهای نامعتبر: ' . json_encode($body['invalid_fields'], JSON_UNESCAPED_UNICODE);
            }
            if (!empty($body['errors'])) {
                $errorMsg .= ' | خطاها: ' . json_encode($body['errors'], JSON_UNESCAPED_UNICODE);
            }
            // برای 500 یا هر خطایی که پیام مشخصی ندارد، کل body رو نشون بده
            if (empty(trim($errorMsg))) {
                $rawBody = $response->body();
                $errorMsg = !empty($body)
                    ? json_encode($body, JSON_UNESCAPED_UNICODE)
                    : substr($rawBody, 0, 500);
            }
            $errorMsg .= ' [v11|ct:' . ($payload['collection_type'] ?? '?') . '|courier:' . ($payload['courier']['name'] ?? '?') . '|to_city:' . ($payload['to']['location']['city_code'] ?? 'null') . '|from_city:' . ($payload['from']['location']['city_code'] ?? 'null') . '|pt:' . ($payload['courier']['payment_type'] ?? '?') . '|st:' . ($payload['courier']['service_type'] ?? '?') . ']';
            return ['success' => false, 'message' => 'پستکس (HTTP ' . $response->status() . '): ' . $errorMsg];
        } catch (\Exception $e) {
            Log::error('Postex createShipment error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * استخراج بارکد/tracking از پاسخ پستکس
     */
    protected function extractBarcode(array $body): ?string
    {
        // حالت‌های مختلف پاسخ پستکس
        $candidates = [
            // { data: [{ parcel_no: ..., tracking_no: ... }] }
            $body['data'][0]['tracking_no'] ?? null,
            $body['data'][0]['parcel_no'] ?? null,
            $body['data'][0]['tracking_number'] ?? null,
            $body['data'][0]['barcode'] ?? null,
            // { data: { tracking_no: ... } }
            $body['data']['tracking_no'] ?? null,
            $body['data']['parcel_no'] ?? null,
            // flat response
            $body['tracking_no'] ?? null,
            $body['parcel_no'] ?? null,
            $body['tracking_number'] ?? null,
            $body['barcode'] ?? null,
            // array response [{ ... }]
            $body[0]['tracking_no'] ?? null,
            $body[0]['parcel_no'] ?? null,
        ];

        foreach ($candidates as $val) {
            if (!empty($val)) return (string) $val;
        }

        return null;
    }

    /**
     * تفکیک نام کامل به نام و نام خانوادگی
     */
    protected function splitName(string $fullName): array
    {
        $fullName = trim($fullName);
        if (empty($fullName)) {
            return ['', ''];
        }
        $parts = preg_split('/\s+/', $fullName, 2);
        $firstName = $parts[0] ?? '';
        $lastName  = $parts[1] ?? $firstName; // اگه فقط یه کلمه بود، هر دو فیلد رو پر کن
        return [$firstName, $lastName];
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

    protected function normalizePostalCode(?string $postalCode): string
    {
        if (!$postalCode) return '';
        $postalCode = str_replace(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $postalCode
        );
        $postalCode = str_replace(
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $postalCode
        );
        $postalCode = preg_replace('/\D/', '', $postalCode);
        return $postalCode;
    }
}
