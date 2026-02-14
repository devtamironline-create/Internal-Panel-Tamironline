<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\OrderLog;
use Modules\Warehouse\Models\WarehouseOrder;
use Modules\Warehouse\Models\WarehouseProduct;
use Modules\Warehouse\Models\WarehouseSetting;
use Modules\Warehouse\Services\AmadestService;
use Modules\Warehouse\Services\TapinService;
use Modules\SMS\Services\KavenegarService;

class PrintController extends Controller
{
    public function invoice(WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $order->load(['items', 'boxSize', 'shippingTypeRelation']);

        // اگر آیتم‌ها وزن ندارن، از جدول محصولات بگیر و آپدیت کن
        $needsWeightUpdate = $order->items->contains(fn($item) => $item->weight == 0 && $item->wc_product_id);
        if ($needsWeightUpdate) {
            $productIds = $order->items->pluck('wc_product_id')->filter()->unique()->toArray();
            $weightsMap = WarehouseProduct::getWeightsMap($productIds);

            foreach ($order->items as $item) {
                if ($item->weight == 0 && $item->wc_product_id) {
                    $weight = (float)($weightsMap[$item->wc_product_id] ?? 0);
                    if ($weight > 0) {
                        $item->update(['weight' => $weight]);
                    }
                }
            }
            $order->refresh();
            $order->load('items');
        }

        // اگر آیتم‌ها ابعاد ندارن، از جدول محصولات بگیر و آپدیت کن
        $needsDimensionsUpdate = $order->items->contains(fn($item) => ($item->length == 0 || $item->width == 0 || $item->height == 0) && $item->wc_product_id);
        if ($needsDimensionsUpdate) {
            $productIds = $order->items->pluck('wc_product_id')->filter()->unique()->toArray();
            $dimensionsMap = WarehouseProduct::getDimensionsMap($productIds);

            foreach ($order->items as $item) {
                if (($item->length == 0 || $item->width == 0 || $item->height == 0) && $item->wc_product_id) {
                    $dims = $dimensionsMap[$item->wc_product_id] ?? null;
                    if ($dims && ($dims['length'] ?? 0) > 0) {
                        $item->update([
                            'length' => (float)($dims['length'] ?? 0),
                            'width' => (float)($dims['width'] ?? 0),
                            'height' => (float)($dims['height'] ?? 0),
                        ]);
                    }
                }
            }
            $order->refresh();
            $order->load(['items', 'boxSize']);
        }

        // همیشه وزن کل رو از روی آیتم‌ها محاسبه و آپدیت کن
        $totalWeightGrams = $order->items->sum(fn($i) => WarehouseOrder::toGrams($i->weight) * $i->quantity);
        if ($totalWeightGrams > 0) {
            $order->update(['total_weight' => $totalWeightGrams]);
            $order->refresh();
            $order->load('items');
        }

        // ثبت خودکار در سرویس ارسال (آمادست یا تاپین) برای سفارشات پستی
        $shippingProvider = WarehouseSetting::get('shipping_provider', 'amadest');
        $registrationError = null;

        // اگه سرویس تاپین هست ولی بارکد قدیمی آمادست داره، پاک کن تا دوباره ثبت بشه
        $wcData = is_array($order->wc_order_data) ? $order->wc_order_data : [];
        $tapinRegistered = ($wcData['tapin']['registered'] ?? false);
        if ($order->shipping_type === 'post' && $shippingProvider === 'tapin' && !empty($order->amadest_barcode) && !$tapinRegistered) {
            Log::info('Clearing old barcode for Tapin re-registration', [
                'order' => $order->order_number,
                'old_barcode' => $order->amadest_barcode,
            ]);
            $order->update(['amadest_barcode' => null, 'post_tracking_code' => null]);
            $order->refresh();
        }

        if ($order->shipping_type === 'post' && empty($order->amadest_barcode)) {
            try {
                $wcData = is_array($order->wc_order_data) ? $order->wc_order_data : [];
                $shipping = $wcData['shipping'] ?? [];
                $billing = $wcData['billing'] ?? [];
                $address = ($shipping['address_1'] ?? '') ?: ($billing['address_1'] ?? '');
                $city = ($shipping['city'] ?? '') ?: ($billing['city'] ?? '');
                $state = ($shipping['state'] ?? '') ?: ($billing['state'] ?? '');
                $fullAddress = implode('، ', array_filter([$state, $city, $address]));
                $postcode = ($shipping['postcode'] ?? '') ?: ($billing['postcode'] ?? '');

                Log::info('Shipping auto-register attempt', [
                    'order' => $order->order_number,
                    'provider' => $shippingProvider,
                    'weight_grams' => $order->total_weight_with_box_grams,
                    'city' => $city,
                    'state' => $state,
                ]);

                if ($shippingProvider === 'tapin') {
                    $registrationError = $this->registerViaTapin($order, $wcData, $fullAddress, $postcode, $city, $state);
                } else {
                    $registrationError = $this->registerViaAmadest($order, $wcData, $fullAddress, $postcode, $city, $state);
                }

                $order->refresh();
                $order->load(['items', 'boxSize']);
            } catch (\Exception $e) {
                $registrationError = $shippingProvider . ': ' . $e->getMessage();
                Log::error('Shipping auto-register error', ['provider' => $shippingProvider, 'order' => $order->order_number, 'error' => $e->getMessage()]);
            }
        }

        // Track print count
        $order->increment('print_count');

        // Log every print
        Log::channel('daily')->info('فاکتور چاپ شد', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'print_count' => $order->print_count,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->name,
            'printed_at' => now()->toDateTimeString(),
        ]);

