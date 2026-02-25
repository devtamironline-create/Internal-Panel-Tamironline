<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Warehouse\Models\WarehouseSetting;
use Modules\Warehouse\Services\Cod24Service;

class Cod24Controller extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $settings = [
            'api_url'           => WarehouseSetting::get('cod24_api_url', 'https://api.cod24.ir'),
            'username'          => WarehouseSetting::get('cod24_username', ''),
            'has_password'      => !empty(WarehouseSetting::get('cod24_password')),
            'shipping_provider' => WarehouseSetting::get('shipping_provider', 'amadest'),
            'sender_name'       => WarehouseSetting::get('cod24_sender_name', ''),
            'sender_mobile'     => WarehouseSetting::get('cod24_sender_mobile', ''),
            'id_type_send'      => WarehouseSetting::get('cod24_id_type_send', '1'),
            'id_pay_method'     => WarehouseSetting::get('cod24_id_pay_method', '0'),
            'id_packet_type'    => WarehouseSetting::get('cod24_id_packet_type', '1'),
            'fallback_city_code'=> WarehouseSetting::get('cod24_fallback_city_code', ''),
            'city_map'          => WarehouseSetting::get('cod24_city_map', ''),
        ];

        return view('warehouse::cod24.index', compact('settings'));
    }

    public function saveSettings(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'api_url'           => 'nullable|url|max:500',
            'username'          => 'nullable|string|max:200',
            'password'          => 'nullable|string|max:200',
            'sender_name'       => 'nullable|string|max:100',
            'sender_mobile'     => 'nullable|string|max:20',
            'id_type_send'      => 'nullable|integer|min:0|max:10',
            'id_pay_method'     => 'nullable|integer|min:0|max:10',
            'id_packet_type'    => 'nullable|integer|min:0|max:10',
            'fallback_city_code'=> 'nullable|integer|min:1',
            'city_map'          => 'nullable|string|max:5000',
        ]);

        if (!empty($validated['api_url'])) {
            WarehouseSetting::set('cod24_api_url', $validated['api_url']);
        }
        if (!empty($validated['username'])) {
            WarehouseSetting::set('cod24_username', $validated['username']);
        }
        if (!empty($validated['password'])) {
            WarehouseSetting::set('cod24_password', $validated['password']);
            // پاک کردن توکن قدیمی تا دوباره بگیره
            Cache::forget('cod24_bearer_token');
        }
        if (isset($validated['sender_name'])) {
            WarehouseSetting::set('cod24_sender_name', $validated['sender_name']);
        }
        if (isset($validated['sender_mobile'])) {
            WarehouseSetting::set('cod24_sender_mobile', $validated['sender_mobile']);
        }
        if (isset($validated['id_type_send'])) {
            WarehouseSetting::set('cod24_id_type_send', $validated['id_type_send']);
        }
        if (isset($validated['id_pay_method'])) {
            WarehouseSetting::set('cod24_id_pay_method', $validated['id_pay_method']);
        }
        if (isset($validated['id_packet_type'])) {
            WarehouseSetting::set('cod24_id_packet_type', $validated['id_packet_type']);
        }
        if (isset($validated['fallback_city_code'])) {
            WarehouseSetting::set('cod24_fallback_city_code', $validated['fallback_city_code']);
        }
        if (isset($validated['city_map'])) {
            WarehouseSetting::set('cod24_city_map', $validated['city_map']);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تنظیمات COD24 ذخیره شد.']);
        }

        return redirect()->route('warehouse.cod24.index')
            ->with('success', 'تنظیمات COD24 ذخیره شد.');
    }

    public function testConnection()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new Cod24Service();
        $result = $service->testConnection();

        return response()->json($result);
    }

    public function getWalletBalance()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new Cod24Service();
        $result = $service->getWalletAmount();

        return response()->json($result);
    }

    public function getStates()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new Cod24Service();
        $states = $service->getStates();

        return response()->json([
            'success' => !empty($states),
            'data'    => $states,
            'count'   => count($states),
        ]);
    }

    public function getCities(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service   = new Cod24Service();
        $search    = trim($request->get('search', ''));
        $stateCode = $request->get('state_code') ? (int) $request->get('state_code') : null;

        $cities = $stateCode ? $service->getCities($stateCode) : $service->getPostCities();

        if (!empty($search)) {
            $cities = array_values(array_filter($cities, function ($c) use ($search) {
                $name = $c['name'] ?? $c['cityName'] ?? $c['title'] ?? '';
                return str_contains($name, $search);
            }));
        }

        return response()->json([
            'success' => !empty($cities),
            'data'    => $cities,
            'count'   => count($cities),
        ]);
    }

    /**
     * تست resolve شهر — نشون می‌ده کد شهر از کجا و چطوری پیدا می‌شه
     */
    public function testCityResolve(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $city = trim($request->get('city', ''));
        $state = trim($request->get('state', ''));
        if (empty($city)) {
            return response()->json(['success' => false, 'message' => 'نام شهر را وارد کنید']);
        }

        $service = new Cod24Service();

        // ترجمه کد استان
        $wcMap = \Modules\Warehouse\Services\TapinService::getWcStateMap();
        $statePersian = isset($wcMap[strtoupper($state)]) ? $wcMap[strtoupper($state)] : $state;

        // تست هر دو API
        $results = [];

        // ۱) نقشه دستی
        $manualCode = null;
        $cityMapRaw = \Modules\Warehouse\Models\WarehouseSetting::get('cod24_city_map', '');
        if (!empty($cityMapRaw)) {
            foreach (explode("\n", $cityMapRaw) as $line) {
                $line = trim($line);
                if (empty($line) || !str_contains($line, ':')) continue;
                [$mapCity, $mapCode] = explode(':', $line, 2);
                if (trim($mapCity) === $city && is_numeric(trim($mapCode))) {
                    $manualCode = (int) trim($mapCode);
                    break;
                }
            }
        }
        $results['manual_map'] = $manualCode ? ['code' => $manualCode, 'found' => true] : ['found' => false];

        // تابع نرمال‌سازی عربی/فارسی
        $normalize = fn(string $s) => str_replace(['ي', 'ك', 'ە', "\xC2\xA0", '  '], ['ی', 'ک', 'ه', ' ', ' '], trim($s));
        $nCity = $normalize($city);

        // ۲) getCities (بر اساس استان)
        $stateCode = null;
        $getCitiesResult = null;
        if (!empty($statePersian)) {
            $nState = $normalize($statePersian);
            $states = $service->getStates();
            foreach ($states as $st) {
                $sName = $normalize($st['name'] ?? $st['stateName'] ?? $st['title'] ?? '');
                if ($sName === $nState || str_contains($sName, $nState) || str_contains($nState, $sName)) {
                    $stateCode = (int) ($st['code'] ?? $st['stateCode'] ?? $st['id'] ?? 0);
                    break;
                }
            }
            if ($stateCode) {
                $stateCities = $service->getCities($stateCode);
                foreach ($stateCities as $c) {
                    $cName = $c['name'] ?? $c['cityName'] ?? $c['title'] ?? '';
                    $nName = $normalize($cName);
                    if ($nName === $nCity || str_contains($nName, $nCity) || str_contains($nCity, $nName)) {
                        $getCitiesResult = ['code' => (int) ($c['code'] ?? $c['cityCode'] ?? $c['id'] ?? 0), 'name' => $cName, 'raw' => $c];
                        break;
                    }
                }
            }
        }
        $results['getCities'] = $getCitiesResult ? array_merge($getCitiesResult, ['found' => true, 'stateCode' => $stateCode]) : ['found' => false, 'stateCode' => $stateCode, 'statePersian' => $statePersian];

        // ۳) getPostCities
        $postCitiesResult = null;
        $postCities = $service->getPostCities();
        foreach ($postCities as $c) {
            $cName = $c['name'] ?? $c['cityName'] ?? $c['title'] ?? '';
            $nName = $normalize($cName);
            if ($nName === $nCity || str_contains($nName, $nCity) || str_contains($nCity, $nName)) {
                $postCitiesResult = ['code' => (int) ($c['code'] ?? $c['cityCode'] ?? $c['id'] ?? 0), 'name' => $cName, 'raw' => $c];
                break;
            }
        }
        $results['getPostCities'] = $postCitiesResult ? array_merge($postCitiesResult, ['found' => true, 'total' => count($postCities)]) : ['found' => false, 'total' => count($postCities)];

        // ۴) نتیجه نهایی findCityCode
        $finalCode = $service->findCityCode($city, $state);
        $results['final_findCityCode'] = $finalCode;

        // فالبک
        $results['fallback_city_code'] = (int) \Modules\Warehouse\Models\WarehouseSetting::get('cod24_fallback_city_code', 0);

        return response()->json([
            'success' => true,
            'city' => $city,
            'state_raw' => $state,
            'state_persian' => $statePersian,
            'results' => $results,
        ]);
    }

    public function calculatePrice(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new Cod24Service();
        $result = $service->getPostPrice($request->all());

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

        $service = new Cod24Service();
        $result = $service->trackShipment($request->tracking_code);

        return response()->json($result);
    }

    public function getBarcodeStatus(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $request->validate([
            'barcode' => 'required|string|max:100',
        ]);

        $service = new Cod24Service();
        $result = $service->getBarcodeStatus($request->barcode);

        return response()->json($result);
    }

    public function setProvider(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'provider' => 'required|in:amadest,tapin,postex,cod24',
        ]);

        WarehouseSetting::set('shipping_provider', $validated['provider']);

        $providerNames = [
            'amadest' => 'آمادست',
            'tapin'   => 'تاپین',
            'postex'  => 'پستکس',
            'cod24'   => 'COD24',
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
