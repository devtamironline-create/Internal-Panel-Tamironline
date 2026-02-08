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

        try {
            $service = new WooCommerceService();
            $result = $service->testConnection();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()]);
        }
    }

    public function fetchShippingMethods()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید.'], 403);
        }

        try {
            $service = new WooCommerceService();
            return response()->json($service->fetchShippingMethods());
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'خطا: ' . $e->getMessage(), 'methods' => []]);
        }
    }

    public function saveShippingMappings(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید.'], 403);
        }

        $mappings = $request->input('mappings', []);
        WarehouseSetting::set('wc_shipping_mappings', json_encode($mappings));

        return response()->json(['success' => true, 'message' => 'نقشه‌برداری نوع ارسال ذخیره شد.']);
    }

    public function sync(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید.'], 403);
        }

        try {
            $wcStatus = $request->input('wc_status', 'processing');
            if ($wcStatus === 'any') {
                $wcStatus = null;
            }

            $service = new WooCommerceService();
            $result = $service->syncOrders($wcStatus);

            return response()->json($result);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('WooCommerce sync error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'خطای سرور: ' . $e->getMessage(),
            ]);
        }
    }
}
