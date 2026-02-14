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
        $statusSyncEnabled = WarehouseSetting::get('wc_status_sync_enabled', '1') === '1';
        $statusMap = WooCommerceService::WC_STATUS_MAP;
        $wcStatusLabels = WooCommerceService::WC_STATUS_LABELS;

        return view('warehouse::woocommerce.index', compact('settings', 'lastSync', 'statusSyncEnabled', 'statusMap', 'wcStatusLabels'));
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
            'wc_status_sync_enabled' => 'nullable|in:0,1',
        ]);

        WarehouseSetting::set('wc_site_url', $validated['site_url']);
        WarehouseSetting::set('wc_consumer_key', $validated['consumer_key']);
        WarehouseSetting::set('wc_consumer_secret', $validated['consumer_secret']);
        WarehouseSetting::set('wc_status_sync_enabled', $request->input('wc_status_sync_enabled', '0'));

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

    public function syncProducts()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید.'], 403);
        }

        try {
            $service = new WooCommerceService();
            $result = $service->syncProducts();
            return response()->json($result);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Product sync error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'خطا: ' . $e->getMessage()]);
        }
    }

    public function redetectShippingTypes()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید.'], 403);
        }

        try {
            $service = new WooCommerceService();
            $result = $service->redetectShippingTypes();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'خطا: ' . $e->getMessage()]);
        }
    }

    public function toggleStatusSync(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید.'], 403);
        }

        $enabled = $request->input('enabled') ? '1' : '0';
        WarehouseSetting::set('wc_status_sync_enabled', $enabled);

        return response()->json(['success' => true, 'enabled' => $enabled === '1']);
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
