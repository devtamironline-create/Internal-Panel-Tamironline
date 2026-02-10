<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Warehouse\Models\WarehouseBoxSize;
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
        $alertMobile = WarehouseSetting::get('alert_mobile', '');
        $shippingMappingsJson = WarehouseSetting::get('wc_shipping_mappings', '{}');
        $shippingMappings = json_decode($shippingMappingsJson, true) ?: [];

        $invoiceSettings = [
            'invoice_store_name' => WarehouseSetting::get('invoice_store_name', ''),
            'invoice_subtitle' => WarehouseSetting::get('invoice_subtitle', ''),
            'invoice_logo' => WarehouseSetting::get('invoice_logo', ''),
            'invoice_sender_phone' => WarehouseSetting::get('invoice_sender_phone', ''),
            'invoice_sender_address' => WarehouseSetting::get('invoice_sender_address', ''),
        ];

        $boxSizes = WarehouseBoxSize::orderBy('sort_order')->get();

        return view('warehouse::settings.index', compact('shippingTypes', 'weightTolerance', 'alertMobile', 'shippingMappings', 'invoiceSettings', 'boxSizes'));
    }

    public function update(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $request->validate([
            'weight_tolerance' => 'required|numeric|min:0|max:100',
            'alert_mobile' => 'nullable|string|max:20',
            'invoice_store_name' => 'nullable|string|max:255',
            'invoice_subtitle' => 'nullable|string|max:255',
            'invoice_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'invoice_sender_phone' => 'nullable|string|max:50',
            'invoice_sender_address' => 'nullable|string|max:500',
        ]);

        WarehouseSetting::set('weight_tolerance', $request->weight_tolerance);

        if ($request->has('alert_mobile')) {
            WarehouseSetting::set('alert_mobile', $request->alert_mobile);
        }

        // Invoice settings
        foreach (['invoice_store_name', 'invoice_subtitle', 'invoice_sender_phone', 'invoice_sender_address'] as $key) {
            if ($request->has($key)) {
                WarehouseSetting::set($key, $request->input($key));
            }
        }

        if ($request->hasFile('invoice_logo')) {
            $oldLogo = WarehouseSetting::get('invoice_logo');
            if ($oldLogo) {
                Storage::disk('public')->delete($oldLogo);
            }
            $path = $request->file('invoice_logo')->store('warehouse/invoice', 'public');
            WarehouseSetting::set('invoice_logo', $path);
        }

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

    public function deleteInvoiceLogo()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $logo = WarehouseSetting::get('invoice_logo');
        if ($logo) {
            Storage::disk('public')->delete($logo);
            WarehouseSetting::set('invoice_logo', '');
        }

        return redirect()->route('warehouse.settings.index')
            ->with('success', 'لوگوی فاکتور حذف شد.');
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

    public function storeBoxSize(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:50',
            'length' => 'required|numeric|min:0.1',
            'width' => 'required|numeric|min:0.1',
            'height' => 'required|numeric|min:0.1',
            'weight' => 'required|integer|min:1',
        ]);

        $maxSort = WarehouseBoxSize::max('sort_order') ?? 0;

        WarehouseBoxSize::create([
            'name' => $request->name,
            'length' => $request->length,
            'width' => $request->width,
            'height' => $request->height,
            'weight' => $request->weight,
            'sort_order' => $maxSort + 1,
        ]);

        return redirect()->route('warehouse.settings.index')
            ->with('success', 'سایز کارتن جدید اضافه شد.');
    }

    public function updateBoxSize(Request $request, WarehouseBoxSize $boxSize)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:50',
            'length' => 'required|numeric|min:0.1',
            'width' => 'required|numeric|min:0.1',
            'height' => 'required|numeric|min:0.1',
            'weight' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $boxSize->update($request->only('name', 'length', 'width', 'height', 'weight', 'is_active'));

        return response()->json(['success' => true, 'message' => 'سایز کارتن ویرایش شد.']);
    }

    public function deleteBoxSize(WarehouseBoxSize $boxSize)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $boxSize->delete();

        return redirect()->route('warehouse.settings.index')
            ->with('success', 'سایز کارتن حذف شد.');
    }
}
