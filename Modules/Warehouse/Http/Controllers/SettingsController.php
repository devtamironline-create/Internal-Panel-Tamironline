<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\WarehouseSetting;
use Modules\Warehouse\Models\WarehouseShippingType;

class SettingsController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $shippingTypes = WarehouseShippingType::all();
        $weightTolerance = WarehouseSetting::get('weight_tolerance', '5');
        $shippingMappingsJson = WarehouseSetting::get('wc_shipping_mappings', '{}');
        $shippingMappings = json_decode($shippingMappingsJson, true) ?: [];

        return view('warehouse::settings.index', compact('shippingTypes', 'weightTolerance', 'shippingMappings'));
    }

    public function update(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $request->validate([
            'weight_tolerance' => 'required|numeric|min:0|max:100',
        ]);

        WarehouseSetting::set('weight_tolerance', $request->weight_tolerance);

        return redirect()->route('warehouse.settings.index')
            ->with('success', 'تنظیمات ذخیره شد.');
    }

    public function updateShippingType(Request $request, WarehouseShippingType $shippingType)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'timer_minutes' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $shippingType->update($request->only('name', 'timer_minutes', 'is_active'));

        return response()->json(['success' => true, 'message' => 'نوع ارسال ویرایش شد.']);
    }

    public function storeShippingType(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:warehouse_shipping_types,slug',
            'timer_minutes' => 'required|integer|min:1',
        ]);

        WarehouseShippingType::create($request->only('name', 'slug', 'timer_minutes'));

        return redirect()->route('warehouse.settings.index')
            ->with('success', 'نوع ارسال جدید اضافه شد.');
    }
}
