<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Warehouse\Models\WarehouseSetting;
use Modules\Warehouse\Services\PostexService;

class PostexController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $apiKey = WarehouseSetting::get('postex_api_key');
        $settings = [
            'api_url'           => WarehouseSetting::get('postex_api_url', 'https://api.postex.ir'),
            'api_key'           => $apiKey,
            'has_key'           => !empty($apiKey),
            'key_preview'       => $apiKey ? (substr($apiKey, 0, 8) . '...' . substr($apiKey, -4) . ' (طول: ' . strlen($apiKey) . ')') : '',
            'shipping_provider' => WarehouseSetting::get('shipping_provider', 'amadest'),
            'collection_type'   => WarehouseSetting::get('postex_collection_type', 'postex_drop_off'),
            'from_city_code'    => WarehouseSetting::get('postex_from_city_code', '444'),
            'from_name'         => WarehouseSetting::get('postex_from_name', ''),
            'from_phone'        => WarehouseSetting::get('postex_from_phone', ''),
            'from_telephone'    => WarehouseSetting::get('postex_from_telephone', ''),
            'from_address'      => WarehouseSetting::get('postex_from_address', ''),
            'from_postcode'     => WarehouseSetting::get('postex_from_postcode', ''),
            'courier'           => WarehouseSetting::get('postex_courier', 'post'),
            'payment_type'        => WarehouseSetting::get('postex_payment_type', '0'),
            'service_type'        => WarehouseSetting::get('postex_service_type', '0'),
            'fallback_city_code'  => WarehouseSetting::get('postex_fallback_city_code', ''),
            'city_map'            => WarehouseSetting::get('postex_city_map', ''),
        ];

        return view('warehouse::postex.index', compact('settings'));
    }

    public function saveSettings(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'api_url'        => 'nullable|url|max:500',
            'api_key'        => 'nullable|string|max:5000',
            'collection_type'=> 'nullable|in:pick_up,courier_drop_off,postex_drop_off',
            'from_city_code' => 'nullable|integer|min:1',
            'from_name'      => 'nullable|string|max:100',
            'from_phone'     => 'nullable|string|max:20',
            'from_telephone' => 'nullable|string|max:20',
            'from_address'   => 'nullable|string|max:500',
            'from_postcode'  => 'nullable|string|max:10',
            'courier'        => 'nullable|string|max:50',
            'payment_type'        => 'nullable|integer|min:0|max:10',
            'service_type'        => 'nullable|integer|min:0|max:10',
            'fallback_city_code'  => 'nullable|integer|min:1',
            'city_map'            => 'nullable|string|max:5000',
        ]);

        if (!empty($validated['api_url'])) {
            WarehouseSetting::set('postex_api_url', $validated['api_url']);
        }
        if (!empty($validated['api_key'])) {
            WarehouseSetting::set('postex_api_key', $validated['api_key']);
        }
        if (isset($validated['collection_type'])) {
            WarehouseSetting::set('postex_collection_type', $validated['collection_type']);
        }
        if (isset($validated['from_city_code'])) {
            WarehouseSetting::set('postex_from_city_code', $validated['from_city_code']);
        }
        if (isset($validated['from_name'])) {
            WarehouseSetting::set('postex_from_name', $validated['from_name']);
        }
        if (isset($validated['from_phone'])) {
            WarehouseSetting::set('postex_from_phone', $validated['from_phone']);
        }
        if (isset($validated['from_address'])) {
            WarehouseSetting::set('postex_from_address', $validated['from_address']);
        }
        if (isset($validated['from_postcode'])) {
            WarehouseSetting::set('postex_from_postcode', $validated['from_postcode']);
        }
        if (isset($validated['courier'])) {
            WarehouseSetting::set('postex_courier', $validated['courier']);
        }
        if (isset($validated['from_telephone'])) {
            WarehouseSetting::set('postex_from_telephone', $validated['from_telephone']);
        }
        if (isset($validated['payment_type'])) {
            WarehouseSetting::set('postex_payment_type', $validated['payment_type']);
        }
        if (isset($validated['service_type'])) {
            WarehouseSetting::set('postex_service_type', $validated['service_type']);
        }
        if (isset($validated['fallback_city_code'])) {
            WarehouseSetting::set('postex_fallback_city_code', $validated['fallback_city_code']);
        }
        if (isset($validated['city_map'])) {
            WarehouseSetting::set('postex_city_map', $validated['city_map']);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تنظیمات پستکس ذخیره شد.']);
        }

        return redirect()->route('warehouse.postex.index')
            ->with('success', 'تنظیمات پستکس ذخیره شد.');
    }

    public function testConnection()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new PostexService();
        $result = $service->testConnection();

        return response()->json($result);
    }

    public function getWalletBalance()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new PostexService();
        $result = $service->getWalletBalance();

        return response()->json($result);
    }

    public function getUserProfile()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new PostexService();
        $result = $service->getUserProfile();

        return response()->json($result);
    }

    public function getProvinces()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new PostexService();
        Cache::forget('postex_provinces'); // پاک کردن cache قدیمی برای debug
        $provinces = $service->getProvinces();

        // اگه خالی بود، raw response رو مستقیم از API بگیر برای debug
        $rawDebug = null;
        if (empty($provinces)) {
            try {
                $apiUrl = rtrim(WarehouseSetting::get('postex_api_url', 'https://api.postex.ir'), '/');
                $apiKey = WarehouseSetting::get('postex_api_key');
                $rawResp = \Illuminate\Support\Facades\Http::timeout(15)
                    ->withHeaders(['x-api-key' => $apiKey, 'Accept' => 'application/json'])
                    ->get($apiUrl . '/api/v1/location/provinces');
                $rawDebug = ['status' => $rawResp->status(), 'body' => substr($rawResp->body(), 0, 1000)];
            } catch (\Exception $e) {
                $rawDebug = ['error' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => !empty($provinces),
            'data'    => $provinces,
            'count'   => count($provinces),
            'debug'   => $rawDebug,
        ]);
    }

    public function getCities(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new PostexService();
        $search  = trim($request->get('search', ''));
        $cities  = $service->getCities();

        if (!empty($search)) {
            $cities = array_values(array_filter($cities, function ($c) use ($search) {
                $name = $c['name'] ?? $c['title'] ?? '';
                return str_contains($name, $search);
            }));
        }

        return response()->json([
            'success' => !empty($cities),
            'data'    => $cities,
            'count'   => count($cities),
        ]);
    }

    public function calculatePrice(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new PostexService();
        $result = $service->calculateShippingCost($request->all());

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

        $service = new PostexService();
        $result = $service->trackShipment($request->tracking_code);

        return response()->json($result);
    }

    public function debugCreateShipment(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new PostexService();
        if (!$service->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'API Key تنظیم نشده']);
        }

        $testPostcode = $request->get('postcode', '1234567890');
        $testCity     = $request->get('city', 'تهران');
        $testState    = $request->get('state', 'تهران');

        $cityCode = $service->findCityCode($testCity, $testState);

        // تست probe endpoint قبل از ثبت واقعی
        $probeResult = $service->probeCreateEndpoints();

        $result = $service->createShipment([
            'external_order_id'    => 'TEST-' . now()->timestamp,
            'recipient_name'       => 'تست تست',
            'recipient_mobile'     => '09120000000',
            'recipient_address'    => 'تهران، خیابان تست، پلاک ۱',
            'recipient_postal_code'=> $testPostcode,
            'to_city_code'         => $cityCode,
            'weight'               => 500,
            'value'                => 100000,
            'description'          => 'تست ثبت سفارش',
        ]);

        return response()->json([
            'city_code_found' => $cityCode,
            'probe'           => $probeResult,
            'result'          => $result,
        ]);
    }

    /**
     * کشف endpoint صحیح پستکس با تست چند مسیر مختلف
     */
    public function probeEndpoints(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new PostexService();
        if (!$service->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'API Key تنظیم نشده']);
        }

        $results = $service->probeCreateEndpoints();
        return response()->json($results);
    }

    public function setProvider(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'provider' => 'required|in:amadest,tapin,postex',
        ]);

        WarehouseSetting::set('shipping_provider', $validated['provider']);

        $providerNames = [
            'amadest' => 'آمادست',
            'tapin' => 'تاپین',
            'postex' => 'پستکس',
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'سرویس‌دهنده ارسال تغییر کرد: ' . ($providerNames[$validated['provider']] ?? $validated['provider']),
            ]);
        }

        return redirect()->back()->with('success', 'سرویس‌دهنده ارسال تغییر کرد.');
    }
}