        OrderLog::log($order, OrderLog::ACTION_PRINTED, 'چاپ فاکتور (بار ' . $order->print_count . ')', [
            'print_count' => $order->print_count,
        ]);

        // Send SMS alert on duplicate print
        if ($order->print_count > 1) {
            $alertMobile = WarehouseSetting::get('alert_mobile');
            if (!empty($alertMobile)) {
                try {
                    $sms = new KavenegarService();
                    $message = "هشدار: فاکتور {$order->order_number} برای بار {$order->print_count} توسط " . auth()->user()->name . " پرینت شد.";
                    $sms->send($alertMobile, $message);
                } catch (\Exception $e) {
                    Log::error('SMS alert failed for duplicate print', ['error' => $e->getMessage()]);
                }
            }
        }

        // Mark as printed and move to packed (در انتظار اسکن خروج)
        if ($order->status === WarehouseOrder::STATUS_PENDING) {
            $order->updateStatus(WarehouseOrder::STATUS_PACKED);
        }

        // سفارشات پستی بعد از پرینت فاکتور مستقیم به ارسال شده تغییر میکنن
        if ($order->shipping_type === 'post' && $order->status === WarehouseOrder::STATUS_PACKED) {
            $order->updateStatus(WarehouseOrder::STATUS_SHIPPED);
            OrderLog::log($order, OrderLog::ACTION_SCANNED_SHIPPED, 'ارسال خودکار سفارش پستی پس از پرینت فاکتور');
        }

        $invoiceSettings = [
            'store_name' => WarehouseSetting::get('invoice_store_name', 'گنجه'),
            'subtitle' => WarehouseSetting::get('invoice_subtitle', 'فاکتور سفارش انبار'),
            'logo' => WarehouseSetting::get('invoice_logo', ''),
            'sender_phone' => WarehouseSetting::get('invoice_sender_phone', ''),
            'sender_address' => WarehouseSetting::get('invoice_sender_address', ''),
        ];

