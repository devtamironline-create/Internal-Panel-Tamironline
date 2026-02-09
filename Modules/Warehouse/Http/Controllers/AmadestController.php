<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\WarehouseSetting;
use Modules\Warehouse\Services\AmadestService;

class AmadestController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $settings = [
            'api_key' => WarehouseSetting::get('amadest_api_key'),
            'api_url' => WarehouseSetting::get('amadest_api_url', 'https://api.amadest.com'),
            'store_id' => WarehouseSetting::get('amadest_store_id'),
            'location_id' => WarehouseSetting::get('amadest_location_id'),
            'sender_name' => WarehouseSetting::get('amadest_sender_name'),
            'sender_mobile' => WarehouseSetting::get('amadest_sender_mobile'),
            'warehouse_address' => WarehouseSetting::get('amadest_warehouse_address'),
        ];

        return view('warehouse::amadest.index', compact('settings'));
    }

    public function saveSettings(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'api_key' => 'required|string|max:5000',
            'api_url' => 'nullable|url|max:500',
        ]);

        WarehouseSetting::set('amadest_api_key', $validated['api_key']);
        if (!empty($validated['api_url'])) {
            WarehouseSetting::set('amadest_api_url', $validated['api_url']);
        }

        return redirect()->route('warehouse.amadest.index')
            ->with('success', 'تنظیمات آمادست ذخیره شد.');
    }

    public function testConnection()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new AmadestService();
        $result = $service->testConnection();

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

        $service = new AmadestService();
        $result = $service->trackShipment($request->tracking_code);

        return response()->json($result);
    }

    public function getProvinces()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new AmadestService();
        $provinces = $service->getProvinces();

        return response()->json(['success' => true, 'data' => $provinces]);
    }

    public function getCities(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $provinceId = $request->get('province_id') ? (int) $request->get('province_id') : null;
        $service = new AmadestService();
        $cities = $service->getCities($provinceId);

        return response()->json(['success' => true, 'data' => $cities]);
    }

    public function setup(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'sender_name' => 'required|string|max:255',
            'sender_mobile' => 'required|string|max:20',
            'warehouse_address' => 'required|string|max:1000',
            'province_id' => 'required|integer',
            'city_id' => 'required|integer',
            'postal_code' => 'nullable|string|max:20',
            'warehouse_title' => 'nullable|string|max:255',
            'store_title' => 'nullable|string|max:255',
        ]);

        $service = new AmadestService();
        $result = $service->setup($validated);

        return response()->json($result);
    }
}
