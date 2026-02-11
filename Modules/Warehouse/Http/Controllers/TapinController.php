<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\WarehouseSetting;
use Modules\Warehouse\Models\WarehouseOrder;
use Modules\Warehouse\Services\TapinService;
use Illuminate\Support\Facades\Log;

class TapinController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $apiKey = WarehouseSetting::get('tapin_api_key');
        $settings = [
            'api_url' => WarehouseSetting::get('tapin_api_url', 'https://api.tapin.ir'),
            'api_key' => $apiKey,
            'shop_id' => WarehouseSetting::get('tapin_shop_id'),
            'sender_name' => WarehouseSetting::get('tapin_sender_name') ?: WarehouseSetting::get('amadest_sender_name'),
            'sender_mobile' => WarehouseSetting::get('tapin_sender_mobile') ?: WarehouseSetting::get('amadest_sender_mobile'),
            'has_key' => !empty($apiKey),
            'key_preview' => $apiKey ? (substr($apiKey, 0, 8) . '...' . substr($apiKey, -4) . ' (طول: ' . strlen($apiKey) . ')') : '',
            'shipping_provider' => WarehouseSetting::get('shipping_provider', 'amadest'),
            'order_type' => WarehouseSetting::get('tapin_order_type', '1'),
            'box_id' => WarehouseSetting::get('tapin_box_id', '10'),
        ];

        return view('warehouse::tapin.index', compact('settings'));
    }

    public function saveSettings(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'api_url' => 'nullable|url|max:500',
            'api_key' => 'nullable|string|max:5000',
            'shop_id' => 'nullable|string|max:100',
            'sender_name' => 'nullable|string|max:255',
            'sender_mobile' => 'nullable|string|max:20',
            'order_type' => 'nullable|integer|in:1,2',
            'box_id' => 'nullable|integer|min:1',
        ]);

        if (!empty($validated['api_url'])) {
            WarehouseSetting::set('tapin_api_url', $validated['api_url']);
        }
        if (!empty($validated['api_key'])) {
            WarehouseSetting::set('tapin_api_key', $validated['api_key']);
        }
        if (!empty($validated['shop_id'])) {
            WarehouseSetting::set('tapin_shop_id', $validated['shop_id']);
        }
        if (!empty($validated['sender_name'])) {
            WarehouseSetting::set('tapin_sender_name', $validated['sender_name']);
        }
        if (!empty($validated['sender_mobile'])) {
            WarehouseSetting::set('tapin_sender_mobile', $validated['sender_mobile']);
        }
        if (isset($validated['order_type'])) {
            WarehouseSetting::set('tapin_order_type', $validated['order_type']);
        }
        if (isset($validated['box_id'])) {
            WarehouseSetting::set('tapin_box_id', $validated['box_id']);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تنظیمات تاپین ذخیره شد.']);
        }

        return redirect()->route('warehouse.tapin.index')
            ->with('success', 'تنظیمات تاپین ذخیره شد.');
    }

    public function testConnection()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new TapinService();
        $result = $service->testConnection();

        return response()->json($result);
    }

    public function checkPrice(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new TapinService();
        $result = $service->checkPrice($request->all());

        return response()->json($result);
    }

    public function getShopDetails()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new TapinService();
        $result = $service->getShopDetails();

        return response()->json($result);
    }

    public function getShopCredit()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new TapinService();
        $result = $service->getShopCredit();

        return response()->json($result);
    }

    public function track(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $request->validate([
            'tracking_code' => 'required|string|max:100',
        ]);

        $service = new TapinService();
        $result = $service->trackShipment($request->tracking_code);

        return response()->json($result);
    }

    /**
     * تغییر سرویس‌دهنده ارسال (آمادست/تاپین)
     */
    public function setProvider(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'provider' => 'required|in:amadest,tapin',
        ]);

        WarehouseSetting::set('shipping_provider', $validated['provider']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'سرویس‌دهنده ارسال تغییر کرد: ' . ($validated['provider'] === 'tapin' ? 'تاپین' : 'آمادست'),
            ]);
        }

        return redirect()->back()->with('success', 'سرویس‌دهنده ارسال تغییر کرد.');
    }

    /**
     * لیست سفارشات در حال آماده‌سازی که هنوز بارکد ندارن
     */
    public function getPendingOrders()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $orders = WarehouseOrder::where('status', WarehouseOrder::STATUS_PREPARING)
            ->where('shipping_type', 'post')
            ->where(function ($q) {
                $q->whereNull('amadest_barcode')->orWhere('amadest_barcode', '');
            })
            ->orderBy('created_at', 'desc')
            ->get(['id', 'order_number', 'customer_name', 'customer_mobile', 'total_weight', 'created_at']);

        return response()->json([
            'success' => true,
            'count' => $orders->count(),
            'orders' => $orders,
        ]);
    }

    /**
     * پاک کردن بارکدهای قدیمی سفارشات ارسال‌نشده (بدون تغییر وضعیت)
     */
    public function clearBarcodes()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $orders = WarehouseOrder::whereIn('status', [
                WarehouseOrder::STATUS_PENDING,
                WarehouseOrder::STATUS_PREPARING,
            ])
            ->where('shipping_type', 'post')
            ->where(function ($q) {
                $q->whereNotNull('amadest_barcode')->where('amadest_barcode', '!=', '');
            })
            ->get();

        $count = $orders->count();
        foreach ($orders as $order) {
            // پاک کردن بارکدها
            $order->amadest_barcode = null;
            $order->post_tracking_code = null;

            // پاک کردن فلگ registered تا دوباره ثبت بشه
            $wcData = is_array($order->wc_order_data) ? $order->wc_order_data : [];
            if (isset($wcData['tapin']['registered'])) {
                unset($wcData['tapin']['registered']);
                unset($wcData['tapin']['tapin_order_id']);
                $order->wc_order_data = $wcData;
            }
            $order->save();
        }

        Log::info('Bulk clear old barcodes', ['count' => $count, 'user' => auth()->user()->name]);

        return response()->json([
            'success' => true,
            'message' => "{$count} بارکد پاک شد. حالا میتونید دوباره پرینت بزنید تا در تاپین ثبت بشه.",
            'cleared' => $count,
        ]);
    }

    /**
     * لیست استان و شهر تاپین - فرمت آماده برای سایت ووکامرس
     */
    public function tapinStatesForWc()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $tapin = new TapinService();
        $stateTree = $tapin->getStateTree();

        if (empty($stateTree)) {
            return response()->json(['success' => false, 'message' => 'دریافت لیست استان‌ها از تاپین ناموفق بود']);
        }

        // ساخت لیست استان‌ها برای select ووکامرس
        $wcStates = [];
        $wcCities = [];

        foreach ($stateTree as $province) {
            $code = $province['code'] ?? null;
            $title = trim($province['title'] ?? '');
            if (!$code || !$title) continue;

            // کد ووکامرس: TP + کد تاپین (مثلاً TP1 = تهران)
            $wcCode = 'TP' . $code;
            $wcStates[$wcCode] = $title;

            $cities = $province['cities'] ?? [];
            foreach ($cities as $city) {
                $cityCode = $city['code'] ?? null;
                $cityTitle = trim(str_replace(["\r\n", "\r", "\n"], '', $city['title'] ?? ''));
                if (!$cityCode || !$cityTitle) continue;
                $wcCities[$wcCode][$cityCode] = $cityTitle;
            }
        }

        // خروجی HTML برای copy/paste
        $stateHtml = "<select name=\"state\">\n    <option value=\"\">انتخاب استان</option>\n";
        foreach ($wcStates as $code => $title) {
            $stateHtml .= "    <option value=\"{$code}\">{$title}</option>\n";
        }
        $stateHtml .= "</select>";

        return response()->json([
            'success' => true,
            'states' => $wcStates,
            'cities' => $wcCities,
            'state_html' => $stateHtml,
            'tapin_state_tree' => $stateTree,
            'message' => count($wcStates) . ' استان و ' . collect($wcCities)->flatten()->count() . ' شهر دریافت شد',
        ]);
    }

    /**
     * تشخیص تفاوت کدهای استان ووکامرس و تاپین
     */
    public function diagnosticStates()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $tapin = new TapinService();

        // دریافت لیست استان‌ها از تاپین
        $stateTree = $tapin->getStateTree();

        // نگاشت فعلی ووکامرس
        $wcMap = TapinService::getWcStateMap();

        // استان‌های استفاده‌شده در سفارشات واقعی
        $usedStates = WarehouseOrder::whereNotNull('wc_order_data')
            ->get(['wc_order_data'])
            ->map(function ($order) {
                $data = is_array($order->wc_order_data) ? $order->wc_order_data : [];
                return ($data['shipping']['state'] ?? '') ?: ($data['billing']['state'] ?? '');
            })
            ->filter()
            ->countBy()
            ->sortDesc();

        // بررسی هر کد ووکامرس
        $mapping = [];
        foreach ($wcMap as $code => $persianName) {
            $provinceCode = $tapin->findProvinceCode($code);
            $tapinProvince = null;
            foreach ($stateTree as $p) {
                if (($p['code'] ?? null) == $provinceCode) {
                    $tapinProvince = $p['title'] ?? '';
                    break;
                }
            }
            $mapping[] = [
                'wc_code' => $code,
                'wc_name' => $persianName,
                'tapin_code' => $provinceCode,
                'tapin_name' => $tapinProvince,
                'match' => $provinceCode !== null,
                'used_count' => $usedStates[$code] ?? 0,
            ];
        }

        // کدهای ووکامرسی که تو مپ نیستن ولی تو سفارشات استفاده شدن
        $unmapped = [];
        foreach ($usedStates as $code => $count) {
            if (!isset($wcMap[strtoupper($code)])) {
                $provinceCode = $tapin->findProvinceCode($code);
                $unmapped[] = [
                    'wc_code' => $code,
                    'tapin_code' => $provinceCode,
                    'used_count' => $count,
                ];
            }
        }

        // لیست استان‌های تاپین
        $tapinProvinces = collect($stateTree)->map(fn($p) => [
            'code' => $p['code'] ?? null,
            'title' => $p['title'] ?? '',
            'cities_count' => count($p['cities'] ?? []),
        ]);

        return response()->json([
            'success' => true,
            'wc_mapping' => $mapping,
            'unmapped_codes' => $unmapped,
            'tapin_provinces' => $tapinProvinces,
            'used_states_in_orders' => $usedStates,
        ]);
    }

    /**
     * ثبت دسته‌ای سفارشات در تاپین
     */
    public function bulkRegister(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $tapin = new TapinService();
        if (!$tapin->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'تاپین تنظیم نشده']);
        }

        $orders = WarehouseOrder::where('status', WarehouseOrder::STATUS_PREPARING)
            ->where('shipping_type', 'post')
            ->where(function ($q) {
                $q->whereNull('amadest_barcode')->orWhere('amadest_barcode', '');
            })
            ->with('items')
            ->get();

        if ($orders->isEmpty()) {
            return response()->json(['success' => true, 'message' => 'سفارشی برای ثبت وجود ندارد', 'results' => []]);
        }

        $results = [];
        foreach ($orders as $order) {
            try {
                $wcData = is_array($order->wc_order_data) ? $order->wc_order_data : [];
                $shipping = $wcData['shipping'] ?? [];
                $billing = $wcData['billing'] ?? [];
                $address = ($shipping['address_1'] ?? '') ?: ($billing['address_1'] ?? '');
                $city = ($shipping['city'] ?? '') ?: ($billing['city'] ?? '');
                $state = ($shipping['state'] ?? '') ?: ($billing['state'] ?? '');
                $fullAddress = implode('، ', array_filter([$state, $city, $address]));
                $postcode = ($shipping['postcode'] ?? '') ?: ($billing['postcode'] ?? '');

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

                $result = $tapin->createShipment([
                    'external_order_id' => $order->order_number,
                    'recipient_name' => $order->customer_name,
                    'recipient_mobile' => $order->customer_mobile,
                    'recipient_address' => $fullAddress ?: 'آدرس نامشخص',
                    'recipient_postal_code' => $postcode ?: '0000000000',
                    'recipient_city_name' => $city,
                    'recipient_province' => $state,
                    'tapin_province_code' => $tapinData['province_code'] ?? null,
                    'tapin_city_code' => $tapinData['city_code'] ?? null,
                    'weight' => $order->total_weight_with_box_grams ?: 500,
                    'value' => (int)($wcData['total'] ?? 100000),
                    'products' => $products,
                ]);

                $status = 'failed';
                if ($result['success'] ?? false) {
                    $data = $result['data'] ?? [];
                    $barcode = $data['barcode'] ?? null;
                    $tapinOrderId = $data['order_id'] ?? null;

                    // اگه بارکد نداریم، change-status(1) بزن تا بارکد بگیریم
                    if (empty($barcode) && $tapinOrderId) {
                        $statusResult = $tapin->changeOrderStatus($tapinOrderId, 1);
                        if ($statusResult['success'] ?? false) {
                            $barcode = $statusResult['data']['barcode'] ?? null;
                        }
                    }

                    $trackingRef = $barcode ?: ($tapinOrderId ? 'TAPIN-' . $tapinOrderId : null);

                    if ($trackingRef) {
                        $order->amadest_barcode = $trackingRef;
                        $order->tracking_code = $order->tracking_code ?: $trackingRef;
                        if ($barcode) {
                            $order->post_tracking_code = $barcode;
                        }
                        $order->save();
                        $status = 'success';
                    }
                }

                $results[] = [
                    'order_number' => $order->order_number,
                    'customer' => $order->customer_name,
                    'status' => $status,
                    'message' => $result['message'] ?? '',
                    'barcode' => $result['data']['barcode'] ?? '',
                    'order_id' => $result['data']['order_id'] ?? '',
                ];
            } catch (\Exception $e) {
                Log::error('Tapin bulk register error', ['order' => $order->order_number, 'error' => $e->getMessage()]);
                $results[] = [
                    'order_number' => $order->order_number,
                    'customer' => $order->customer_name,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        $successCount = collect($results)->where('status', 'success')->count();
        return response()->json([
            'success' => true,
            'message' => "{$successCount} از " . count($results) . ' سفارش ثبت شد',
            'results' => $results,
        ]);
    }
}
