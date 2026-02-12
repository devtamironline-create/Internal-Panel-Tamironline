<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\WarehouseOrder;
use Modules\Warehouse\Models\WarehouseSetting;
use Modules\Warehouse\Models\WarehouseShippingType;
use Modules\Warehouse\Models\WarehouseWcShippingMethod;
use Modules\Warehouse\Services\WooCommerceService;

class ShippingController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $wcMethods = WarehouseWcShippingMethod::orderBy('zone_name')->orderBy('method_title')->get();
        $shippingTypes = WarehouseShippingType::all();
        $lastSync = WarehouseSetting::get('wc_shipping_methods_last_sync');

        // Count orders per shipping method
        $orderCounts = WarehouseOrder::whereNotNull('wc_order_data')
            ->get()
            ->groupBy(function ($order) {
                $lines = $order->wc_order_data['shipping_lines'] ?? [];
                if (empty($lines)) return 'unknown';
                return $lines[0]['method_id'] ?? 'unknown';
            })
            ->map->count();

        return view('warehouse::shipping.index', compact('wcMethods', 'shippingTypes', 'lastSync', 'orderCounts'));
    }

    public function syncFromWooCommerce()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید.'], 403);
        }

        try {
            $service = new WooCommerceService();
            $result = $service->syncShippingMethods();
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'خطا: ' . $e->getMessage()]);
        }
    }

    public function updateMapping(Request $request, WarehouseWcShippingMethod $method)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید.'], 403);
        }

        $request->validate([
            'mapped_shipping_type' => 'nullable|string|max:100',
        ]);

        $method->update(['mapped_shipping_type' => $request->mapped_shipping_type ?: null]);

        // Also update wc_shipping_mappings setting for backward compatibility
        $this->syncMappingsToSettings();

        return response()->json(['success' => true, 'message' => 'نوع ارسال ذخیره شد.']);
    }

    public function saveMappings(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید.'], 403);
        }

        $mappings = $request->input('mappings', []);

        foreach ($mappings as $methodId => $shippingType) {
            WarehouseWcShippingMethod::where('id', $methodId)
                ->update(['mapped_shipping_type' => $shippingType ?: null]);
        }

        $this->syncMappingsToSettings();

        return response()->json(['success' => true, 'message' => 'نقشه‌برداری روش‌های ارسال ذخیره شد.']);
    }

    public function redetectAll()
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

    /**
     * Sync DB mappings to the wc_shipping_mappings setting for backward compatibility
     */
    protected function syncMappingsToSettings(): void
    {
        $methods = WarehouseWcShippingMethod::whereNotNull('mapped_shipping_type')->get();
        $mappings = [];

        foreach ($methods as $method) {
            // Map by method_id (e.g. flat_rate) and by method_title
            $mappings[$method->method_id] = $method->mapped_shipping_type;
            $mappings[$method->method_title] = $method->mapped_shipping_type;
        }

        WarehouseSetting::set('wc_shipping_mappings', json_encode($mappings));
    }
}
