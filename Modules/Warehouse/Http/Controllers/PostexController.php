<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
            'api_url' => WarehouseSetting::get('postex_api_url', 'https://api.postex.ir'),
            'api_key' => $apiKey,
            'has_key' => !empty($apiKey),
            'key_preview' => $apiKey ? (substr($apiKey, 0, 8) . '...' . substr($apiKey, -4) . ' (طول: ' . strlen($apiKey) . ')') : '',
            'shipping_provider' => WarehouseSetting::get('shipping_provider', 'amadest'),
            'collection_type' => WarehouseSetting::get('postex_collection_type', 'postex_drop_off'),
            'from_city_code' => WarehouseSetting::get('postex_from_city_code', '444'),
        ];

        return view('warehouse::postex.index', compact('settings'));
    }

    public function saveSettings(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'api_url' => 'nullable|url|max:500',
            'api_key' => 'nullable|string|max:5000',
            'collection_type' => 'nullable|in:pick_up,courier_drop_off,postex_drop_off',
            'from_city_code' => 'nullable|integer|min:1',
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
        $provinces = $service->getProvinces();

        return response()->json([
            'success' => !empty($provinces),
            'data' => $provinces,
            'count' => count($provinces),
        ]);
    }

    public function getCities(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $provinceCode = $request->get('province_code');

        $service = new PostexService();
        $cities = $service->getCities($provinceCode);

        return response()->json([
            'success' => !empty($cities),
            'data' => $cities,
            'count' => count($cities),
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
