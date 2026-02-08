<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\WarehouseOrder;
use Modules\Warehouse\Models\WarehouseOrderItem;
use Modules\Warehouse\Models\WarehouseSetting;

class PackingController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        return view('warehouse::packing.index');
    }

    public function scanOrder(Request $request)
    {
        $request->validate(['barcode' => 'required|string']);

        $order = WarehouseOrder::with('items')
            ->where('barcode', $request->barcode)
            ->orWhere('order_number', $request->barcode)
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'سفارشی با این بارکد یافت نشد.']);
        }

        if ($order->status !== WarehouseOrder::STATUS_PREPARING) {
            return response()->json([
                'success' => false,
                'message' => 'این سفارش در مرحله آماده‌سازی نیست. وضعیت فعلی: ' . $order->status_label,
            ]);
        }

        $scannedCount = $order->items()->where('scanned', true)->count();
        $totalCount = $order->items()->count();

        return response()->json([
            'success' => true,
            'message' => 'سفارش ' . $order->order_number . ' بارگذاری شد.',
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'barcode' => $order->barcode,
                'customer_name' => $order->customer_name,
                'customer_mobile' => $order->customer_mobile,
                'shipping_type' => $order->shipping_type,
                'total_weight' => $order->total_weight,
                'status' => $order->status,
                'scanned_count' => $scannedCount,
                'total_count' => $totalCount,
                'all_scanned' => $scannedCount >= $totalCount,
                'items' => $order->items->map(fn($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'product_barcode' => $item->product_barcode,
                    'product_sku' => $item->product_sku,
                    'quantity' => $item->quantity,
                    'weight' => $item->weight,
                    'scanned' => $item->scanned,
                ]),
            ],
        ]);
    }

    public function scanProduct(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:warehouse_orders,id',
            'barcode' => 'required|string',
        ]);

        $item = WarehouseOrderItem::where('warehouse_order_id', $request->order_id)
            ->where(function ($q) use ($request) {
                $q->where('product_barcode', $request->barcode)
                  ->orWhere('product_sku', $request->barcode);
            })
            ->where('scanned', false)
            ->first();

        if (!$item) {
            // Check if already scanned
            $alreadyScanned = WarehouseOrderItem::where('warehouse_order_id', $request->order_id)
                ->where(function ($q) use ($request) {
                    $q->where('product_barcode', $request->barcode)
                      ->orWhere('product_sku', $request->barcode);
                })
                ->where('scanned', true)
                ->exists();

            if ($alreadyScanned) {
                return response()->json(['success' => false, 'message' => 'این محصول قبلا اسکن شده.', 'already_scanned' => true]);
            }

            return response()->json(['success' => false, 'message' => 'محصولی با این بارکد در سفارش یافت نشد.']);
        }

        $item->markScanned();

        // Check if all items are scanned
        $order = WarehouseOrder::find($request->order_id);
        $allScanned = $order->items()->where('scanned', false)->count() === 0;

        return response()->json([
            'success' => true,
            'message' => $item->product_name . ' اسکن شد.',
            'item_id' => $item->id,
            'all_scanned' => $allScanned,
        ]);
    }

    public function verifyWeight(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:warehouse_orders,id',
            'actual_weight' => 'required|numeric|min:0',
        ]);

        $order = WarehouseOrder::findOrFail($request->order_id);
        $tolerance = (float) WarehouseSetting::get('weight_tolerance', '5');

        $order->actual_weight = $request->actual_weight;

        $diff = $order->weight_difference_percent;
        $weightOk = $diff === null || $diff <= $tolerance;

        $order->weight_verified = $weightOk;
        $order->save();

        if ($weightOk) {
            $order->updateStatus(WarehouseOrder::STATUS_PACKED);
            return response()->json([
                'success' => true,
                'verified' => true,
                'message' => 'وزن تایید شد. سفارش آماده ارسال است.',
                'difference' => $diff ? round($diff, 1) : 0,
            ]);
        }

        return response()->json([
            'success' => true,
            'verified' => false,
            'message' => 'اختلاف وزن: ' . round($diff, 1) . '% - احتمالا محصولی جا مانده!',
            'expected' => $order->total_weight,
            'actual' => $order->actual_weight,
            'difference' => round($diff, 1),
        ]);
    }

    public function forceVerify(Request $request)
    {
        $request->validate(['order_id' => 'required|exists:warehouse_orders,id']);

        $order = WarehouseOrder::findOrFail($request->order_id);
        $order->weight_verified = true;
        $order->save();
        $order->updateStatus(WarehouseOrder::STATUS_PACKED);

        return response()->json([
            'success' => true,
            'message' => 'وزن به صورت دستی تایید شد.',
        ]);
    }
}
