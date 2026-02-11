<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\WarehouseSetting;
use Modules\Warehouse\Services\TapinService;

class TapinController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $settings = [
            'api_url' => WarehouseSetting::get('tapin_api_url', 'https://api.tapin.ir'),
            'api_key' => WarehouseSetting::get('tapin_api_key'),
            'shop_id' => WarehouseSetting::get('tapin_shop_id'),
            'sender_name' => WarehouseSetting::get('tapin_sender_name') ?: WarehouseSetting::get('amadest_sender_name'),
            'sender_mobile' => WarehouseSetting::get('tapin_sender_mobile') ?: WarehouseSetting::get('amadest_sender_mobile'),
            'has_key' => !empty(WarehouseSetting::get('tapin_api_key')),
            'shipping_provider' => WarehouseSetting::get('shipping_provider', 'amadest'),
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
}
