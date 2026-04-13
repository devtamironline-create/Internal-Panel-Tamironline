<?php

namespace Modules\Warehouse\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\WarehouseSetting;

class Cod24Service
{
    protected string $apiUrl;
    protected ?string $username;
    protected ?string $password;

    public function __construct()
    {
        $this->apiUrl   = rtrim(WarehouseSetting::get('cod24_api_url', 'https://api.cod24.ir'), '/');
        $this->username = WarehouseSetting::get('cod24_username');
        $this->password = WarehouseSetting::get('cod24_password');
    }

    public function isConfigured(): bool
    {
        return !empty($this->username) && !empty($this->password);
    }

    /**
     * دریافت توکن از API
     * POST /api/Account/getToken
     */
    public function getToken(bool $forceRefresh = false): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $cacheKey = 'cod24_bearer_token';
        if (!$forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (!empty($cached)) {
                return $cached;
            }
        }

        try {
            $response = Http::timeout(15)
                ->post($this->apiUrl . '/api/Account/getToken', [
                    'userName' => $this->username,
                    'password' => $this->password,
                ]);

            if ($response->successful()) {
                $data = $response->json() ?? [];
                $token = $data['token'] ?? $data['access_token'] ?? $data['data']['token'] ?? null;

                if (empty($token) && is_string($response->body()) && strlen($response->body()) > 20) {
                    $token = trim($response->body(), '"');
                }

                if (!empty($token)) {
                    // توکن رو برای ۲۳ ساعت کش کن
                    Cache::put($cacheKey, $token, 82800);
                    WarehouseSetting::set('cod24_token', $token);
                    return $token;
                }

                Log::warning('Cod24 getToken: no token in response', ['body' => $data]);
                return null;
            }

            Log::warning('Cod24 getToken failed', ['status' => $response->status(), 'body' => substr($response->body(), 0, 500)]);
            return null;
        } catch (\Exception $e) {
            Log::error('Cod24 getToken error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function getHeaders(): array
    {
        $token = $this->getToken();
        return [
            'Authorization' => 'Bearer ' . ($token ?? ''),
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ];
    }

    protected function endpoint(string $path): string
    {
        return $this->apiUrl . '/api/' . ltrim($path, '/');
    }

    /**
     * تست اتصال: سعی میکنه توکن بگیره و موجودی کیف پول رو چک کنه
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات COD24 کامل نیست (نام کاربری یا رمز عبور وارد نشده)'];
        }

        try {
            $token = $this->getToken(true);
            if (empty($token)) {
                return ['success' => false, 'message' => 'خطا در دریافت توکن — نام کاربری یا رمز عبور اشتباه است'];
            }

            // تست با دریافت موجودی کیف پول
            $wallet = $this->getWalletAmount();
            if ($wallet['success'] ?? false) {
                $amount = $wallet['data']['amount'] ?? 0;
                return [
                    'success' => true,
                    'message' => 'اتصال برقرار است. موجودی کیف پول: ' . number_format($amount) . ' ریال',
                ];
            }

            return ['success' => true, 'message' => 'اتصال برقرار است (توکن دریافت شد).'];
        } catch (\Exception $e) {
            Log::error('Cod24 testConnection error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطا در اتصال: ' . $e->getMessage()];
        }
    }

    /**
     * دریافت موجودی کیف پول
     * POST /api/Wallet/getWalletAmount
     */
    public function getWalletAmount(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات COD24 کامل نیست'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('Wallet/getWalletAmount'));

            if ($response->successful()) {
                $data = $response->json() ?? [];
                $amount = $data['amount'] ?? $data['data']['amount'] ?? $data['walletAmount'] ?? 0;
                return [
                    'success' => true,
                    'data' => [
                        'amount'    => $amount,
                        'formatted' => number_format($amount) . ' ریال',
                        'raw'       => $data,
                    ],
                ];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Cod24 getWalletAmount error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * دریافت لیست استان‌ها
     * POST /api/State/getStates
     */
    public function getStates(): array
    {
        $cached = Cache::get('cod24_states');
        if (!empty($cached)) return $cached;

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('State/getStates'));

            if ($response->successful()) {
                $data = $this->unwrapList($response->json() ?? []);
                if (!empty($data)) {
                    Cache::put('cod24_states', $data, 86400);
                    return $data;
                }
            }

            Log::warning('Cod24 getStates failed', ['status' => $response->status()]);
            return [];
        } catch (\Exception $e) {
            Log::error('Cod24 getStates error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * دریافت شهرهای پستی
     * POST /api/City/getPostCities
     */
    public function getPostCities(): array
    {
        $cached = Cache::get('cod24_post_cities');
        if (!empty($cached)) return $cached;

        try {
            $response = Http::timeout(20)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('City/getPostCities'));

            if ($response->successful()) {
                $data = $this->unwrapList($response->json() ?? []);
                if (!empty($data)) {
                    // لاگ ساختار اولین شهر برای دیباگ فیلدها
                    Log::info('Cod24 getPostCities sample', ['first_item' => $data[0] ?? [], 'total' => count($data)]);
                    Cache::put('cod24_post_cities', $data, 86400);
                    return $data;
                }
            }

            Log::warning('Cod24 getPostCities failed', ['status' => $response->status()]);
            return [];
        } catch (\Exception $e) {
            Log::error('Cod24 getPostCities error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * دریافت شهرهای یک استان
     * POST /api/City/getCities
     */
    public function getCities(?int $stateCode = null): array
    {
        $cacheKey = 'cod24_cities_' . ($stateCode ?? 'all');
        $cached = Cache::get($cacheKey);
        if (!empty($cached)) return $cached;

        try {
            $payload = $stateCode ? ['stateCode' => $stateCode] : [];
            $response = Http::timeout(20)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('City/getCities'), $payload);

            if ($response->successful()) {
                $data = $this->unwrapList($response->json() ?? []);
                if (!empty($data)) {
                    Cache::put($cacheKey, $data, 86400);
                    return $data;
                }
            }

            Log::warning('Cod24 getCities failed', ['status' => $response->status(), 'stateCode' => $stateCode]);
            return [];
        } catch (\Exception $e) {
            Log::error('Cod24 getCities error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * جستجوی یک شهر با کد
     * GET /api/City/getCity?code=XXX
     */
    public function getCity(int $code): array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->endpoint('City/getCity'), ['code' => $code]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json() ?? []];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Cod24 getCity error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * محاسبه هزینه ارسال پستی
     * POST /api/Order/getPostPrice
     */
    public function getPostPrice(array $data): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات COD24 کامل نیست'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('Order/getPostPrice'), $data);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json() ?? []];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Cod24 getPostPrice error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * ثبت سفارش در COD24
     * POST /api/Order/addOrder
     */
    public function createShipment(array $data): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'COD24 تنظیم نشده (نام کاربری یا رمز عبور خالی)'];
        }

        $idTypeSend  = (int) WarehouseSetting::get('cod24_id_type_send', 1);
        $idPayMethod = (int) WarehouseSetting::get('cod24_id_pay_method', 0);

        [$firstName, $lastName] = $this->splitName($data['recipient_name'] ?? '');
        $mobile     = $this->formatMobile($data['recipient_mobile'] ?? '');
        $postalCode = $this->normalizePostalCode($data['recipient_postal_code'] ?? '');
        $address    = $data['recipient_address'] ?? 'آدرس نامشخص';
        $cityCode   = (int) ($data['to_city_code'] ?? 0);
        $citySource = $cityCode ? 'findCityCode' : '';
        $weight     = (int) ($data['weight'] ?? 500);
        $value      = (int) ($data['value'] ?? 0);
        $orderNo    = $data['external_order_id'] ?? '';

        if (empty($cityCode)) {
            $cityCode = (int) WarehouseSetting::get('cod24_fallback_city_code', 0);
            $citySource = 'fallback';
            if (empty($cityCode)) {
                return ['success' => false, 'message' => 'کد شهر مقصد یافت نشد — شهر "' . ($data['recipient_city'] ?? '?') . '" در نقشه شهرها یا API پیدا نشد. لطفاً کد صحیح را از جستجوی شهرها پیدا کنید و در "نقشه کد شهرهای دستی" وارد کنید.'];
            }
        }

        Log::info('Cod24 createShipment cityCode', [
            'order' => $orderNo, 'city' => $data['recipient_city'] ?? '?',
            'cityCode' => $cityCode, 'source' => $citySource,
        ]);

        // کد پستی باید ۱۰ رقم باشه وگرنه ۰۰۰۰۰۰۰۰۰۰ بفرست (مطابق پلاگین رسمی)
        if (empty($postalCode) || strlen($postalCode) != 10) {
            $postalCode = '0000000000';
        }

        $payload = [
            'idOrderShop'          => (int) preg_replace('/\D/', '', $orderNo) ?: 0,
            'cityCode'             => $cityCode,
            'idTypeSend'           => $idTypeSend,
            'idPayMethod'          => $idPayMethod,
            'description'          => $data['description'] ?? ('سفارش ' . $orderNo),
            'firstName'            => $firstName,
            'lastName'             => $lastName,
            'mobile'               => $mobile,
            'postalCode'           => $postalCode,
            'nationalCode'         => '',
            'address'              => $address,
            'nonStandardPackage'   => false,
            'finalPayAmountCustomer' => (float) $value,
            'requestOrderProducts' => $this->buildProducts($data),
            'totalWeight'          => (int) $weight,
            'contentParcell'       => $data['description'] ?? 'کالا',
            'idCartonType'         => $this->resolveCartonType($data['box_size_id'] ?? null),
            'isCalcSrvByWeight'    => true,
        ];

        Log::info('Cod24 createShipment payload', ['order' => $orderNo, 'payload' => $payload]);

        try {
            $response = Http::timeout(30)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('Order/addOrder'), $payload);

            $body = $response->json() ?? [];
            Log::info('Cod24 createShipment response', [
                'order'  => $orderNo,
                'status' => $response->status(),
                'body'   => $body,
            ]);

            if ($response->successful()) {
                $barcode = $this->extractBarcode($body);
                if (!empty($barcode)) {
                    return ['success' => true, 'data' => ['barcode' => (string) $barcode, 'raw' => $body]];
                }

                // بررسی وضعیت خطا در body
                $isSuccess = $body['isSuccess'] ?? $body['success'] ?? $body['status'] ?? null;
                if ($isSuccess === false || $isSuccess === 0) {
                    $msg = $body['message'] ?? $body['errorMessage'] ?? json_encode($body, JSON_UNESCAPED_UNICODE);
                    $msg .= ' [کد شهر ارسالی: ' . $cityCode . ' — منبع: ' . $citySource . ' — شهر: ' . ($data['recipient_city'] ?? '?') . ']';
                    $msg .= ' — برای رفع: کد صحیح شهر را در تنظیمات COD24 > "نقشه کد شهرهای دستی" وارد کنید';
                    return ['success' => false, 'message' => 'COD24: ' . $msg];
                }

                return ['success' => false, 'message' => 'COD24: ثبت شد ولی بارکد در پاسخ نیامد — ' . json_encode($body, JSON_UNESCAPED_UNICODE)];
            }

            $errorMsg = $body['message'] ?? $body['errorMessage'] ?? '';
            // نمایش جزئیات خطاهای validation
            if (!empty($body['errors'])) {
                $details = [];
                foreach ($body['errors'] as $err) {
                    $details[] = is_array($err) ? ($err['message'] ?? json_encode($err, JSON_UNESCAPED_UNICODE)) : (string) $err;
                }
                $errorMsg .= ' | جزئیات: ' . implode(' — ', $details);
            }
            if (empty(trim($errorMsg))) {
                $errorMsg = json_encode($body, JSON_UNESCAPED_UNICODE) ?: substr($response->body(), 0, 500);
            }
            return ['success' => false, 'message' => 'COD24 (HTTP ' . $response->status() . '): ' . $errorMsg];
        } catch (\Exception $e) {
            Log::error('Cod24 createShipment error', ['order' => $orderNo, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * دریافت بارکدها
     * POST /api/Order/getBarcodes
     */
    public function getBarcodes(array $params = []): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات COD24 کامل نیست'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('Order/getBarcodes'), $params);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json() ?? []];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Cod24 getBarcodes error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * وضعیت بارکد
     * POST /api/Order/getBarcodeStatus
     */
    public function getBarcodeStatus(string $barcode): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات COD24 کامل نیست'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('Order/getBarcodeStatus'), [
                    'barcode' => $barcode,
                ]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json() ?? []];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Cod24 getBarcodeStatus error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * رهگیری مرسوله
     * GET /tracking/{cod24barcode}
     */
    public function trackShipment(string $barcode): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات COD24 کامل نیست.'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->get($this->apiUrl . '/tracking/' . $barcode);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json() ?? ['body' => $response->body()]];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Cod24 trackShipment error', ['barcode' => $barcode, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * لغو سفارش
     * POST /api/Order/cancelOrder
     */
    public function cancelOrder(string $barcode): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات COD24 کامل نیست'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('Order/cancelOrder'), [
                    'barcode' => $barcode,
                ]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json() ?? []];
            }

            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::error('Cod24 cancelOrder error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * تعلیق سفارش برای تولید بارکد پستی
     * POST /api/Order/suspendOrder
     * پارامتر: آرایه‌ای از آبجکت‌ها [{"serial": int, "idOrderShop": "string"}]
     * پاسخ: [{"barcode": "...", "isSuccess": true, "code": 0}]
     */
    public function suspendOrder(string $serial, string $orderShopId = ''): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات COD24 کامل نیست'];
        }

        try {
            // API آرایه‌ای از آبجکت‌ها می‌خواد — دقیقاً مطابق پلاگین رسمی COD24
            // serial: int, idOrderShop: string (strval)
            $payload = [
                [
                    'serial'      => (int) $serial,
                    'idOrderShop' => (string) ($orderShopId ?: $serial),
                ],
            ];

            $jsonBody = json_encode($payload);
            Log::info('Cod24 suspendOrder request', ['serial' => $serial, 'json' => $jsonBody]);

            // ارسال مستقیم JSON — Http::post با آرایه عددی درست کار نمی‌کنه
            $response = Http::timeout(30)
                ->withHeaders($this->getHeaders())
                ->withBody($jsonBody, 'application/json')
                ->post($this->endpoint('Order/suspendOrder'));

            $body = $response->json() ?? [];
            Log::info('Cod24 suspendOrder response', [
                'serial' => $serial,
                'status' => $response->status(),
                'body'   => $body,
            ]);

            if ($response->successful()) {
                // پاسخ آرایه‌ای از نتایج هست — اولین آیتم رو بگیر
                $item = $body[0] ?? $body;
                $isSuccess = $item['isSuccess'] ?? false;
                $code = $item['code'] ?? -1;
                $postBarcode = $item['barcode'] ?? null;

                if ($isSuccess && $code == 0 && !empty($postBarcode)) {
                    return [
                        'success'      => true,
                        'data'         => $body,
                        'post_barcode' => (string) $postBarcode,
                    ];
                }

                // ممکنه suspend موفق باشه ولی بارکد نداشته باشه
                if ($isSuccess) {
                    return [
                        'success'      => true,
                        'data'         => $body,
                        'post_barcode' => !empty($postBarcode) ? (string) $postBarcode : null,
                    ];
                }

                $errorMsg = $item['message'] ?? $item['errorMessage'] ?? json_encode($body, JSON_UNESCAPED_UNICODE);
                return ['success' => false, 'message' => 'COD24 suspend: ' . $errorMsg];
            }

            $errorMsg = $body['message'] ?? $body[0]['message'] ?? '';
            if (!empty($body['errors'])) {
                $details = [];
                foreach ($body['errors'] as $err) {
                    $details[] = is_array($err) ? ($err['message'] ?? json_encode($err, JSON_UNESCAPED_UNICODE)) : (string) $err;
                }
                $errorMsg .= ' | جزئیات: ' . implode(' — ', $details);
            }
            if (empty(trim($errorMsg))) {
                $errorMsg = substr($response->body(), 0, 500);
            }
            return ['success' => false, 'message' => 'COD24 suspendOrder (HTTP ' . $response->status() . '): ' . $errorMsg];
        } catch (\Exception $e) {
            Log::error('Cod24 suspendOrder error', ['serial' => $serial, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * استخراج بارکد پستی از پاسخ API
     */
    protected function extractPostBarcode(array $body): ?string
    {
        // بررسی تمام فیلدهای محتمل در سطوح مختلف
        $roots = [$body, $body['data'] ?? [], $body['result'] ?? []];

        $fieldNames = [
            'postBarcode', 'postalBarcode', 'barcodePosti', 'postCode',
            'post_barcode', 'postal_barcode', 'barcodePost',
            'trackingCode', 'postTrackingCode',
        ];

        foreach ($roots as $root) {
            if (!is_array($root)) continue;
            foreach ($fieldNames as $field) {
                $val = $root[$field] ?? null;
                if (!empty($val) && is_string($val) && strlen($val) > 10) {
                    return $val;
                }
            }
        }

        return null;
    }

    /**
     * استخراج بارکد پستی از پاسخ getBarcodeStatus
     * public برای استفاده در PrintController
     */
    public function extractPostBarcodeFromStatus(array $data): ?string
    {
        return $this->extractPostBarcode($data);
    }

    /**
     * پیدا کردن کد شهر بر اساس نام شهر
     */
    public function findCityCode(string $cityName, string $provinceName = ''): ?int
    {
        $cityName = trim($cityName);
        if (empty($cityName)) return null;

        // ترجمه کد ووکامرس (FRS) به فارسی (فارس)
        $wcMap = TapinService::getWcStateMap();
        $provincePersian = trim($provinceName);
        if (!empty($provincePersian) && isset($wcMap[strtoupper($provincePersian)])) {
            $provincePersian = $wcMap[strtoupper($provincePersian)];
        }

        Log::info('Cod24 findCityCode: start', [
            'city' => $cityName, 'province_raw' => $provinceName, 'province_fa' => $provincePersian,
        ]);

        // ۱) نقشه دستی ادمین (اولویت بالا)
        $cityMapRaw = WarehouseSetting::get('cod24_city_map', '');
        if (!empty($cityMapRaw)) {
            foreach (explode("\n", $cityMapRaw) as $line) {
                $line = trim($line);
                if (empty($line) || !str_contains($line, ':')) continue;
                [$mapCity, $mapCode] = explode(':', $line, 2);
                if ($this->normalizePersian(trim($mapCity)) === $this->normalizePersian($cityName) && is_numeric(trim($mapCode))) {
                    Log::info('Cod24 findCityCode: found in manual map', ['city' => $cityName, 'code' => (int) trim($mapCode)]);
                    return (int) trim($mapCode);
                }
            }
        }

        // ۲) جستجو بر اساس استان (getCities) — اگه استان داریم
        if (!empty($provincePersian)) {
            $cod24StateCode = $this->findStateCode($provincePersian);
            Log::info('Cod24 findCityCode: state lookup', ['province' => $provincePersian, 'stateCode' => $cod24StateCode]);
            if ($cod24StateCode) {
                $stateCities = $this->getCities($cod24StateCode);
                Log::info('Cod24 findCityCode: getCities result', ['stateCode' => $cod24StateCode, 'count' => count($stateCities)]);
                if (!empty($stateCities)) {
                    $found = $this->matchCityInList($cityName, $stateCities);
                    if ($found) {
                        Log::info('Cod24 findCityCode: ✓ found via getCities(state)', [
                            'city' => $cityName, 'stateCode' => $cod24StateCode, 'cityCode' => $found, 'source' => 'getCities',
                        ]);
                        return $found;
                    }
                }
            }
        }

        // ۳) جستجو در شهرهای پستی (getPostCities)
        $allPostCities = $this->getPostCities();
        Log::info('Cod24 findCityCode: getPostCities count', ['count' => count($allPostCities)]);
        if (!empty($allPostCities)) {
            // اول فقط شهرهای همون استان رو فیلتر کن (رفع مشکل شهرهای هم‌نام مثل اردکان)
            $cod24StateCode = isset($cod24StateCode) ? $cod24StateCode : $this->findStateCode($provincePersian);
            if ($cod24StateCode) {
                $filteredByState = array_filter($allPostCities, function ($c) use ($cod24StateCode) {
                    return (int) ($c['stateCode'] ?? $c['state_code'] ?? 0) === $cod24StateCode;
                });
                if (!empty($filteredByState)) {
                    $found = $this->matchCityInList($cityName, $filteredByState);
                    if ($found) {
                        Log::info('Cod24 findCityCode: ✓ found via getPostCities+stateFilter', [
                            'city' => $cityName, 'stateCode' => $cod24StateCode, 'cityCode' => $found,
                        ]);
                        return $found;
                    }
                }
            }

            // فالبک: جستجو در همه شهرها (بدون فیلتر استان)
            $found = $this->matchCityInList($cityName, $allPostCities);
            if ($found) {
                Log::info('Cod24 findCityCode: ✓ found via getPostCities (no state filter)', [
                    'city' => $cityName, 'cityCode' => $found, 'source' => 'getPostCities',
                ]);
                return $found;
            }
        }

        Log::warning('Cod24 findCityCode: ✗ city not found anywhere', [
            'city' => $cityName, 'province' => $provincePersian,
            'getCities_count' => isset($stateCities) ? count($stateCities) : 'N/A',
            'postCities_count' => count($allPostCities),
        ]);
        return null;
    }

    /**
     * پیدا کردن کد استان COD24 بر اساس نام فارسی
     */
    protected function findStateCode(string $provincePersian): ?int
    {
        $normalizedSearch = $this->normalizePersian($provincePersian);
        $states = $this->getStates();
        foreach ($states as $st) {
            $sName = $st['stateNameFa'] ?? $st['name'] ?? $st['stateName'] ?? $st['title'] ?? '';
            $normalizedName = $this->normalizePersian($sName);
            if (!empty($normalizedName) && ($normalizedName === $normalizedSearch || str_contains($normalizedName, $normalizedSearch) || str_contains($normalizedSearch, $normalizedName))) {
                $code = (int) ($st['stateCode'] ?? $st['code'] ?? $st['id'] ?? 0);
                if ($code > 0) return $code;
            }
        }
        return null;
    }

    /**
     * جستجوی شهر در لیست — با نرمال‌سازی حروف عربی/فارسی
     */
    protected function matchCityInList(string $cityName, array $cities): ?int
    {
        $normalizedSearch = $this->normalizePersian($cityName);

        // مرحله ۱: exact match (بعد از normalize)
        foreach ($cities as $city) {
            $cName = $city['cityNameFa'] ?? $city['name'] ?? $city['cityName'] ?? $city['title'] ?? '';
            $normalizedName = $this->normalizePersian($cName);
            if ($normalizedName === $normalizedSearch) {
                $code = (int) ($city['cityCode'] ?? $city['code'] ?? $city['id'] ?? 0);
                if ($code > 0) return $code;
            }
        }
        // مرحله ۲: substring match (فقط اگه exact نداشتیم)
        foreach ($cities as $city) {
            $cName = $city['cityNameFa'] ?? $city['name'] ?? $city['cityName'] ?? $city['title'] ?? '';
            $normalizedName = $this->normalizePersian($cName);
            if (!empty($normalizedName) && (str_contains($normalizedName, $normalizedSearch) || str_contains($normalizedSearch, $normalizedName))) {
                $code = (int) ($city['cityCode'] ?? $city['code'] ?? $city['id'] ?? 0);
                if ($code > 0) return $code;
            }
        }
        return null;
    }

    /**
     * استخراج بارکد از پاسخ API
     */
    protected function extractBarcode(array $body): ?string
    {
        $candidates = [
            $body['serial'] ?? null,
            $body['barcode'] ?? null,
            $body['cod24Barcode'] ?? null,
            $body['data']['serial'] ?? null,
            $body['data']['barcode'] ?? null,
            $body['data']['cod24Barcode'] ?? null,
            $body['data'][0]['barcode'] ?? null,
            $body['result']['barcode'] ?? null,
            $body['result']['cod24Barcode'] ?? null,
            $body['trackingCode'] ?? null,
            $body['data']['trackingCode'] ?? null,
        ];

        foreach ($candidates as $val) {
            if (!empty($val)) return (string) $val;
        }

        return null;
    }

    /**
     * تبدیل سایز کارتن انتخابی به idCartonType برای COD24
     * نام کارتن (مثلاً "4") = شماره نوع کارتن در COD24
     */
    protected function resolveCartonType(?int $boxSizeId): int
    {
        if (empty($boxSizeId)) {
            return 0;
        }

        $boxSize = \Modules\Warehouse\Models\WarehouseBoxSize::find($boxSizeId);
        if (!$boxSize) {
            return 0;
        }

        // نام کارتن عدد سایز هست (مثلاً "4")
        return (int) $boxSize->name;
    }

    /**
     * ساخت آرایه محصولات برای API
     */
    protected function buildProducts(array $data): array
    {
        $products = [];
        $items = $data['items'] ?? [];

        if (empty($items)) {
            $products[] = [
                'productUserCode' => '',
                'productName'     => $data['description'] ?? 'کالا',
                'weight'          => (int) ($data['weight'] ?? 500),
                'count'           => 1,
                'totalOffAmount'  => 0,
                'totalPayAmount'  => (int) ($data['value'] ?? 0),
                'finalPayAmount'  => (int) ($data['value'] ?? 0),
            ];
        } else {
            foreach ($items as $item) {
                $products[] = [
                    'productUserCode' => (string) ($item['sku'] ?? ''),
                    'productName'     => $item['name'] ?? 'کالا',
                    'weight'          => (int) ($item['weight'] ?? 0),
                    'count'           => (int) ($item['quantity'] ?? 1),
                    'totalOffAmount'  => 0,
                    'totalPayAmount'  => (int) ($item['price'] ?? 0),
                    'finalPayAmount'  => (int) ($item['price'] ?? 0),
                ];
            }
        }

        return $products;
    }

    /**
     * استخراج آرایه از پاسخ API
     */
    protected function unwrapList(array $raw): array
    {
        if (isset($raw['data']) && is_array($raw['data'])) return $raw['data'];
        if (isset($raw['result']) && is_array($raw['result'])) return $raw['result'];
        if (isset($raw['results']) && is_array($raw['results'])) return $raw['results'];
        if (isset($raw['items']) && is_array($raw['items'])) return $raw['items'];
        if (!empty($raw) && array_keys($raw) === range(0, count($raw) - 1)) return $raw;
        return [];
    }

    protected function splitName(string $fullName): array
    {
        $fullName = trim($fullName);
        if (empty($fullName)) {
            return ['', ''];
        }
        $parts = preg_split('/\s+/', $fullName, 2);
        $firstName = $parts[0] ?? '';
        $lastName  = $parts[1] ?? $firstName;
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

    /**
     * نرمال‌سازی حروف عربی/فارسی — حل مشکل ي vs ی و ك vs ک
     * COD24 ممکنه ي عربی (U+064A) بفرسته ولی WC ی فارسی (U+06CC) داره
     */
    protected function normalizePersian(string $text): string
    {
        $text = str_replace(
            ['ي', 'ك', 'ە', "\xC2\xA0", '  '],
            ['ی', 'ک', 'ه', ' ', ' '],
            trim($text)
        );
        // حذف نیم‌فاصله (zero-width non-joiner) و تبدیل به بدون فاصله
        $text = str_replace("\xE2\x80\x8C", '', $text);
        // حذف فاصله‌های اضافی
        $text = preg_replace('/\s+/', ' ', trim($text));
        return $text;
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
