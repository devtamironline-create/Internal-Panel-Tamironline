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
        ];

        return view('warehouse::amadest.index', compact('settings'));
    }

    public function saveSettings(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'api_key' => 'required|string|max:500',
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
}
