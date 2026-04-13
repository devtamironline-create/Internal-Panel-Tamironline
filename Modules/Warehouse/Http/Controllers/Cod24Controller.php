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
        $rawStates = $service->getStates();

        \Illuminate\Support\Facades\Log::info('Cod24 getStates raw', [
            'count' => count($rawStates),
            'sample' => !empty($rawStates) ? $rawStates[0] : 'empty',
            'keys' => !empty($rawStates) ? array_keys($rawStates[0]) : [],
        ]);

        // normalize — هوشمند: اول فیلدهای شناخته‌شده رو چک کن، بعد هر عدد/رشته
        $states = array_map(function ($s) {
            $code = (int) ($s['stateCode'] ?? $s['code'] ?? $s['id'] ?? $s['stateId'] ?? $s['Id'] ?? $s['Code'] ?? 0);
            $name = $s['stateNameFa'] ?? $s['name'] ?? $s['stateName'] ?? $s['title'] ?? $s['Name'] ?? $s['Title'] ?? '';

            // اگه هنوز code صفره، اولین عدد بزرگتر از صفر رو بگیر
            if ($code === 0) {
                foreach ($s as $val) {
                    if (is_numeric($val) && (int) $val > 0) {
                        $code = (int) $val;
                        break;
                    }
                }
            }
            // اگه name خالیه، اولین رشته غیرعددی بلند رو بگیر
            if (empty($name)) {
                foreach ($s as $val) {
                    if (is_string($val) && mb_strlen($val) > 1 && !is_numeric($val)) {
                        $name = $val;
                        break;
                    }
                }
            }

            return ['code' => $code, 'name' => $name];
        }, $rawStates);

        // فیلتر خالی‌ها
        $states = array_values(array_filter($states, fn($s) => $s['code'] > 0 && !empty($s['name'])));

        return response()->json([
            'success' => !empty($states),
            'data'    => $states,
            'count'   => count($states),
            'raw_keys' => !empty($rawStates) ? array_keys($rawStates[0]) : [],
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
            $normalize = function ($str) {
                $str = str_replace(['ي', 'ك', 'ة'], ['ی', 'ک', 'ه'], $str);
                return trim($str);
            };
            $nSearch = $normalize($search);
            $cities = array_values(array_filter($cities, function ($c) use ($nSearch, $normalize) {
                $name = $c['cityNameFa'] ?? $c['name'] ?? $c['cityName'] ?? $c['title'] ?? '';
                $nName = $normalize($name);
                return !empty($nName) && (str_contains($nName, $nSearch) || str_contains($nSearch, $nName));
            }));
        }

        // normalize output: هر آیتم حتماً name و code داشته باشه
        $cities = array_map(function ($c) {
            $code = (int) ($c['cityCode'] ?? $c['code'] ?? $c['id'] ?? $c['Id'] ?? $c['Code'] ?? 0);
            $name = $c['cityNameFa'] ?? $c['name'] ?? $c['cityName'] ?? $c['title'] ?? $c['Name'] ?? $c['Title'] ?? '';

            // فالبک هوشمند
            if ($code === 0) {
                foreach ($c as $val) {
                    if (is_numeric($val) && (int) $val > 0) { $code = (int) $val; break; }
                }
            }
            if (empty($name)) {
                foreach ($c as $val) {
                    if (is_string($val) && mb_strlen($val) > 1 && !is_numeric($val)) { $name = $val; break; }
                }
            }

            return [
                'code' => $code,
                'name' => $name,
                'stateCode' => (int) ($c['stateCode'] ?? $c['state_code'] ?? $c['stateId'] ?? $c['StateCode'] ?? 0),
            ];
        }, $cities);

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
                $sName = $normalize($st['stateNameFa'] ?? $st['name'] ?? $st['stateName'] ?? $st['title'] ?? '');
                if (!empty($sName) && ($sName === $nState || str_contains($sName, $nState) || str_contains($nState, $sName))) {
                    $stateCode = (int) ($st['stateCode'] ?? $st['code'] ?? $st['id'] ?? 0);
                    break;
                }
            }
            if ($stateCode) {
                $stateCities = $service->getCities($stateCode);
                foreach ($stateCities as $c) {
                    $cName = $c['cityNameFa'] ?? $c['name'] ?? $c['cityName'] ?? $c['title'] ?? '';
                    $nName = $normalize($cName);
                    if (!empty($nName) && ($nName === $nCity || str_contains($nName, $nCity) || str_contains($nCity, $nName))) {
                        $getCitiesResult = ['code' => (int) ($c['cityCode'] ?? $c['code'] ?? $c['id'] ?? 0), 'name' => $cName, 'raw' => $c];
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
            $cName = $c['cityNameFa'] ?? $c['name'] ?? $c['cityName'] ?? $c['title'] ?? '';
            $nName = $normalize($cName);
            if (!empty($nName) && ($nName === $nCity || str_contains($nName, $nCity) || str_contains($nCity, $nName))) {
                $postCitiesResult = ['code' => (int) ($c['cityCode'] ?? $c['code'] ?? $c['id'] ?? 0), 'name' => $cName, 'raw' => $c];
                break;
            }
        }
        $results['getPostCities'] = $postCitiesResult ? array_merge($postCitiesResult, ['found' => true, 'total' => count($postCities)]) : ['found' => false, 'total' => count($postCities)];

        // نمونه ۳ شهر اول — برای دیباگ فیلدها
        $results['postCities_sample'] = array_slice($postCities, 0, 3);
        // نمونه getCities
        if (!empty($stateCities)) {
            $results['getCities_sample'] = array_slice($stateCities, 0, 3);
        }

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

    /**
     * صفحه نقشه شهر/استان — لیست کامل COD24 + کد ووکامرس
     */
    public function cityMap()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $wcMap = \Modules\Warehouse\Services\TapinService::getWcStateMap();
        $states = [];
        $debugInfo = [];

        try {
            $service = new Cod24Service();
            if (!$service->isConfigured()) {
                return view('warehouse::cod24.city-map', compact('states', 'wcMap', 'debugInfo'))
                    ->with('error', 'COD24 تنظیم نشده. ابتدا نام کاربری و رمز عبور را وارد کنید.');
            }

            $token = $service->getToken();
            $debugInfo['token'] = !empty($token) ? 'OK' : 'FAIL';

            // ساخت نقشه کد→نام استان از API
            $stateNameMap = []; // stateCode => stateName
            $rawStates = $service->getStates();
            $debugInfo['states_count'] = count($rawStates);
            if (!empty($rawStates)) {
                $debugInfo['state_fields'] = implode(', ', array_keys($rawStates[0]));
            }

            foreach ($rawStates as $s) {
                // تلاش برای پیدا کردن کد و نام از هر فیلد ممکن
                $name = '';
                $code = 0;
                foreach ($s as $key => $val) {
                    $keyLower = strtolower($key);
                    if ($name === '' && is_string($val) && mb_strlen($val) > 1 && !is_numeric($val)) {
                        $name = $val;
                    }
                    if ($code === 0 && is_numeric($val) && (int)$val > 0) {
                        $code = (int) $val;
                    }
                }
                // اگه فیلدهای مشخص داره اون‌ها رو ترجیح بده
                $name = $s['stateNameFa'] ?? $s['name'] ?? $s['stateName'] ?? $s['title'] ?? $name;
                $code = (int) ($s['stateCode'] ?? $s['code'] ?? $s['id'] ?? $s['stateId'] ?? $code);

                if (!empty($name) && $code > 0) {
                    $stateNameMap[$code] = $name;
                }
            }
            $debugInfo['stateNameMap_count'] = count($stateNameMap);
            $debugInfo['stateNameMap_sample'] = json_encode(array_slice($stateNameMap, 0, 3, true), JSON_UNESCAPED_UNICODE);

            // دریافت شهرها
            $allCities = $service->getPostCities();
            $debugInfo['postCities_count'] = count($allCities);
            if (!empty($allCities)) {
                $debugInfo['city_fields'] = implode(', ', array_keys($allCities[0]));
            }

            if (empty($allCities)) {
                $allCities = $service->getCities(null);
                $debugInfo['getCities_null'] = count($allCities);
            }

            // دسته‌بندی شهرها بر اساس استان
            $grouped = [];
            foreach ($allCities as $c) {
                $cityCode = (int) ($c['cityCode'] ?? $c['code'] ?? $c['id'] ?? 0);
                $cityName = $c['cityNameFa'] ?? $c['name'] ?? $c['cityName'] ?? $c['title'] ?? '';
                $stCode = (int) ($c['stateCode'] ?? $c['state_code'] ?? $c['stateId'] ?? 0);

                if (empty($cityName)) continue;

                if (!isset($grouped[$stCode])) {
                    $stateName = $stateNameMap[$stCode] ?? null;
                    $grouped[$stCode] = [
                        'code' => $stCode,
                        'name' => $stateName ?: 'نامشخص (کد ' . $stCode . ')',
                        'cities' => [],
                    ];
                }

                $grouped[$stCode]['cities'][] = ['code' => $cityCode, 'name' => $cityName];
            }

            // مرتب‌سازی
            foreach ($grouped as &$state) {
                usort($state['cities'], fn($a, $b) => strcmp($a['name'], $b['name']));
            }
            unset($state);

            $states = array_values($grouped);
            usort($states, fn($a, $b) => strcmp($a['name'], $b['name']));

            // مپ کد ووکامرس
            $wcMapFlipped = array_flip($wcMap);
            foreach ($states as &$state) {
                $state['wc_code'] = $wcMapFlipped[$state['name']] ?? null;
            }
            unset($state);

            $debugInfo['final_states'] = count($states);
            $debugInfo['final_cities'] = array_sum(array_map(fn($s) => count($s['cities']), $states));

        } catch (\Exception $e) {
            $debugInfo['error'] = $e->getMessage();
        }

        return view('warehouse::cod24.city-map', compact('states', 'wcMap', 'debugInfo'));
    }
}