        return view('warehouse::print.invoice', compact('order', 'invoiceSettings', 'shippingProvider', 'registrationError'));
    }

    /**
     * تلاش مجدد ثبت در سرویس ارسال
     */
    public function retryRegister(WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $order->load(['items', 'boxSize']);

        $shippingProvider = WarehouseSetting::get('shipping_provider', 'amadest');

        if ($order->shipping_type !== 'post') {
            return response()->json(['success' => false, 'message' => 'سفارش پستی نیست']);
        }

        // پاک کردن بارکد قبلی و فلگ registered برای ثبت مجدد
        $order->amadest_barcode = null;
        $order->post_tracking_code = null;
        $wcData = is_array($order->wc_order_data) ? $order->wc_order_data : [];
        if (isset($wcData['tapin']['registered'])) {
            unset($wcData['tapin']['registered']);
            $order->wc_order_data = $wcData;
        }
        $order->save();

        $wcData = is_array($order->wc_order_data) ? $order->wc_order_data : [];
        $shipping = $wcData['shipping'] ?? [];
        $billing = $wcData['billing'] ?? [];
        $address = ($shipping['address_1'] ?? '') ?: ($billing['address_1'] ?? '');
        $city = ($shipping['city'] ?? '') ?: ($billing['city'] ?? '');
        $state = ($shipping['state'] ?? '') ?: ($billing['state'] ?? '');
        $fullAddress = implode('، ', array_filter([$state, $city, $address]));
        $postcode = ($shipping['postcode'] ?? '') ?: ($billing['postcode'] ?? '');

        try {
            if ($shippingProvider === 'tapin') {
                $error = $this->registerViaTapin($order, $wcData, $fullAddress, $postcode, $city, $state);
            } else {
                $error = $this->registerViaAmadest($order, $wcData, $fullAddress, $postcode, $city, $state);
            }

            $order->refresh();

            if ($error) {
                return response()->json(['success' => false, 'message' => $error]);
            }

            return response()->json([
                'success' => true,
                'message' => 'ثبت شد! بارکد: ' . ($order->amadest_barcode ?? 'نامشخص'),
                'barcode' => $order->amadest_barcode,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function label(WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $order->load(['items', 'boxSize']);

        return view('warehouse::print.label', compact('order'));
    }

    /**
     * ثبت سفارش از طریق آمادست
     * @return string|null خطا یا null اگه موفق بود
     */
    private function registerViaAmadest(WarehouseOrder $order, array $wcData, string $fullAddress, string $postcode, string $city, string $state): ?string
    {
        $amadest = new AmadestService();
        if (!$amadest->isConfigured()) return 'آمادست تنظیم نشده';

        $cityId = $amadest->findCityId($city, $state);

        $result = $amadest->createShipment([
            'external_order_id' => $order->order_number,
            'recipient_name' => $order->customer_name,
            'recipient_mobile' => $order->customer_mobile,
            'recipient_address' => $fullAddress ?: 'آدرس نامشخص',
            'recipient_postal_code' => $postcode ?: null,
            'recipient_city_id' => $cityId,
            'weight' => $order->total_weight_with_box_grams ?: 500,
            'value' => (int)($wcData['total'] ?? 100000),
        ]);

        Log::info('Amadest auto-register result', ['order' => $order->order_number, 'success' => $result['success'] ?? false]);

        $data = $result['data'] ?? [];
        $amadestId = $data['amadest_order_id'] ?? $data['id'] ?? null;
        $amadestTrackingCode = $data['amadast_tracking_code'] ?? null;
        $courierTrackingCode = $data['courier_tracking_code'] ?? null;
        $isDuplicate = !($result['success'] ?? false) && str_contains($result['message'] ?? '', 'تکراری');

        if ($result['success'] ?? false) {
            $order->amadest_barcode = $amadestTrackingCode ?: (string) $amadestId;
            $order->tracking_code = $order->tracking_code ?: $order->amadest_barcode;
            if ($courierTrackingCode) {
                $order->post_tracking_code = $courierTrackingCode;
            }
            $order->save();
            Log::info('Amadest barcode saved', [
                'order' => $order->order_number,
                'amadest_barcode' => $order->amadest_barcode,
                'courier_tracking_code' => $courierTrackingCode,
            ]);
            OrderLog::log($order, OrderLog::ACTION_SHIPPING_REGISTERED, 'ثبت در آمادست — بارکد: ' . $order->amadest_barcode, [
                'provider' => 'amadest',
                'barcode' => $order->amadest_barcode,
            ]);
            return null;
        } elseif ($isDuplicate) {
            Log::info('Amadest order duplicate, searching existing', ['order' => $order->order_number]);
            $searchResult = $amadest->searchOrders([$order->customer_mobile]);
            $externalId = (int) preg_replace('/\D/', '', $order->order_number);
            $found = false;
            foreach ($searchResult['data'] ?? [] as $existing) {
                if (($existing['external_order_id'] ?? null) == $externalId) {
                    $order->amadest_barcode = (string) ($existing['amadast_tracking_code'] ?? '');
                    $order->tracking_code = $order->tracking_code ?: $order->amadest_barcode;
                    $order->post_tracking_code = $existing['courier_tracking_code'] ?? null;
                    $order->save();
                    $found = true;
                    Log::info('Amadest existing order found', ['order' => $order->order_number, 'amadest_id' => $order->amadest_barcode]);
                    break;
                }
            }
            if (!$found) {
                $order->amadest_barcode = 'AMD-' . $externalId;
                $order->save();
                Log::warning('Amadest duplicate but search failed', ['order' => $order->order_number]);
            }
            return null;
        } else {
            $error = $result['message'] ?? 'خطای نامشخص';
            Log::warning('Amadest auto-register failed', ['order' => $order->order_number, 'error' => $error]);
            OrderLog::log($order, OrderLog::ACTION_SHIPPING_FAILED, 'خطای ثبت آمادست: ' . $error, [
                'provider' => 'amadest',
                'error' => $error,
            ]);
            return 'آمادست: ' . $error;
        }
    }

    /**
     * ثبت سفارش از طریق تاپین
     * @return string|null خطا یا null اگه موفق بود
     */
    private function registerViaTapin(WarehouseOrder $order, array $wcData, string $fullAddress, string $postcode, string $city, string $state): ?string
    {
        $tapin = new TapinService();
        if (!$tapin->isConfigured()) {
            return 'تاپین تنظیم نشده (API Key یا Shop ID خالی)';
        }

        // نرمال‌سازی کد پستی (تبدیل فارسی/عربی + حذف غیرعددی)
        $postcode = TapinService::normalizePostalCode($postcode);
        if (empty($postcode) || strlen($postcode) !== 10) {
            Log::warning('Tapin: postal code missing or invalid', ['order' => $order->order_number, 'postcode' => $postcode]);
            return 'کد پستی ثبت نشده یا نامعتبر است (باید ۱۰ رقم باشد) — کد فعلی: "' . ($postcode ?: 'خالی') . '"';
        }

        // ساخت لیست محصولات از آیتم‌های سفارش
        $products = [];
        foreach ($order->items as $item) {
            $products[] = [
                'title' => $item->product_name ?: 'کالا',
                'count' => (int) $item->quantity,
                'price' => (int) ($item->price ?? 0),
                'weight' => (int) WarehouseOrder::toGrams($item->weight),
                'discount' => 0,
                'product_id' => null,
            ];
        }

        // خواندن لوکیشن تاپین اگه قبلاً ذخیره شده
        $tapinData = $wcData['tapin'] ?? [];

        // ابعاد جعبه سفارش برای match با بسته‌های تاپین
        $box = $order->boxSize;

        Log::info('Tapin register postal code', ['order' => $order->order_number, 'postcode' => $postcode]);

        $result = $tapin->createShipment([
            'external_order_id' => $order->order_number,
            'recipient_name' => $order->customer_name,
            'recipient_mobile' => $order->customer_mobile,
            'recipient_address' => $fullAddress ?: 'آدرس نامشخص',
            'recipient_postal_code' => $postcode,
            'recipient_city_name' => $city,
            'recipient_province' => $state,
            'tapin_province_code' => $tapinData['province_code'] ?? null,
            'tapin_city_code' => $tapinData['city_code'] ?? null,
            'weight' => $order->total_weight_with_box_grams ?: 500,
            'value' => (int)($wcData['total'] ?? 100000),
            'products' => $products,
            'box_length' => $box->length ?? null,
            'box_width' => $box->width ?? null,
            'box_height' => $box->height ?? null,
        ]);

        Log::info('Tapin auto-register result', ['order' => $order->order_number, 'success' => $result['success'] ?? false, 'message' => $result['message'] ?? '']);

        if ($result['success'] ?? false) {
            $data = $result['data'] ?? [];
            $barcode = $data['barcode'] ?? null;
            $tapinOrderId = $data['order_id'] ?? null;

            Log::info('Tapin register response data', [
                'order' => $order->order_number,
                'barcode' => $barcode,
                'tapin_order_id' => $tapinOrderId,
                'full_data' => $data,
                'duplicate' => $result['duplicate'] ?? false,
            ]);

            // اگه بارکد نداریم، از لیست سفارشات بگیر (register_type=1 باید بارکد بده، ولی به عنوان fallback)
            $fallbackError = null;
            if (empty($barcode) && $tapinOrderId) {
                // مرحله ۱: دریافت جزئیات سفارش از لیست
                $detailsResult = $tapin->getOrderDetails($tapinOrderId);
                Log::info('Tapin getOrderDetails fallback', [
                    'order' => $order->order_number,
                    'success' => $detailsResult['success'] ?? false,
                    'data' => $detailsResult['data'] ?? [],
                ]);
                if ($detailsResult['success'] ?? false) {
                    $barcode = $detailsResult['data']['barcode']
                        ?? $detailsResult['data']['post_barcode']
                        ?? null;
                }

                // مرحله ۲: اگه هنوز بارکد نداریم، change-status رو امتحان کن
                if (empty($barcode)) {
                    // اول status 2 (در حال پرینت) بزن، بعد status 1
                    foreach ([2, 1] as $tryStatus) {
                        $statusResult = $tapin->changeOrderStatus($tapinOrderId, $tryStatus);
                        Log::info('Tapin change-status(' . $tryStatus . ') result', [
                            'order' => $order->order_number,
                            'success' => $statusResult['success'] ?? false,
                            'data' => $statusResult['data'] ?? [],
                            'message' => $statusResult['message'] ?? '',
                        ]);
                        if ($statusResult['success'] ?? false) {
                            $barcode = $statusResult['data']['barcode'] ?? null;
                            if (!empty($barcode)) break;
                        } else {
                            $fallbackError = $statusResult['message'] ?? 'خطای نامشخص';
                        }
                    }
                }
            }

            $trackingRef = $barcode ?: ($tapinOrderId ? 'TAPIN-' . $tapinOrderId : null);

            if ($trackingRef) {
                $order->amadest_barcode = $trackingRef;
                $order->tracking_code = $order->tracking_code ?: $trackingRef;
                if ($barcode) {
                    $order->post_tracking_code = $barcode;
                }
                // ذخیره فلگ registered برای تشخیص ثبت تاپین از آمادست
                $wcDataCurrent = is_array($order->wc_order_data) ? $order->wc_order_data : [];
                $wcDataCurrent['tapin']['registered'] = true;
                $wcDataCurrent['tapin']['tapin_order_id'] = $tapinOrderId;
                $order->wc_order_data = $wcDataCurrent;
                $order->save();
                Log::info('Tapin order saved', [
                    'order' => $order->order_number,
                    'barcode' => $barcode,
                    'tapin_order_id' => $tapinOrderId,
                    'saved_ref' => $trackingRef,
                ]);
                OrderLog::log($order, OrderLog::ACTION_SHIPPING_REGISTERED, 'ثبت در تاپین — بارکد: ' . $trackingRef, [
                    'provider' => 'tapin',
                    'barcode' => $barcode,
                    'tapin_order_id' => $tapinOrderId,
                ]);
                // اگه بارکد پست نگرفتیم، خطا رو برگردون (ولی سفارش ثبت شده)
                if (empty($barcode) && $fallbackError) {
                    return 'تاپین: ثبت شد (TAPIN-' . $tapinOrderId . ') ولی بارکد پست نیومد: ' . $fallbackError;
                }
                return null; // موفق
            }
            return 'تاپین: ثبت شد ولی بارکد/شناسه دریافت نشد';
        }

        $error = $result['message'] ?? 'خطای نامشخص';
        Log::warning('Tapin auto-register failed', ['order' => $order->order_number, 'error' => $error, 'postcode_sent' => $postcode]);
        OrderLog::log($order, OrderLog::ACTION_SHIPPING_FAILED, 'خطای ثبت تاپین: ' . $error . ' (کد پستی ارسالی: ' . $postcode . ')', [
            'provider' => 'tapin',
            'error' => $error,
            'postcode_sent' => $postcode,
        ]);
        return 'تاپین: ' . $error;
    }
}
