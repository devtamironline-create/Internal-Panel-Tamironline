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

    public function getProvinces(): array
    {
        $cached = Cache::get('postex_provinces');
        if (!empty($cached)) return $cached;

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint('location/provinces'));

            if ($response->successful()) {
                $data = $response->json() ?? [];
                if (!empty($data)) {
                    Cache::put('postex_provinces', $data, 86400);
                    return $data;
                }
            }

            Log::warning('Postex getProvinces failed', ['response' => $response->body()]);
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
                $data = $response->json() ?? [];
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
                ?? WarehouseSetting::get('postex_collection_type', 'postex_drop_off');
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
        $collectionType = WarehouseSetting::get('postex_collection_type', 'postex_drop_off');

        $fromCityCode = (int) WarehouseSetting::get('postex_from_city_code', 444);
        $courier      = WarehouseSetting::get('postex_courier', 'post');

        // payload با ساختار صحیح - courier آبجکت + فیلد request
        $correctPayload = [
            'collection_type' => $collectionType,
            'custom_order_no' => 'TEST-PROBE-' . now()->timestamp,
            'request'         => [
                'label' => false,
            ],
            'remark'          => 'تست اتصال از پنل',
            'courier'         => [
                'name' => $courier,
            ],
            'to' => [
                'contact' => [
                    'name'         => 'تست تست',
                    'cellphone_no' => '09120000000',
                ],
                'location' => [
                    'city_code' => 444,
                    'address'   => 'تهران، آدرس تست',
                    'postcode'  => '1234567890',
                ],
            ],
            'from' => [
                'contact' => [
                    'name'         => WarehouseSetting::get('postex_from_name', 'فرستنده'),
                    'cellphone_no' => WarehouseSetting::get('postex_from_phone', '09000000000'),
                ],
                'location' => [
                    'city_code' => $fromCityCode,
                    'address'   => WarehouseSetting::get('postex_from_address', 'آدرس مبدا'),
                    'postcode'  => WarehouseSetting::get('postex_from_postcode', '1234567890'),
                ],
            ],
            'parcelproperties' => [
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
    public function findCityCode(string $cityName, string $provinceName = ''): ?int
    {
        $cityName = trim($cityName);
        if (empty($cityName)) return null;

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

        $collectionType = WarehouseSetting::get('postex_collection_type', 'postex_drop_off');
        $courier        = WarehouseSetting::get('postex_courier', 'post');

        $postcode = $this->normalizePostalCode($data['recipient_postal_code'] ?? '');
        if (empty($postcode) || strlen($postcode) !== 10) {
            return ['success' => false, 'message' => 'کد پستی نامعتبر است (باید ۱۰ رقم باشد): "' . ($postcode ?: 'خالی') . '"'];
        }

        $toCityCode  = $data['to_city_code'] ?? null;
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

        // ساختار payload بر اساس خطای API:
        // - courier باید آبجکت باشه (CreateParcelCourierDto) نه رشته
        // - فیلد request اجباری هست
        $payload = [
            'collection_type' => $collectionType,
            'custom_order_no' => (string) $orderNo,
            'request'         => [
                'label' => true,
            ],
            'remark'          => $description,
            'courier'         => [
                'name' => $courier,
            ],
            'to' => [
                'contact' => [
                    'name'         => $data['recipient_name'] ?? '',
                    'cellphone_no' => $this->formatMobile($data['recipient_mobile'] ?? ''),
                ],
                'location' => [
                    'city_code' => $toCityCode,
                    'address'   => $data['recipient_address'] ?? '',
                    'postcode'  => $postcode,
                ],
            ],
            'from' => [
                'contact' => [
                    'name'         => $fromName,
                    'cellphone_no' => $fromPhone,
                ],
                'location' => [
                    'city_code' => $fromCityCode,
                    'address'   => $fromAddress,
                    'postcode'  => $fromPostcode,
                ],
            ],
            'parcelproperties' => [
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

            $errorMsg = $body['message'] ?? 'خطای نامشخص';
            // همیشه invalid_fields رو نمایش بده تا بفهمیم کدوم فیلد مشکل داره
            if (!empty($body['invalid_fields'])) {
                $errorMsg .= ' | فیلدهای نامعتبر: ' . json_encode($body['invalid_fields'], JSON_UNESCAPED_UNICODE);
            }
            // اگه errors هم داشت نشون بده
            if (!empty($body['errors'])) {
                $errorMsg .= ' | خطاها: ' . json_encode($body['errors'], JSON_UNESCAPED_UNICODE);
            }
            // اگه هیچ جزئیاتی نبود کل body رو نشون بده
            if (empty($body['invalid_fields']) && empty($body['errors']) && empty($body['message'])) {
                $errorMsg = json_encode($body, JSON_UNESCAPED_UNICODE);
            }
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
