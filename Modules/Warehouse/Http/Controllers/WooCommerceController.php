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

    public function fixZeroWeightProducts()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید.'], 403);
        }

        try {
            $service = new WooCommerceService();
            $result = $service->fixZeroWeightProducts();
            return response()->json($result);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Fix zero weight error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'خطا: ' . $e->getMessage()]);
        }
    }

    public function debugProductWeight(\Illuminate\Http\Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید.'], 403);
        }

        try {
            $productId = $request->input('product_id');
            $service = new WooCommerceService();
            $result = $service->debugProductWeight($productId);
            return response()->json($result);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Debug product weight error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'خطا: ' . $e->getMessage()]);
        }
    }

    public function fixZeroWeightVariations()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید.'], 403);
        }

        try {
            // اجرای command برای fix کردن variations
            \Illuminate\Support\Facades\Artisan::call('warehouse:fix-variation-weights');
            $output = \Illuminate\Support\Facades\Artisan::output();

            // Parse کردن نتیجه
            preg_match('/(\d+) variation رفع شد/', $output, $matches);
            $fixed = isset($matches[1]) ? (int)$matches[1] : 0;

            return response()->json([
                'success' => true,
                'message' => "{$fixed} variation رفع شد.",
                'fixed' => $fixed,
                'output' => strip_tags($output),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Fix variation weight error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'خطا: ' . $e->getMessage()]);
        }
    }

    public function productsCatalog(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $query = \Modules\Warehouse\Models\WarehouseProduct::query();

        // فیلتر براساس جستجو
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('sku', 'LIKE', "%{$search}%")
                    ->orWhere('wc_product_id', $search);
            });
        }

        // فیلتر براساس نوع
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // فیلتر براساس وزن
        if ($weightFilter = $request->input('weight_filter')) {
            if ($weightFilter === 'zero') {
                $query->where('weight', 0);
            } elseif ($weightFilter === 'non_zero') {
                $query->where('weight', '>', 0);
            }
        }

        // مرتب‌سازی
        $sortBy = $request->input('sort', 'name');
        $sortDir = $request->input('dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        $products = $query->paginate(50);

        // آمار
        $stats = [
            'total' => \Modules\Warehouse\Models\WarehouseProduct::count(),
            'zero_weight' => \Modules\Warehouse\Models\WarehouseProduct::where('weight', 0)->count(),
            'bundles' => \Modules\Warehouse\Models\WarehouseProduct::whereIn('type', ['bundle', 'yith_bundle', 'woosb', 'grouped'])->count(),
        ];

        return view('warehouse::woocommerce.products-catalog', compact('products', 'stats'));
    }
}
