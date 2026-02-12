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

    // نگاشت کد استان ووکامرس (سایت گنجه مارکت) به نام فارسی
    protected static array $wcStateMap = [
        'ABZ' => 'البرز',
        'ADL' => 'اردبیل',
        'EAZ' => 'آذربایجان شرقی',
        'WAZ' => 'آذربایجان غربی',
        'BHR' => 'بوشهر',
        'CHB' => 'چهارمحال و بختیاری',
        'FRS' => 'فارس',
        'GIL' => 'گیلان',
        'GLS' => 'گلستان',
        'HDN' => 'همدان',
        'HRZ' => 'هرمزگان',
        'ILM' => 'ایلام',
        'ESF' => 'اصفهان',
        'KRN' => 'کرمان',
        'KRH' => 'کرمانشاه',
        'NKH' => 'خراسان شمالی',
        'RKH' => 'خراسان رضوی',
        'SKH' => 'خراسان جنوبی',
        'KHZ' => 'خوزستان',
        'KBD' => 'کهگیلویه و بویراحمد',
        'KRD' => 'کردستان',
        'LRS' => 'لرستان',
        'MKZ' => 'مرکزی',
        'MZN' => 'مازندران',
        'GZN' => 'قزوین',
        'QHM' => 'قم',
        'SMN' => 'سمنان',
        'SBN' => 'سیستان و بلوچستان',
        'THR' => 'تهران',
        'YZD' => 'یزد',
        'ZJN' => 'زنجان',
    ];

    public static function getWcStateMap(): array
    {
        return self::$wcStateMap;
    }

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
    // استان‌ها و شهرها - POST /api/v2/public/state/tree/
    // ==========================================

    public function getStateTree(): array
    {
        $cached = Cache::get('tapin_state_tree');
        if (!empty($cached)) return $cached;

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('public/state/tree/'), []);

            $json = $response->json() ?? [];
            $apiStatus = $json['returns']['status'] ?? 0;

            if ($apiStatus === 200) {
                $entries = $json['entries'] ?? [];
                if (!empty($entries) && is_array($entries)) {
                    Cache::put('tapin_state_tree', $entries, 86400);
                    Log::info('Tapin state tree loaded', ['count' => count($entries)]);
                    return $entries;
                }
            }

            Log::warning('Tapin state tree failed', ['status' => $apiStatus, 'message' => $json['returns']['message'] ?? '']);
            return [];
        } catch (\Exception $e) {
            Log::error('Tapin getStateTree error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * نرمال‌سازی متن فارسی برای مقایسه
     * ی/ي → ی، ک/ك → ک، ة → ه، حذف فاصله اضافی و \r\n
     */
    public static function normalizePersian(string $text): string
    {
        $text = trim($text);
        $text = str_replace(["\r\n", "\r", "\n"], '', $text);
        // یکسان‌سازی کاراکترهای عربی/فارسی
        $text = str_replace(['ي', 'ى'], 'ی', $text);
        $text = str_replace('ك', 'ک', $text);
        $text = str_replace('ة', 'ه', $text);
        $text = str_replace('‌', ' ', $text); // نیم‌فاصله به فاصله
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * پیدا کردن province_code از نام استان یا کد ووکامرس
     */
    public function findProvinceCode(?string $stateNameOrCode): ?int
    {
        if (!$stateNameOrCode) return null;

        // اگه کد ووکامرس هست، به نام فارسی تبدیل کن
        $persianName = self::$wcStateMap[strtoupper($stateNameOrCode)] ?? $stateNameOrCode;
        $normalizedName = self::normalizePersian($persianName);

        $stateTree = $this->getStateTree();

        // مرحله 1: تطابق دقیق (با نرمال‌سازی)
        foreach ($stateTree as $province) {
            $title = self::normalizePersian($province['title'] ?? '');
            $code = $province['code'] ?? null;
            if ($code !== null && $title === $normalizedName) {
                return (int) $code;
            }
        }

        // مرحله 2: تطابق جزئی (یکی شامل دیگری باشه)
        foreach ($stateTree as $province) {
            $title = self::normalizePersian($province['title'] ?? '');
            $code = $province['code'] ?? null;
            if ($code !== null && (str_contains($title, $normalizedName) || str_contains($normalizedName, $title))) {
                return (int) $code;
            }
        }

        Log::warning('Tapin province not found', ['input' => $stateNameOrCode, 'persian' => $persianName, 'normalized' => $normalizedName]);
        return null;
    }

    /**
     * پیدا کردن city_code از نام شهر و کد استان
     */
    public function findCityCode(?string $cityName, $provinceCode): ?int
    {
        if (!$provinceCode) return null;

        $stateTree = $this->getStateTree();

        foreach ($stateTree as $province) {
            if (($province['code'] ?? null) == $provinceCode) {
                $cities = $province['cities'] ?? [];

                // اگه نام شهر داریم جستجو کن
                if ($cityName) {
                    $normalizedCity = self::normalizePersian($cityName);

                    // تطابق دقیق
                    foreach ($cities as $city) {
                        $title = self::normalizePersian($city['title'] ?? '');
                        if ($title === $normalizedCity && ($city['code'] ?? null) !== null) {
                            return (int) $city['code'];
                        }
                    }

                    // تطابق جزئی
                    foreach ($cities as $city) {
                        $title = self::normalizePersian($city['title'] ?? '');
                        if (($city['code'] ?? null) !== null && (str_contains($title, $normalizedCity) || str_contains($normalizedCity, $title))) {
                            return (int) $city['code'];
                        }
                    }

                    Log::info('Tapin city not found, using first city', ['city' => $cityName, 'province_code' => $provinceCode]);
                }

                // اگه شهر پیدا نشد، اولین شهر (مرکز استان)
                if (!empty($cities)) {
                    return (int) ($cities[0]['code'] ?? 1);
                }
                break;
            }
        }

        return null;
    }

    // ==========================================
    // اطلاعات فروشگاه
    // ==========================================

    public function getShopDetails(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات تاپین کامل نیست'];
        }

        try {
            // دریافت اعتبار (تنها endpoint مستند برای اطلاعات فروشگاه)
            $creditResult = $this->getShopCredit();

            return [
                'success' => $creditResult['success'] ?? false,
                'data' => [
                    'shop_id' => $this->shopId,
                    'credit' => $creditResult['data']['credit'] ?? null,
                    'credit_formatted' => $creditResult['data']['formatted'] ?? 'نامشخص',
                ],
                'message' => $creditResult['message'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::error('Tapin getShopDetails error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ==========================================
    // اعتبار فروشگاه
    // ==========================================

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

            if (($data['returns']['status'] ?? 0) === 200) {
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
    // لیست بسته‌های پستی
    // POST /api/v2/public/order/post/packing-box/
    // ==========================================

    public function getPackingBoxes(): array
    {
        $cached = Cache::get('tapin_packing_boxes');
        if (!empty($cached)) return $cached;

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('public/order/post/packing-box/'), [
                    'shop_id' => $this->shopId,
                ]);

            $json = $response->json() ?? [];
            if (($json['returns']['status'] ?? 0) === 200) {
                $list = $json['entries']['list'] ?? [];
                if (!empty($list)) {
                    Cache::put('tapin_packing_boxes', $list, 86400);
                    return $list;
                }
            }
            return [];
        } catch (\Exception $e) {
            Log::error('Tapin getPackingBoxes error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * انتخاب بهترین box_id بر اساس ابعاد
     */
    public function findBestBoxId(?float $length, ?float $width, ?float $height): int
    {
        $boxes = $this->getPackingBoxes();
        if (empty($boxes)) return 10; // fallback

        // اگه ابعاد نداریم، کوچکترین رو بده
        if (!$length || !$width || !$height) {
            return (int) ($boxes[0]['pk'] ?? 10);
        }

        // sort dimensions
        $dims = [$length, $width, $height];
        rsort($dims);

        foreach ($boxes as $box) {
            $bDims = [(float)$box['length'], (float)$box['width'], (float)$box['height']];
            rsort($bDims);

            if ($bDims[0] >= $dims[0] && $bDims[1] >= $dims[1] && $bDims[2] >= $dims[2]) {
                return (int) $box['pk'];
            }
        }

        // بزرگترین باکس
        return (int) (end($boxes)['pk'] ?? 10);
    }

    // ==========================================
    // استعلام قیمت
    // POST /api/v2/public/order/post/check-price/
    // ==========================================

    public function checkPrice(array $orderData): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات تاپین کامل نیست'];
        }

        try {
            $payload = $this->buildOrderPayload($orderData);
            // check-price همون فیلدهای register رو می‌خواد
            unset($payload['register_type'], $payload['manual_id'], $payload['has_insurance'], $payload['content_type']);

            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('public/order/post/check-price/'), $payload);

            $json = $response->json() ?? [];
            $apiStatus = $json['returns']['status'] ?? 0;

            if ($apiStatus === 200) {
                $entries = $json['entries'] ?? [];
                return [
                    'success' => true,
                    'data' => $entries,
                    'total_price' => $entries['total_price'] ?? 0,
                    'send_price' => $entries['send_price'] ?? 0,
                ];
            }

            return ['success' => false, 'message' => $json['returns']['message'] ?? $response->body()];
        } catch (\Exception $e) {
            Log::error('Tapin checkPrice error', ['error' => $e->getMessage()]);
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
            $entries = $result['entries'] ?? [];
            Log::info('Tapin createOrder response', [
                'http_status' => $response->status(),
                'api_status' => $result['returns']['status'] ?? 0,
                'api_message' => $result['returns']['message'] ?? '',
                'entries_keys' => is_array($entries) ? array_keys($entries) : 'not-array',
                'barcode' => $entries['barcode'] ?? 'EMPTY',
                'order_id' => $entries['order_id'] ?? 'EMPTY',
                'status' => $entries['status'] ?? 'EMPTY',
                'register_type' => $payload['register_type'] ?? 'UNKNOWN',
                'full_entries' => $entries,
            ]);

            $apiStatus = $result['returns']['status'] ?? 0;

            // اگه order_type خطا داد، با مقدار 1 (پیشتاز) دوباره تلاش کن
            if ($apiStatus === 300 && isset($result['entries']['order_type'])) {
                $usedType = $payload['order_type'];
                Log::warning('Tapin order_type invalid, retrying with 1', ['tried' => $usedType]);
                $payload['order_type'] = 1;
                $response = Http::timeout(30)
                    ->withHeaders($this->getHeaders())
                    ->post($this->endpoint('public/order/post/register/'), $payload);
                $result = $response->json() ?? [];
                $apiStatus = $result['returns']['status'] ?? 0;
                Log::info('Tapin createOrder retry response', ['status' => $response->status(), 'body' => $result]);
            }

            // 200 = موفق، 770 = تکراری
            if ($apiStatus === 200 || $apiStatus === 770) {
                $entries = $result['entries'] ?? [];
                return [
                    'success' => true,
                    'message' => $result['returns']['message'] ?? 'سفارش ثبت شد',
                    'duplicate' => $apiStatus === 770,
                    'data' => [
                        'order_id' => $entries['order_id'] ?? null,
                        'barcode' => $entries['barcode'] ?? null,
                        'tracking_code' => $entries['barcode'] ?? null,
                        'status' => $entries['status'] ?? null,
                    ],
                ];
            }

            $errorMessage = $result['returns']['message'] ?? $response->body();
            $fieldErrors = $result['entries'] ?? [];
            if (is_array($fieldErrors) && !empty($fieldErrors)) {
                $errorDetails = [];
                foreach ($fieldErrors as $field => $errors) {
                    if (is_array($errors)) {
                        $flat = [];
                        array_walk_recursive($errors, function ($v) use (&$flat) { $flat[] = $v; });
                        $errorDetails[] = $field . ': ' . implode(', ', $flat);
                    } elseif (is_string($errors)) {
                        $errorDetails[] = $field . ': ' . $errors;
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

    public function createShipment(array $data): array
    {
        return $this->createOrder($data);
    }

    // ==========================================
    // تغییر وضعیت سفارش (+ دریافت بارکد)
    // POST /api/v2/public/order/post/change-status/
    // ==========================================

    public function changeOrderStatus(int $orderId, int $status = 1): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات تاپین کامل نیست'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('public/order/post/change-status/'), [
                    'shop_id' => $this->shopId,
                    'order_id' => $orderId,
                    'status' => $status,
                ]);

            $json = $response->json() ?? [];
            $apiStatus = $json['returns']['status'] ?? 0;

            Log::info('Tapin changeOrderStatus response', [
                'order_id' => $orderId,
                'requested_status' => $status,
                'api_status' => $apiStatus,
                'entries' => $json['entries'] ?? [],
            ]);

            if ($apiStatus === 200) {
                $entries = $json['entries'] ?? [];
                return [
                    'success' => true,
                    'message' => $json['returns']['message'] ?? 'وضعیت تغییر کرد',
                    'data' => [
                        'barcode' => $entries['barcode'] ?? null,
                        'order_id' => $entries['order_id'] ?? $orderId,
                        'status' => $entries['status'] ?? null,
                    ],
                ];
            }

            return ['success' => false, 'message' => $json['returns']['message'] ?? $response->body()];
        } catch (\Exception $e) {
            Log::error('Tapin changeOrderStatus error', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ==========================================
    // لیست سفارشات
    // POST /api/v2/public/order/post/list/
    // ==========================================

    public function getOrdersList(int $page = 1, int $count = 10, array $filters = []): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات تاپین کامل نیست'];
        }

        try {
            $payload = array_merge([
                'shop_id' => $this->shopId,
                'count' => $count,
                'page' => $page,
            ], $filters);

            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('public/order/post/list/'), $payload);

            $json = $response->json() ?? [];

            if (($json['returns']['status'] ?? 0) === 200) {
                return ['success' => true, 'data' => $json['entries'] ?? []];
            }

            return ['success' => false, 'message' => $json['returns']['message'] ?? $response->body()];
        } catch (\Exception $e) {
            Log::error('Tapin getOrdersList error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ==========================================
    // وضعیت و بارکد سفارشات (بالک)
    // POST /api/v2/public/order/post/get-status/bulk/
    // ==========================================

    public function getOrderStatusBulk(array $orderUuids): array
    {
        if (!$this->isConfigured() || empty($orderUuids)) {
            return ['success' => false, 'message' => 'تنظیمات یا لیست سفارش خالی'];
        }

        try {
            $orders = array_map(fn($id) => ['id' => $id], $orderUuids);

            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('public/order/post/get-status/bulk/'), [
                    'shop_id' => $this->shopId,
                    'orders' => $orders,
                ]);

            $json = $response->json() ?? [];

            if (($json['returns']['status'] ?? 0) === 200) {
                return ['success' => true, 'data' => $json['entries']['list'] ?? []];
            }

            return ['success' => false, 'message' => $json['returns']['message'] ?? $response->body()];
        } catch (\Exception $e) {
            Log::error('Tapin getOrderStatusBulk error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ==========================================
    // دریافت جزئیات سفارش (بارکد)
    // POST /api/v2/public/order/post/list/ با فیلتر order_id
    // ==========================================

    public function getOrderDetails(int $orderId): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات تاپین کامل نیست'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders($this->getHeaders())
                ->post($this->endpoint('public/order/post/list/'), [
                    'shop_id' => $this->shopId,
                    'order_id' => $orderId,
                    'count' => 1,
                    'page' => 1,
                ]);

            $json = $response->json() ?? [];
            $apiStatus = $json['returns']['status'] ?? 0;

            Log::info('Tapin getOrderDetails response', [
                'order_id' => $orderId,
                'api_status' => $apiStatus,
                'entries' => $json['entries'] ?? [],
            ]);

            if ($apiStatus === 200) {
                $entries = $json['entries'] ?? [];
                // ممکنه entries مستقیم اطلاعات باشه یا لیست باشه
                $orderData = null;
                if (isset($entries['list']) && is_array($entries['list'])) {
                    $orderData = $entries['list'][0] ?? null;
                } elseif (isset($entries['barcode'])) {
                    $orderData = $entries;
                }

                if ($orderData) {
                    return [
                        'success' => true,
                        'data' => [
                            'barcode' => $orderData['barcode'] ?? null,
                            'order_id' => $orderData['order_id'] ?? $orderData['id'] ?? $orderId,
                            'status' => $orderData['status'] ?? null,
                            'post_barcode' => $orderData['post_barcode'] ?? $orderData['easypost_barcode'] ?? null,
                        ],
                    ];
                }
            }

            return ['success' => false, 'message' => $json['returns']['message'] ?? 'سفارش یافت نشد'];
        } catch (\Exception $e) {
            Log::error('Tapin getOrderDetails error', ['order_id' => $orderId, 'error' => $e->getMessage()]);
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
            // اگه شناسه عددی تاپین هست، از لیست سفارشات بگیر
            if (is_numeric($trackingCode)) {
                return $this->getOrdersList(1, 1, ['order_id' => (int) $trackingCode]);
            }

            // رهگیری با بارکد
            return $this->getOrdersList(1, 1, ['barcode' => $trackingCode]);
        } catch (\Exception $e) {
            Log::error('Tapin trackShipment error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ==========================================
    // ساخت payload ثبت سفارش
    // ==========================================

    protected function buildOrderPayload(array $orderData): array
    {
        $weightGrams = (int) ($orderData['weight'] ?? 500);
        $weightGrams = max($weightGrams, 50); // حداقل 50 گرم (الزام تاپین)

        // پیدا کردن کد استان و شهر
        // اولویت: کدهای مستقیم تاپین (اگه تو سفارش ذخیره شده) → سپس مپ از WC
        $provinceCode = $orderData['tapin_province_code'] ?? null;
        $cityCode = $orderData['tapin_city_code'] ?? null;

        if (!$provinceCode) {
            $state = $orderData['recipient_province'] ?? $orderData['recipient_state'] ?? '';
            $city = $orderData['recipient_city_name'] ?? $orderData['recipient_city'] ?? '';

            $provinceCode = $this->findProvinceCode($state);
            $cityCode = $this->findCityCode($city, $provinceCode);
        }

        if (!$provinceCode) {
            Log::error('Tapin province code NOT FOUND - cannot register', [
                'state' => $state ?? '',
                'city' => $city ?? '',
            ]);
        }

        Log::info('Tapin location lookup', [
            'state_input' => $state ?? 'direct',
            'city_input' => $city ?? 'direct',
            'province_code' => $provinceCode,
            'city_code' => $cityCode,
        ]);

        // ساخت لیست محصولات
        $products = [];
        $productCount = count($orderData['products'] ?? []) ?: 1;
        // ارزش ثابت کل: ۱۰۰,۰۰۰ ریال تقسیم بر تعداد محصولات (حداقل تاپین ۵۰,۰۰۰ ریال)
        $pricePerProduct = (int) ceil(100000 / $productCount);

        if (!empty($orderData['products'])) {
            foreach ($orderData['products'] as $p) {
                $productWeight = (int) ($p['weight'] ?? $weightGrams);
                $products[] = [
                    'title' => mb_substr($p['title'] ?? $p['name'] ?? 'کالا', 0, 50),
                    'count' => (int) ($p['count'] ?? $p['quantity'] ?? 1),
                    'price' => $pricePerProduct,
                    'weight' => max($productWeight, 50), // حداقل 50g (الزام تاپین)
                    'discount' => (int) ($p['discount'] ?? 0),
                    'product_id' => $p['product_id'] ?? null,
                ];
            }
        } else {
            $products[] = [
                'title' => mb_substr($orderData['product_name'] ?? 'کالا', 0, 50),
                'count' => 1,
                'price' => 100000, // ۱۰۰,۰۰۰ ریال
                'weight' => max($weightGrams, 50),
                'discount' => 0,
                'product_id' => null,
            ];
        }

        $orderNumber = $orderData['external_order_id'] ?? '';
        $manualId = preg_replace('/\D/', '', $orderNumber) ?: (string) time();

        // order_type از تنظیمات (پیش‌فرض 1 = پیشتاز، 2 = عادی)
        $orderType = (int) WarehouseSetting::get('tapin_order_type', 1);

        // match ابعاد جعبه سفارش با بسته‌های تاپین
        $boxLength = $orderData['box_length'] ?? null;
        $boxWidth = $orderData['box_width'] ?? null;
        $boxHeight = $orderData['box_height'] ?? null;

        if ($boxLength && $boxWidth && $boxHeight) {
            $boxId = $this->findBestBoxId((float) $boxLength, (float) $boxWidth, (float) $boxHeight);
            Log::info('Tapin box matched', [
                'order_box' => "{$boxLength}x{$boxWidth}x{$boxHeight}",
                'tapin_box_id' => $boxId,
            ]);
        } else {
            $boxId = (int) ($orderData['box_id'] ?? WarehouseSetting::get('tapin_box_id', 10));
        }

        return [
            'register_type' => (int) WarehouseSetting::get('tapin_register_type', 2),
            'shop_id' => $this->shopId,
            'first_name' => $this->getFirstName($orderData['recipient_name'] ?? 'مشتری'),
            'last_name' => $this->getLastName($orderData['recipient_name'] ?? 'مشتری'),
            'mobile' => $this->formatMobile($orderData['recipient_mobile'] ?? ''),
            'phone' => null,
            'email' => null,
            'address' => $orderData['recipient_address'] ?? 'آدرس نامشخص',
            'postal_code' => $orderData['recipient_postal_code'] ?? '0000000000',
            'province_code' => $provinceCode,
            'city_code' => $cityCode ?: ($provinceCode ? 1 : null),
            'description' => null,
            'employee_code' => -1,
            'pay_type' => (int) ($orderData['pay_type'] ?? 1),
            'order_type' => $orderType,
            'box_id' => $boxId,
            'kiosk_id' => null,
            'package_weight' => $weightGrams,
            'manual_id' => $manualId,
            'has_insurance' => false,
            'content_type' => 1,
            'products' => $products,
        ];
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
