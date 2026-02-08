<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\WarehouseSetting;
use Modules\Warehouse\Services\WooCommerceService;

class WooCommerceController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $settings = [
            'site_url' => WarehouseSetting::get('wc_site_url'),
            'consumer_key' => WarehouseSetting::get('wc_consumer_key'),
            'consumer_secret' => WarehouseSetting::get('wc_consumer_secret'),
        ];

        $lastSync = WarehouseSetting::get('wc_last_sync');

        return view('warehouse::woocommerce.index', compact('settings', 'lastSync'));
    }

    public function saveSettings(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'site_url' => 'required|url|max:500',
            'consumer_key' => 'required|string|max:500',
            'consumer_secret' => 'required|string|max:500',
        ]);

        WarehouseSetting::set('wc_site_url', $validated['site_url']);
        WarehouseSetting::set('wc_consumer_key', $validated['consumer_key']);
        WarehouseSetting::set('wc_consumer_secret', $validated['consumer_secret']);

        return redirect()->route('warehouse.woocommerce.index')
            ->with('success', 'تنظیمات ووکامرس ذخیره شد.');
    }

    public function testConnection()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $service = new WooCommerceService();
        $result = $service->testConnection();

        return response()->json($result);
    }

    public function sync(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $wcStatus = $request->get('wc_status', 'processing');

        $service = new WooCommerceService();
        $result = $service->syncOrders($wcStatus);

        if ($request->ajax()) {
            return response()->json($result);
        }

        return redirect()->route('warehouse.woocommerce.index')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
