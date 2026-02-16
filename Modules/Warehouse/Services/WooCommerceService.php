<?php

namespace Modules\Warehouse\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\WarehouseOrder;
use Modules\Warehouse\Models\WarehouseOrderItem;
use Modules\Warehouse\Models\WarehouseProduct;
use Modules\Warehouse\Models\WarehouseProductBundleItem;
use Modules\Warehouse\Models\WarehouseSetting;
use Modules\Warehouse\Models\WarehouseShippingRule;
use Modules\Warehouse\Models\WarehouseShippingType;

class WooCommerceService
{
    protected ?string $siteUrl;
    protected ?string $consumerKey;
    protected ?string $consumerSecret;

    public function __construct()
    {
        $this->siteUrl = rtrim(WarehouseSetting::get('wc_site_url', ''), '/');
        $this->consumerKey = WarehouseSetting::get('wc_consumer_key');
        $this->consumerSecret = WarehouseSetting::get('wc_consumer_secret');
    }

    public function isConfigured(): bool
    {
        return !empty($this->siteUrl) && !empty($this->consumerKey) && !empty($this->consumerSecret);
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات ووکامرس کامل نیست.'];
        }

        try {
            $response = Http::timeout(15)
                ->withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get($this->siteUrl . '/wp-json/wc/v3/system_status');

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message' => 'اتصال برقرار است.',
                    'store_name' => $data['environment']['site_url'] ?? $this->siteUrl,
                    'wc_version' => $data['environment']['version'] ?? 'نامشخص',
                ];
            }

            return ['success' => false, 'message' => 'خطا در اتصال: ' . $response->status()];
        } catch (\Exception $e) {
            Log::error('WooCommerce connection test failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطا در اتصال: ' . $e->getMessage()];
        }
    }

    public function fetchOrders(int $page = 1, int $perPage = 50, ?string $status = null): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات ووکامرس کامل نیست.', 'orders' => []];
        }

        try {
            $params = [
                'page' => $page,
                'per_page' => $perPage,
                'orderby' => 'date',
                'order' => 'desc',
            ];

            if (!empty($status)) {
                $params['status'] = $status;
            }

            $response = Http::timeout(60)
                ->withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get($this->siteUrl . '/wp-json/wc/v3/orders', $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'orders' => $response->json(),
                    'total' => (int) $response->header('X-WP-Total', 0),
                    'total_pages' => (int) $response->header('X-WP-TotalPages', 0),
                ];
            }

            return ['success' => false, 'message' => 'خطا: ' . $response->status(), 'orders' => []];
        } catch (\Exception $e) {
            Log::error('WooCommerce fetch orders failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطا: ' . $e->getMessage(), 'orders' => []];
        }
    }

    public function syncOrders(?string $wcStatus = 'processing'): array
    {
        // Check if required DB columns exist (migration must be run first)
        if (!\Schema::hasColumn('warehouse_orders', 'wc_order_id')) {
            return [
                'success' => false,
                'message' => 'ابتدا باید مایگریشن اجرا شود. دستور php artisan migrate را روی سرور اجرا کنید.',
            ];
        }

        $result = $this->fetchOrders(1, 100, $wcStatus);

        if (!$result['success']) {
            return $result;
        }

        if (empty($result['orders'])) {
            WarehouseSetting::set('wc_last_sync', now()->toDateTimeString());
            return [
                'success' => true,
                'message' => 'سفارشی با وضعیت انتخاب شده در ووکامرس یافت نشد.',
                'imported' => 0,
                'skipped' => 0,
                'failed' => 0,
            ];
        }

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $lastError = '';

        // دریافت وزن محصولات از جدول محلی (warehouse_products)
        $allLineItems = collect($result['orders'])->flatMap(fn($o) => $o['line_items'] ?? []);
        $productIds = $allLineItems->pluck('product_id')->filter()->unique()->toArray();
        $variationIds = $allLineItems->filter(fn($i) => !empty($i['variation_id']) && $i['variation_id'] > 0)
            ->pluck('variation_id')->unique()->toArray();
        $productWeights = WarehouseProduct::getWeightsMap($productIds, $variationIds);

        foreach ($result['orders'] as $wcOrder) {
            try {
                $wcOrderId = $wcOrder['id'];
                $orderNumber = 'WC-' . $wcOrderId;

                // Check if already synced (by wc_order_id OR order_number)
                $existingOrder = WarehouseOrder::where('wc_order_id', $wcOrderId)
                    ->orWhere('order_number', $orderNumber)
                    ->first();

                if ($existingOrder) {
                    // If old order exists without wc_order_id, update it with journey fields
                    if (!$existingOrder->wc_order_id) {
                        $shippingType = $this->detectShippingType($wcOrder);
                        $filteredItems = $this->filterBundleChildren($wcOrder['line_items'] ?? []);
                        $totalWeight = $this->calculateTotalWeight($filteredItems, $productWeights);

                        $existingOrder->update([
                            'wc_order_id' => $wcOrderId,
                            'wc_order_data' => $wcOrder,
                            'shipping_type' => $shippingType,
                            'barcode' => $existingOrder->barcode ?: WarehouseOrder::generateBarcode(),
                            'total_weight' => $totalWeight,
                            'customer_mobile' => $existingOrder->customer_mobile ?: ($wcOrder['billing']['phone'] ?? null),
                        ]);

                        // Create order items if not already created
                        if ($existingOrder->items()->count() === 0) {
                            $this->createOrderItems($existingOrder, $wcOrder['line_items'] ?? [], $productWeights);
                        }

                        // Set timer if not set
                        if (!$existingOrder->timer_deadline) {
                            $existingOrder->setTimerFromShippingType();
                        }

                        $imported++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                // سفارشات completed فقط حضوری‌ها رو وارد کن (پرداخت حضوری)
                $wcOrderStatus = $wcOrder['status'] ?? '';
                if ($wcOrderStatus === 'completed') {
                    $paymentTitle = $wcOrder['payment_method_title'] ?? '';
                    $paymentSlug = $wcOrder['payment_method'] ?? '';
                    $isInStore = str_contains($paymentTitle, 'حضوری') || str_contains($paymentSlug, 'cod');
                    if (!$isInStore) {
                        $skipped++;
                        continue;
                    }
                }

                $customerName = trim(($wcOrder['billing']['first_name'] ?? '') . ' ' . ($wcOrder['billing']['last_name'] ?? ''));
                if (empty($customerName)) {
                    $customerName = 'مشتری ووکامرس #' . $wcOrderId;
                }

                // Determine shipping type from WC shipping methods
                $shippingType = $this->detectShippingType($wcOrder);

                // فیلتر کردن زیرمجموعه‌های تکراری پکیج
                $filteredLineItems = $this->filterBundleChildren($wcOrder['line_items'] ?? []);

                // Calculate total weight from product weights
                $totalWeight = $this->calculateTotalWeight($filteredLineItems, $productWeights);

                // Build description
                $lineItems = collect($filteredLineItems)
                    ->map(fn($item) => ($item['name'] ?? '') . ' x' . ($item['quantity'] ?? 1))
                    ->implode("\n");

                // تشخیص منبع سفارش (باسلام / حضوری / سایت)
                $isBasalam = str_contains($wcOrderStatus, 'bslm');
                $isCompleted = $wcOrderStatus === 'completed';
                $orderSource = WarehouseOrder::SOURCE_WEBSITE;
                $orderNotes = 'مبلغ: ' . number_format((float)($wcOrder['total'] ?? 0)) . ' تومان';
                if ($isBasalam) {
                    $orderSource = WarehouseOrder::SOURCE_BASALAM;
                    $orderNotes = '🛒 سفارش باسلام | ' . $orderNotes;
                } elseif ($isCompleted) {
                    $orderSource = WarehouseOrder::SOURCE_IN_STORE;
                    $orderNotes = '🏪 سفارش حضوری | ' . $orderNotes;
                }

                // Payment method
                $paymentMethod = $wcOrder['payment_method_title'] ?? '';
                if ($paymentMethod) {
                    $orderNotes .= ' | پرداخت: ' . $paymentMethod;
                }

                // Create order
                $order = WarehouseOrder::create([
                    'order_number' => $orderNumber,
                    'wc_order_id' => $wcOrderId,
                    'wc_order_data' => $wcOrder,
                    'customer_name' => $customerName,
                    'customer_mobile' => $wcOrder['billing']['phone'] ?? null,
                    'description' => $lineItems ?: null,
                    'status' => WarehouseOrder::STATUS_PENDING,
                    'shipping_type' => $shippingType,
                    'order_source' => $orderSource,
                    'barcode' => WarehouseOrder::generateBarcode(),
                    'total_weight' => $totalWeight,
                    'created_by' => auth()->id(),
                    'notes' => $orderNotes,
                ]);

                // Set timer based on shipping type
                $order->setTimerFromShippingType();

                // Create order items with product weights (filtered = no bundle children)
                $this->createOrderItems($order, $filteredLineItems, $productWeights);

                $imported++;
            } catch (\Exception $e) {
                Log::error('WooCommerce order sync failed', [
                    'wc_order_id' => $wcOrder['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
                $lastError = $e->getMessage();
                $failed++;
            }
        }

        // ریکلکولیت وزن سفارشات موجود (باندل‌ها ممکنه بعد از سینک محصولات وزنشون تغییر کنه)
        $this->updateExistingOrderWeights();

        WarehouseSetting::set('wc_last_sync', now()->toDateTimeString());

        $totalFound = count($result['orders']);
        $message = "از {$totalFound} سفارش: وارد شده: {$imported} | تکراری: {$skipped} | خطا: {$failed}";
        if ($failed > 0 && $lastError) {
            $message .= "\nآخرین خطا: " . \Illuminate\Support\Str::limit($lastError, 150);
        }

        return [
            'success' => $imported > 0 || $failed === 0,
            'message' => $message,
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }



    /**
     * آیا سفارش مربوط به تهران است؟
     * سفارشات تهرانی نباید به تاپین بروند و باید حتماً پیک باشند.
     */
    public static function isTehranOrder(array $wcOrder): bool
    {
        $shipping = $wcOrder['shipping'] ?? [];
        $billing = $wcOrder['billing'] ?? [];

        $state = ($shipping['state'] ?? '') ?: ($billing['state'] ?? '');
        $city = ($shipping['city'] ?? '') ?: ($billing['city'] ?? '');

        $stateLower = mb_strtolower(trim($state));
        $cityLower = mb_strtolower(trim($city));

        // استان باید تهران باشد
        $isTehranProvince = $stateLower === 'تهران'
            || mb_strtoupper($state) === 'THR'
            || $stateLower === 'tehran';

        // شهر هم باید تهران باشد (نه ورامین، شهریار و غیره)
        $isTehranCity = str_contains($cityLower, 'تهران')
            || str_contains($cityLower, 'tehran');

        return $isTehranProvince && $isTehranCity;
    }

    /**
     * استخراج استان و شهر از سفارش ووکامرس
     */
    public static function extractProvinceCity(array $wcOrder): array
    {
        $shipping = $wcOrder['shipping'] ?? [];
        $billing = $wcOrder['billing'] ?? [];

        return [
            'province' => ($shipping['state'] ?? '') ?: ($billing['state'] ?? ''),
            'city' => ($shipping['city'] ?? '') ?: ($billing['city'] ?? ''),
        ];
    }

    /**
     * نگاشت کلید ارسال قالب گنجه به نوع ارسال داخلی
     *
     * قالب ووکامرس این مقادیر رو در meta key "_ganjeh_shipping_method" ذخیره می‌کنه:
     *   post       → پست (ارسال پستی)
     *   express    → urgent (پیک فوری تهران)
     *   collection → courier (ارسال عادی تهران / پیک ۵ روزه)
     *   pickup     → pickup (تحویل حضوری)
     */
    private const GANJEH_SHIPPING_MAP = [
        'post'       => 'post',
        'express'    => 'urgent',
        'collection' => 'courier',
        'pickup'     => 'pickup',
    ];

    public function detectShippingType(array $wcOrder): string
    {
        $metaData = $wcOrder['meta_data'] ?? [];
        $wcOrderId = $wcOrder['id'] ?? 'unknown';

        // ===== ۱. اولویت اول: _ganjeh_shipping_method از meta_data (دقیق‌ترین) =====
        $baseType = null;
        foreach ($metaData as $meta) {
            if (($meta['key'] ?? '') === '_ganjeh_shipping_method') {
                $ganjehMethod = strtolower(trim($meta['value'] ?? ''));
                $mapped = self::GANJEH_SHIPPING_MAP[$ganjehMethod] ?? null;
                if ($mapped) {
                    Log::info('WC shipping detected from _ganjeh_shipping_method', [
                        'wc_order_id' => $wcOrderId,
                        'ganjeh_method' => $ganjehMethod,
                        'mapped_to' => $mapped,
                    ]);
                    $baseType = $mapped;
                    break;
                }
            }
        }

        // ===== ۲. فالبک: shipping_lines (برای سفارشات قدیمی یا بدون meta) =====
        if (!$baseType) {
            $shippingLines = $wcOrder['shipping_lines'] ?? [];
            foreach ($shippingLines as $line) {
                $title = mb_strtolower($line['method_title'] ?? '');
                $mId = strtolower($line['method_id'] ?? '');

                if (str_contains($title, 'حضوری') || str_contains($mId, 'local_pickup') || str_contains($mId, 'pickup')) { $baseType = 'pickup'; break; }
                if (str_contains($title, 'فوری') || str_contains($title, 'urgent')) { $baseType = 'urgent'; break; }
                if (str_contains($title, 'اضطراری') || str_contains($title, 'emergency')) { $baseType = 'emergency'; break; }
                if (str_contains($title, 'پیک') || str_contains($title, 'courier') || str_contains($mId, 'local_delivery')) { $baseType = 'courier'; break; }
                if (str_contains($title, 'عادی') && str_contains($title, 'تهران')) { $baseType = 'courier'; break; }
                if (str_contains($title, 'پست') || str_contains($title, 'پیشتاز')) { $baseType = 'post'; break; }
                if (str_contains($mId, 'flat_rate') || str_contains($mId, 'free_shipping')) { $baseType = 'post'; break; }
            }
        }

        // ===== ۳. فالبک: fee_lines (قالب‌هایی که ارسال رو به عنوان fee اضافه می‌کنن) =====
        if (!$baseType) {
            $feeLines = $wcOrder['fee_lines'] ?? [];
            foreach ($feeLines as $fee) {
                $feeName = mb_strtolower($fee['name'] ?? '');
                if (str_contains($feeName, 'حضوری')) { $baseType = 'pickup'; break; }
                if (str_contains($feeName, 'فوری') || str_contains($feeName, 'express')) { $baseType = 'urgent'; break; }
                if (str_contains($feeName, 'پیک') || str_contains($feeName, 'courier')) { $baseType = 'courier'; break; }
                if (str_contains($feeName, 'پست') || str_contains($feeName, 'ارسال')) { $baseType = 'post'; break; }
            }
        }

        if (!$baseType) {
            Log::warning('WC shipping → post (no match)', ['wc_order_id' => $wcOrderId]);
            $baseType = 'post';
        }

        // ===== ۴. اعمال قوانین داینامیک override =====
        $addr = self::extractProvinceCity($wcOrder);
        $finalType = WarehouseShippingRule::applyRules($baseType, $addr['province'], $addr['city']);

        if ($finalType !== $baseType) {
            Log::info('WC shipping rule override', [
                'wc_order_id' => $wcOrderId,
                'base_type' => $baseType,
                'final_type' => $finalType,
                'province' => $addr['province'],
                'city' => $addr['city'],
            ]);
        }

        return $finalType;
    }

    /**
     * نگاشت وضعیت‌های پنل به ووکامرس
     */
    public const WC_STATUS_MAP = [
        'pending'     => 'processing',
        'supply_wait' => 'supply-wait',
        'packed'      => 'packed',
        'shipped'     => 'shipped',
        'delivered'   => 'completed',
        'returned'    => 'returned',
    ];

    /**
     * لیبل فارسی وضعیت‌های ووکامرس
     */
    public const WC_STATUS_LABELS = [
        'pending'     => 'در انتظار پرداخت',
        'processing'  => 'در حال پردازش',
        'on-hold'     => 'در انتظار',
        'completed'   => 'تکمیل شده',
        'cancelled'   => 'لغو شده',
        'refunded'    => 'مسترد شده',
        'failed'      => 'ناموفق',
        'supply-wait' => 'در انتظار تامین',
        'packed'      => 'در انتظار اسکن خروج',
        'shipped'     => 'ارسال شده',
        'returned'    => 'مرجوعی',
    ];

    /**
     * آیا سینک وضعیت به ووکامرس فعاله؟
     */
    public static function isStatusSyncEnabled(): bool
    {
        return WarehouseSetting::get('wc_status_sync_enabled', '1') === '1';
    }

    /**
     * آپدیت وضعیت سفارش در ووکامرس
     */
    public function updateOrderStatus(int $wcOrderId, string $wcStatus, ?string $note = null): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات ووکامرس کامل نیست.'];
        }

        try {
            $body = ['status' => $wcStatus];

            $response = Http::timeout(15)
                ->withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->put($this->siteUrl . '/wp-json/wc/v3/orders/' . $wcOrderId, $body);

            if ($response->successful()) {
                // اضافه کردن یادداشت به سفارش
                if ($note) {
                    $this->addOrderNote($wcOrderId, $note);
                }

                Log::info('WC order status synced', [
                    'wc_order_id' => $wcOrderId,
                    'wc_status' => $wcStatus,
                ]);

                return ['success' => true, 'message' => 'وضعیت ووکامرس آپدیت شد.'];
            }

            $errorBody = $response->json();
            $errorMessage = $errorBody['message'] ?? ('HTTP ' . $response->status());

            Log::warning('WC order status update failed', [
                'wc_order_id' => $wcOrderId,
                'wc_status' => $wcStatus,
                'http_status' => $response->status(),
                'response' => $errorMessage,
            ]);

            return ['success' => false, 'message' => 'خطا در آپدیت ووکامرس: ' . $errorMessage];
        } catch (\Exception $e) {
            Log::error('WC order status update exception', [
                'wc_order_id' => $wcOrderId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => 'خطا: ' . $e->getMessage()];
        }
    }

    /**
     * اضافه کردن یادداشت به سفارش ووکامرس
     */
    public function addOrderNote(int $wcOrderId, string $note, bool $customerNote = false): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->post($this->siteUrl . '/wp-json/wc/v3/orders/' . $wcOrderId . '/notes', [
                    'note' => $note,
                    'customer_note' => $customerNote,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('WC order note failed', [
                'wc_order_id' => $wcOrderId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * سینک وضعیت از پنل به ووکامرس
     */
    public function syncPanelStatusToWc(WarehouseOrder $order, string $panelStatus): array
    {
        if (!self::isStatusSyncEnabled()) {
            return ['success' => false, 'message' => 'سینک وضعیت غیرفعال است.'];
        }

        if (!$order->wc_order_id) {
            return ['success' => false, 'message' => 'سفارش ووکامرسی نیست.'];
        }

        // مپینگ سفارشی از تنظیمات (اگه وجود داره)
        $customMap = WarehouseSetting::get('wc_status_map');
        $map = $customMap ? (json_decode($customMap, true) ?: self::WC_STATUS_MAP) : self::WC_STATUS_MAP;

        $wcStatus = $map[$panelStatus] ?? null;
        if (!$wcStatus) {
            return ['success' => false, 'message' => "مپینگی برای وضعیت {$panelStatus} تعریف نشده."];
        }

        $statusLabels = WarehouseOrder::statusLabels();
        $panelLabel = $statusLabels[$panelStatus] ?? $panelStatus;
        $note = "وضعیت در پنل انبار: {$panelLabel}";

        // اگه کد رهگیری داره، اضافه کن
        if ($order->tracking_code && in_array($panelStatus, ['shipped', 'delivered'])) {
            $note .= " | کد رهگیری: {$order->tracking_code}";
        }
        if ($order->post_tracking_code && in_array($panelStatus, ['shipped', 'delivered'])) {
            $note .= " | کد رهگیری پست: {$order->post_tracking_code}";
        }

        return $this->updateOrderStatus($order->wc_order_id, $wcStatus, $note);
    }

    /**
     * گرفتن اطلاعات یک سفارش از API ووکامرس
     */
    public function fetchSingleOrder(int $wcOrderId): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::timeout(15)
                ->withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get($this->siteUrl . '/wp-json/wc/v3/orders/' . $wcOrderId);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch WC order', ['wc_order_id' => $wcOrderId, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * بازتشخیص نوع ارسال برای همه سفارشات
     * ابتدا data تازه از API ووکامرس می‌گیره، بعد تشخیص می‌زنه
     */
    public function redetectShippingTypes(): array
    {
        $orders = WarehouseOrder::whereNotNull('wc_order_id')->get();

        $updated = 0;
        $skipped = 0;
        $refreshed = 0;
        $details = [];

        foreach ($orders as $order) {
            $wcData = $order->wc_order_data;

            // اگه data نداره یا _ganjeh_shipping_method توش نیست، از API تازه بگیر
            $hasGanjehMeta = false;
            if (is_array($wcData)) {
                foreach (($wcData['meta_data'] ?? []) as $meta) {
                    if (($meta['key'] ?? '') === '_ganjeh_shipping_method') {
                        $hasGanjehMeta = true;
                        break;
                    }
                }
            }

            if (!$hasGanjehMeta && $order->wc_order_id) {
                $freshData = $this->fetchSingleOrder($order->wc_order_id);
                if ($freshData) {
                    $wcData = $freshData;
                    $order->wc_order_data = $freshData;
                    $order->save();
                    $refreshed++;
                }
            }

            if (!is_array($wcData)) {
                $skipped++;
                continue;
            }

            $oldType = $order->shipping_type;
            $newType = $this->detectShippingType($wcData);

            if ($oldType !== $newType) {
                $order->shipping_type = $newType;
                $order->save();

                // تایمر رو هم بر اساس نوع ارسال جدید ریست کن
                if (in_array($order->status, ['pending', 'supply_wait'])) {
                    $order->setTimerFromShippingType();
                }

                $updated++;
                $details[] = "#{$order->order_number}: {$oldType} → {$newType}";
            } else {
                $skipped++;
            }
        }

        Log::info('Redetect shipping types completed', [
            'updated' => $updated,
            'skipped' => $skipped,
            'refreshed' => $refreshed,
        ]);

        return [
            'success' => true,
            'updated' => $updated,
            'skipped' => $skipped,
            'refreshed' => $refreshed,
            'total' => $orders->count(),
            'details' => $details,
        ];
    }

    /**
     * تعیین وزن یک آیتم از جدول محلی محصولات
     */
    protected function getItemWeight(array $item, array $weightsMap): float
    {
        // اول variation رو چک کن
        if (!empty($item['variation_id']) && $item['variation_id'] > 0) {
            $varWeight = (float)($weightsMap[$item['variation_id']] ?? 0);
            if ($varWeight > 0) {
                return $varWeight;
            }
        }

        // بعد وزن محصول اصلی
        return (float)($weightsMap[$item['product_id'] ?? 0] ?? 0);
    }

    /**
     * فیلتر کردن آیتم‌هایی که زیرمجموعه پکیج هستن
     * ووکامرس هم پکیج رو میفرسته هم زیرمجموعه‌هاش رو جداگانه - زیرمجموعه‌ها تکراری هستن
     */
    protected function filterBundleChildren(array $lineItems): array
    {
        return array_values(array_filter($lineItems, function ($item) {
            foreach ($item['meta_data'] ?? [] as $meta) {
                if (($meta['key'] ?? '') === '_bundled_by') {
                    return false;
                }
            }
            return true;
        }));
    }

    protected function calculateTotalWeight(array $lineItems, array $weightsMap = []): float
    {
        $totalWeight = 0;
        foreach ($lineItems as $item) {
            $weight = !empty($weightsMap) ? $this->getItemWeight($item, $weightsMap) : 0;
            $quantity = (int)($item['quantity'] ?? 1);
            $totalWeight += $weight * $quantity;
        }
        return round($totalWeight, 2);
    }

    protected function createOrderItems(WarehouseOrder $order, array $lineItems, array $weightsMap = []): void
    {
        // دریافت ابعاد محصولات
        $productIds = collect($lineItems)->pluck('product_id')->filter()->unique()->toArray();
        $variationIds = collect($lineItems)->filter(fn($i) => !empty($i['variation_id']) && $i['variation_id'] > 0)
            ->pluck('variation_id')->unique()->toArray();
        $dimensionsMap = WarehouseProduct::getDimensionsMap($productIds, $variationIds);

        foreach ($lineItems as $item) {
            $prodId = $item['product_id'] ?? 0;
            $weight = !empty($weightsMap) ? $this->getItemWeight($item, $weightsMap) : 0;

            // برای باندل‌ها: همیشه وزن رو از بچه‌ها حساب کن (وزن ووکامرس ممکنه اشتباه باشه)
            if ($weight == 0 || $this->isBundleProduct($prodId)) {
                $bundleWeight = $this->calculateBundleWeightFromDb($prodId);
                if ($bundleWeight > 0) {
                    $weight = $bundleWeight;
                }
            }

            // ابعاد: اول variation بعد محصول اصلی
            $dims = ['length' => 0, 'width' => 0, 'height' => 0];
            $varId = $item['variation_id'] ?? 0;
            if ($varId > 0 && isset($dimensionsMap[$varId]) && ($dimensionsMap[$varId]['length'] ?? 0) > 0) {
                $dims = $dimensionsMap[$varId];
            } elseif ($prodId > 0 && isset($dimensionsMap[$prodId])) {
                $dims = $dimensionsMap[$prodId];
            }

            WarehouseOrderItem::create([
                'warehouse_order_id' => $order->id,
                'product_name' => $item['name'] ?? 'محصول',
                'product_sku' => $item['sku'] ?? null,
                'product_barcode' => $item['sku'] ?? null,
                'quantity' => (int)($item['quantity'] ?? 1),
                'weight' => $weight,
                'length' => (float)($dims['length'] ?? 0),
                'width' => (float)($dims['width'] ?? 0),
                'height' => (float)($dims['height'] ?? 0),
                'price' => (float)($item['total'] ?? 0),
                'wc_product_id' => $prodId ?: null,
            ]);
        }

        // ریکلکولیت وزن کل سفارش از آیتم‌های واقعی
        $order->load('items');
        $recalcWeight = $order->items->sum(fn($i) => WarehouseOrder::toGrams($i->weight) * $i->quantity);
        if ($recalcWeight > 0 && $recalcWeight != WarehouseOrder::toGrams($order->total_weight)) {
            $order->update(['total_weight' => $recalcWeight]);
        }
    }

    protected function isBundleProduct(int $productId): bool
    {
        if ($productId <= 0) return false;
        $product = WarehouseProduct::where('wc_product_id', $productId)->first();
        return $product && $product->is_bundle;
    }

    protected function calculateBundleWeightFromDb(int $productId): float
    {
        if ($productId <= 0) return 0;
        $product = WarehouseProduct::where('wc_product_id', $productId)->first();
        if (!$product || !$product->is_bundle) return 0;
        return $product->calculateBundleWeight();
    }

    /**
     * سینک محصولات از ووکامرس و ذخیره در جدول محلی
     */
    public function syncProducts(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات ووکامرس کامل نیست.'];
        }

        if (!\Schema::hasTable('warehouse_products')) {
            return ['success' => false, 'message' => 'ابتدا مایگریشن اجرا شود: php artisan migrate'];
        }

        $page = 1;
        $perPage = 100;
        $totalImported = 0;
        $totalUpdated = 0;
        $totalVariations = 0;
        $totalBundles = 0;
        $bundleTypes = ['bundle', 'yith_bundle', 'woosb', 'grouped'];

        try {
            do {
                $response = Http::timeout(60)
                    ->withBasicAuth($this->consumerKey, $this->consumerSecret)
                    ->get($this->siteUrl . '/wp-json/wc/v3/products', [
                        'page' => $page,
                        'per_page' => $perPage,
                        'status' => 'publish',
                    ]);

                if (!$response->successful()) {
                    break;
                }

                $products = $response->json();
                if (empty($products)) {
                    break;
                }

                foreach ($products as $product) {
                    // وزن خام از ووکامرس (ممکنه گرم یا کیلوگرم باشه - toGrams تشخیص میده)
                    $weightGrams = (float)($product['weight'] ?? 0);
                    $dims = $product['dimensions'] ?? [];
                    $productType = $product['type'] ?? 'simple';

                    // لاگ متادیتا برای تشخیص پلاگین باندل
                    $metaKeys = collect($product['meta_data'] ?? [])->pluck('key')->toArray();
                    $bundleRelatedKeys = array_filter($metaKeys, fn($k) => str_contains($k, 'bundle') || str_contains($k, 'woosb') || str_contains($k, 'yith_wcpb') || str_contains($k, 'ganjeh_bundle'));
                    if (!empty($bundleRelatedKeys)) {
                        Log::info('Product has bundle meta keys', [
                            'product_id' => $product['id'],
                            'name' => $product['name'] ?? '',
                            'type' => $productType,
                            'bundle_keys' => array_values($bundleRelatedKeys),
                        ]);
                    }

                    $result = WarehouseProduct::updateOrCreate(
                        ['wc_product_id' => $product['id']],
                        [
                            'name' => $product['name'] ?? '',
                            'sku' => $product['sku'] ?? null,
                            'weight' => $weightGrams,
                            'length' => (float)($dims['length'] ?? 0),
                            'width' => (float)($dims['width'] ?? 0),
                            'height' => (float)($dims['height'] ?? 0),
                            'price' => (float)($product['price'] ?? 0),
                            'type' => $productType,
                            'parent_id' => null,
                            'status' => $product['status'] ?? 'publish',
                        ]
                    );

                    if ($result->wasRecentlyCreated) {
                        $totalImported++;
                    } else {
                        $totalUpdated++;
                    }

                    // اگر محصول متغیر بود، variation ها رو هم بگیر
                    if ($productType === 'variable') {
                        $varCount = $this->syncProductVariations($product['id']);
                        $totalVariations += $varCount;
                    }

                    // چک باندل: هم از type و هم از meta_data (پلاگین‌هایی مثل YITH که type رو simple نگه میدارن)
                    $hasBundleMeta = $this->hasBundleMetaData($product);
                    if (in_array($productType, $bundleTypes) || $hasBundleMeta) {
                        $bundleCount = $this->syncBundleItems($product);
                        if ($bundleCount > 0) {
                            $totalBundles++;
                            // اگه type هنوز simple هست ولی باندل داره، تایپ رو آپدیت کن
                            if (!in_array($productType, $bundleTypes)) {
                                WarehouseProduct::where('wc_product_id', $product['id'])
                                    ->update(['type' => 'bundle']);
                                Log::info('Product type updated to bundle (detected from meta)', [
                                    'product_id' => $product['id'],
                                    'original_type' => $productType,
                                ]);
                            }
                        }
                    }
                }

                $totalPages = (int) $response->header('X-WP-TotalPages', 1);
                $page++;
            } while ($page <= $totalPages);

            // محاسبه وزن و ابعاد باندل‌ها از روی زیرمجموعه‌ها
            $this->updateBundleWeightsAndDimensions();

            WarehouseSetting::set('wc_products_last_sync', now()->toDateTimeString());

            // آپدیت وزن و ابعاد آیتم‌های سفارشات موجود
            $updatedWeights = $this->updateExistingOrderWeights();
            $updatedDimensions = $this->updateExistingOrderDimensions();

            $total = $totalImported + $totalUpdated;
            $message = "محصولات سینک شد: {$totalImported} جدید، {$totalUpdated} بروزرسانی، {$totalVariations} تنوع";
            if ($totalBundles > 0) {
                $message .= "، {$totalBundles} پکیج";
            }
            $message .= " | مجموع: {$total}";
            if ($updatedWeights > 0) {
                $message .= "\n{$updatedWeights} آیتم: وزن آپدیت شد.";
            }
            if ($updatedDimensions > 0) {
                $message .= "\n{$updatedDimensions} آیتم: ابعاد آپدیت شد.";
            }

            return [
                'success' => true,
                'message' => $message,
                'imported' => $totalImported,
                'updated' => $totalUpdated,
                'variations' => $totalVariations,
                'bundles' => $totalBundles,
                'updated_items' => $updatedWeights + $updatedDimensions,
            ];
        } catch (\Exception $e) {
            Log::error('WooCommerce product sync failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطا: ' . $e->getMessage()];
        }
    }

    /**
     * سینک variation های یک محصول متغیر
     */
    protected function syncProductVariations(int $productId): int
    {
        $count = 0;
        $page = 1;

        try {
            do {
                $response = Http::timeout(30)
                    ->withBasicAuth($this->consumerKey, $this->consumerSecret)
                    ->get($this->siteUrl . "/wp-json/wc/v3/products/{$productId}/variations", [
                        'page' => $page,
                        'per_page' => 100,
                    ]);

                if (!$response->successful()) {
                    break;
                }

                $variations = $response->json();
                if (empty($variations)) {
                    break;
                }

                // گرفتن اسم محصول پدر برای ساخت اسم کامل variation
                $parentProduct = WarehouseProduct::where('wc_product_id', $productId)->first();
                $parentName = $parentProduct ? $parentProduct->name : '';

                foreach ($variations as $variation) {
                    // وزن خام از ووکامرس (ممکنه گرم یا کیلوگرم باشه - toGrams تشخیص میده)
                    $weightGrams = (float)($variation['weight'] ?? 0);
                    $dims = $variation['dimensions'] ?? [];

                    // ساخت اسم کامل: اگه اسم variation خالی یا خیلی کوتاهه، اسم پدر + ویژگی‌ها رو بذار
                    $variationName = $variation['name'] ?? '';
                    if (empty($variationName) || (mb_strlen($variationName) < 20 && $parentName && !str_contains($variationName, $parentName))) {
                        $attributes = collect($variation['attributes'] ?? [])
                            ->pluck('option')
                            ->filter()
                            ->implode(' - ');
                        $variationName = $parentName . ($attributes ? ' - ' . $attributes : '');
                    }

                    WarehouseProduct::updateOrCreate(
                        ['wc_product_id' => $variation['id']],
                        [
                            'name' => $variationName ?: ('تنوع #' . $variation['id']),
                            'sku' => $variation['sku'] ?? null,
                            'weight' => $weightGrams,
                            'length' => (float)($dims['length'] ?? 0),
                            'width' => (float)($dims['width'] ?? 0),
                            'height' => (float)($dims['height'] ?? 0),
                            'price' => (float)($variation['price'] ?? 0),
                            'type' => 'variation',
                            'parent_id' => $productId,
                            'status' => $variation['status'] ?? 'publish',
                        ]
                    );
                    $count++;
                }

                $totalPages = (int) $response->header('X-WP-TotalPages', 1);
                $page++;
            } while ($page <= $totalPages);
        } catch (\Exception $e) {
            Log::warning('Failed to sync variations for product', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
        }

        return $count;
    }

    /**
     * بررسی اینکه آیا محصول meta_data مربوط به باندل داره
     */
    protected function hasBundleMetaData(array $product): bool
    {
        $metaData = collect($product['meta_data'] ?? []);
        $bundleKeys = [
            '_ganjeh_bundle_data',           // Ganjeh Market theme
            '_ganjeh_bundle_items',          // Ganjeh Market theme (fallback)
            '_yith_wcpb_bundle_data',        // YITH WooCommerce Product Bundles
            '_bundle_data',                   // WC Product Bundles (official)
            'bundle_data',
            '_woosb_ids',                     // WPC Product Bundles
            '_woosb_data',
        ];

        foreach ($bundleKeys as $key) {
            $meta = $metaData->firstWhere('key', $key);
            if ($meta && !empty($meta['value'])) {
                return true;
            }
        }

        // همچنین بررسی bundled_items در API response
        if (!empty($product['bundled_items'])) {
            return true;
        }

        return false;
    }

    /**
     * سینک آیتم‌های باندل/پکیج از ووکامرس
     * ابتدا از bundle_data یا bundled_items داخل محصول، سپس از API جداگانه
     */
    protected function syncBundleItems(array $product): int
    {
        $productId = $product['id'];
        $productType = $product['type'] ?? '';
        $count = 0;

        try {
            $childIds = [];

            // روش ۱: grouped products → children آیدی‌ها مستقیم داخل API هستن
            if ($productType === 'grouped' && !empty($product['grouped_products'])) {
                foreach ($product['grouped_products'] as $childId) {
                    $childIds[] = ['product_id' => (int)$childId, 'quantity' => 1, 'optional' => false, 'discount' => 0, 'priced_individually' => true];
                }
            }

            // روش ۲: WC Product Bundles → bundled_items در API response
            if (empty($childIds) && !empty($product['bundled_items'])) {
                foreach ($product['bundled_items'] as $bundledItem) {
                    $childIds[] = [
                        'product_id' => (int)($bundledItem['product_id'] ?? 0),
                        'quantity' => (int)($bundledItem['default_quantity'] ?? $bundledItem['quantity_default'] ?? 1),
                        'optional' => (bool)($bundledItem['optional'] ?? false),
                        'discount' => (float)($bundledItem['discount'] ?? 0),
                        'priced_individually' => (bool)($bundledItem['priced_individually'] ?? false),
                    ];
                }
            }

            // روش ۳: bundle_data در meta_data (پلاگین‌های مختلف)
            if (empty($childIds)) {
                $metaData = collect($product['meta_data'] ?? []);

                // لیست کلیدهای مختلف پلاگین‌ها
                $bundleKeys = [
                    '_ganjeh_bundle_data',           // Ganjeh Market theme (اصلی)
                    '_ganjeh_bundle_items',          // Ganjeh Market theme (فالبک - فقط آی‌دی‌ها)
                    '_yith_wcpb_bundle_data',        // YITH WooCommerce Product Bundles
                    '_bundle_data',                   // WC Product Bundles (official)
                    'bundle_data',
                    '_woosb_ids',                     // WPC Product Bundles
                    '_woosb_data',
                ];

                $bundleDataMeta = null;
                foreach ($bundleKeys as $key) {
                    $bundleDataMeta = $metaData->firstWhere('key', $key);
                    if ($bundleDataMeta) {
                        Log::info('Bundle meta found', ['product_id' => $productId, 'key' => $key]);
                        break;
                    }
                }

                if ($bundleDataMeta) {
                    $bundleData = $bundleDataMeta['value'] ?? [];
                    $metaKey = $bundleDataMeta['key'] ?? '';

                    // _woosb_ids format: "123/2,456/1" (productId/qty)
                    if (is_string($bundleData) && str_contains($bundleData, '/')) {
                        foreach (explode(',', $bundleData) as $pair) {
                            $parts = explode('/', trim($pair));
                            if (count($parts) >= 2) {
                                $childIds[] = ['product_id' => (int)$parts[0], 'quantity' => (int)$parts[1], 'optional' => false, 'discount' => 0, 'priced_individually' => false];
                            }
                        }
                    }
                    // _ganjeh_bundle_items format: فقط آرایه آی‌دی‌ها [123, 456]
                    elseif (is_array($bundleData) && isset($bundleData[0]) && !is_array($bundleData[0])) {
                        foreach ($bundleData as $childId) {
                            $childId = (int)$childId;
                            if ($childId > 0) {
                                $childIds[] = ['product_id' => $childId, 'quantity' => 1, 'optional' => false, 'discount' => 0, 'priced_individually' => false];
                            }
                        }
                    }
                    // Ganjeh / YITH / WC Bundles format: array of items
                    elseif (is_array($bundleData)) {
                        foreach ($bundleData as $itemKey => $item) {
                            if (!is_array($item)) continue;
                            // Ganjeh: 'id' field | YITH: 'product_id' field
                            $childProdId = (int)($item['id'] ?? $item['product_id'] ?? 0);
                            if ($childProdId > 0) {
                                $childIds[] = [
                                    'product_id' => $childProdId,
                                    'quantity' => (int)($item['default_qty'] ?? $item['bp_quantity'] ?? $item['default_quantity'] ?? $item['quantity_default'] ?? $item['qty'] ?? 1),
                                    'optional' => (bool)($item['optional'] ?? $item['bp_optional'] ?? false),
                                    'discount' => (float)($item['discount'] ?? $item['bp_discount'] ?? 0),
                                    'priced_individually' => (bool)($item['priced_individually'] ?? false),
                                ];
                            }
                        }
                    }
                }
            }

            // روش ۴: فالبک - API جداگانه باندل
            if (empty($childIds)) {
                try {
                    $bundleResponse = Http::timeout(15)
                        ->withBasicAuth($this->consumerKey, $this->consumerSecret)
                        ->get($this->siteUrl . "/wp-json/wc/v3/products/{$productId}/bundled-items");

                    if ($bundleResponse->successful()) {
                        $bundledItems = $bundleResponse->json();
                        if (is_array($bundledItems)) {
                            foreach ($bundledItems as $bundledItem) {
                                $childIds[] = [
                                    'product_id' => (int)($bundledItem['product_id'] ?? 0),
                                    'quantity' => (int)($bundledItem['default_quantity'] ?? $bundledItem['quantity_default'] ?? 1),
                                    'optional' => (bool)($bundledItem['optional'] ?? false),
                                    'discount' => (float)($bundledItem['discount'] ?? 0),
                                    'priced_individually' => (bool)($bundledItem['priced_individually'] ?? false),
                                ];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // API نداره، مشکلی نیست
                }
            }

            if (empty($childIds)) {
                Log::info('Bundle product has no components', ['product_id' => $productId, 'type' => $productType]);
                return 0;
            }

            // حذف آیتم‌های قدیمی و ذخیره جدید
            WarehouseProductBundleItem::where('bundle_product_id', $productId)->delete();

            foreach ($childIds as $child) {
                if (($child['product_id'] ?? 0) <= 0) continue;

                WarehouseProductBundleItem::create([
                    'bundle_product_id' => $productId,
                    'child_product_id' => $child['product_id'],
                    'default_quantity' => $child['quantity'],
                    'optional' => $child['optional'],
                    'discount' => $child['discount'],
                    'priced_individually' => $child['priced_individually'],
                ]);
                $count++;
            }

            Log::info('Bundle items synced', [
                'product_id' => $productId,
                'type' => $productType,
                'items_count' => $count,
                'child_ids' => collect($childIds)->pluck('product_id')->toArray(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to sync bundle items', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
        }

        return $count;
    }

    /**
     * محاسبه و آپدیت وزن و ابعاد محصولات باندل از روی زیرمجموعه‌ها
     */
    protected function updateBundleWeightsAndDimensions(): void
    {
        $bundleTypes = ['bundle', 'yith_bundle', 'woosb', 'grouped'];
        $bundles = WarehouseProduct::whereIn('type', $bundleTypes)->get();

        foreach ($bundles as $bundle) {
            $items = $bundle->bundleItems()->with('childProduct')->get();
            if ($items->isEmpty()) continue;

            $totalWeight = 0;
            $maxLength = 0;
            $maxWidth = 0;
            $totalHeight = 0;

            foreach ($items as $item) {
                if (!$item->childProduct || $item->optional) continue;

                $child = $item->childProduct;
                $qty = $item->default_quantity;

                $childWeight = (float) $child->weight;

                // اگه محصول فرزند variable هست و وزنش 0 هست، وزن رو از variation ها بگیر
                if ($childWeight == 0 && $child->type === 'variable') {
                    $firstVariation = WarehouseProduct::where('parent_id', $child->wc_product_id)
                        ->where('type', 'variation')
                        ->where('weight', '>', 0)
                        ->first();
                    if ($firstVariation) {
                        $childWeight = (float) $firstVariation->weight;
                    }
                }

                $totalWeight += $childWeight * $qty;

                // ابعاد: اگه فرزند variable هست و ابعادش 0 هست، از variation بگیر
                $childLength = (float) $child->length;
                $childWidth = (float) $child->width;
                $childHeight = (float) $child->height;

                if ($childLength == 0 && $child->type === 'variable') {
                    $firstVarDims = WarehouseProduct::where('parent_id', $child->wc_product_id)
                        ->where('type', 'variation')
                        ->where('length', '>', 0)
                        ->first();
                    if ($firstVarDims) {
                        $childLength = (float) $firstVarDims->length;
                        $childWidth = (float) $firstVarDims->width;
                        $childHeight = (float) $firstVarDims->height;
                    }
                }

                if ($childLength > 0 && $childWidth > 0 && $childHeight > 0) {
                    $maxLength = max($maxLength, $childLength);
                    $maxWidth = max($maxWidth, $childWidth);
                    $totalHeight += $childHeight * $qty;
                }
            }

            // همیشه وزن رو از زیرمجموعه‌ها آپدیت کن (وزن ووکامرس ممکنه غلط باشه)
            $updates = [];
            if ($totalWeight > 0) {
                $updates['weight'] = round($totalWeight, 2);
            }
            if ($maxLength > 0) {
                $updates['length'] = round($maxLength, 1);
                $updates['width'] = round($maxWidth, 1);
                $updates['height'] = round($totalHeight, 1);
            }

            if (!empty($updates)) {
                $bundle->update($updates);
                Log::info('Bundle weight/dims updated from components', [
                    'product_id' => $bundle->wc_product_id,
                    'name' => $bundle->name,
                    'weight' => $updates['weight'] ?? $bundle->weight,
                    'dims' => ($updates['length'] ?? $bundle->length) . 'x' . ($updates['width'] ?? $bundle->width) . 'x' . ($updates['height'] ?? $bundle->height),
                ]);
            }
        }
    }

    /**
     * آپدیت وزن آیتم‌های سفارشات موجود که وزنشون با warehouse_products فرق داره
     */
    public function updateExistingOrderWeights(): int
    {
        $updatedCount = 0;

        // همه آیتم‌هایی که product_id دارن
        $items = WarehouseOrderItem::whereNotNull('wc_product_id')->get();

        if ($items->isEmpty()) {
            return 0;
        }

        // جمع‌آوری product_id ها
        $productIds = $items->pluck('wc_product_id')->unique()->toArray();
        $weightsMap = WarehouseProduct::getWeightsMap($productIds);

        $affectedOrderIds = collect();

        foreach ($items as $item) {
            $newWeight = (float)($weightsMap[$item->wc_product_id] ?? 0);
            if ($newWeight > 0 && $newWeight != (float)$item->weight) {
                $item->update(['weight' => $newWeight]);
                $affectedOrderIds->push($item->warehouse_order_id);
                $updatedCount++;
            }
        }

        // آپدیت وزن کل سفارشاتی که آیتمشون تغییر کرده
        if ($updatedCount > 0) {
            $orderIds = $affectedOrderIds->unique();
            foreach ($orderIds as $orderId) {
                $order = WarehouseOrder::with('items')->find($orderId);
                if ($order) {
                    $totalWeight = $order->items->sum(fn($i) => WarehouseOrder::toGrams($i->weight) * $i->quantity);
                    $order->update(['total_weight' => $totalWeight]);
                }
            }
        }

        return $updatedCount;
    }

    /**
     * آپدیت ابعاد آیتم‌های سفارشات موجود که ابعادشون 0 هست
     */
    public function updateExistingOrderDimensions(): int
    {
        $updatedCount = 0;

        // آیتم‌هایی که ابعادشون 0 هست
        $items = WarehouseOrderItem::whereNotNull('wc_product_id')
            ->where(function ($q) {
                $q->where('length', 0)->orWhereNull('length')
                  ->orWhere('width', 0)->orWhereNull('width')
                  ->orWhere('height', 0)->orWhereNull('height');
            })
            ->get();

        if ($items->isEmpty()) {
            return 0;
        }

        // جمع‌آوری product_id ها
        $productIds = $items->pluck('wc_product_id')->unique()->toArray();
        $dimensionsMap = WarehouseProduct::getDimensionsMap($productIds);

        foreach ($items as $item) {
            $dims = $dimensionsMap[$item->wc_product_id] ?? null;
            if ($dims && ($dims['length'] ?? 0) > 0) {
                $item->update([
                    'length' => (float)($dims['length'] ?? 0),
                    'width' => (float)($dims['width'] ?? 0),
                    'height' => (float)($dims['height'] ?? 0),
                ]);
                $updatedCount++;
            }
        }

        return $updatedCount;
    }
}
