<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\OrderLog;
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

        $barcode = trim($request->barcode);

        $order = WarehouseOrder::with('items')
            ->where('barcode', $barcode)
            ->orWhere('order_number', $barcode)
            ->orWhere('amadest_barcode', $barcode)
            ->orWhere('tracking_code', $barcode)
            ->first();

        // Fallback: try numeric-only match
        if (!$order) {
            $numericBarcode = preg_replace('/\D/', '', $barcode);
            if ($numericBarcode) {
                $order = WarehouseOrder::with('items')
                    ->where('order_number', 'like', '%' . $numericBarcode)
                    ->orWhere('barcode', 'like', '%' . $numericBarcode)
                    ->first();
            }
        }

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

    public function verifyOrderBarcode(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:warehouse_orders,id',
            'barcode' => 'required|string',
        ]);

        $order = WarehouseOrder::findOrFail($request->input('order_id'));

        if ($order->status !== WarehouseOrder::STATUS_PREPARING) {
            return response()->json(['success' => false, 'message' => 'این سفارش در مرحله آماده‌سازی نیست.']);
        }

        // Check if barcode matches order barcode, order number, or tracking codes
        $barcode = trim($request->input('barcode'));
        $barcodeUpper = strtoupper($barcode);
        $matched = (
            strtoupper($order->barcode ?? '') === $barcodeUpper
            || strtoupper($order->order_number ?? '') === $barcodeUpper
            || strtoupper($order->amadest_barcode ?? '') === $barcodeUpper
            || strtoupper($order->tracking_code ?? '') === $barcodeUpper
        );

        // فالبک: مقایسه عددی (بارکدخوان ممکنه prefix/suffix اضافه کنه)
        if (!$matched) {
            $numericBarcode = preg_replace('/\D/', '', $barcode);
            $numericOrder = preg_replace('/\D/', '', $order->barcode ?? '');
            if ($numericBarcode && $numericOrder && str_contains($numericBarcode, $numericOrder)) {
                $matched = true;
            }
        }

        if (!$matched) {
            return response()->json(['success' => false, 'message' => 'بارکد با این سفارش مطابقت ندارد. اسکن شده: ' . $barcode]);
        }

        return response()->json([
            'success' => true,
            'message' => 'بارکد سفارش تایید شد.',
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

        $order = WarehouseOrder::with(['boxSize', 'items'])->findOrFail($request->order_id);
        $tolerance = (float) WarehouseSetting::get('weight_tolerance', '5');

        $order->actual_weight = $request->actual_weight;

        $expectedWeight = $order->total_weight_with_box_grams;
        $diff = $order->weight_difference_percent;
        $weightOk = $diff === null || $diff <= $tolerance;

        $order->weight_verified = $weightOk;
        $order->save();

        if ($weightOk) {
            $order->updateStatus(WarehouseOrder::STATUS_PACKED);
            OrderLog::log($order, OrderLog::ACTION_WEIGHT_VERIFIED, 'وزن تایید شد: ' . number_format($request->actual_weight) . 'g (وزن مورد انتظار با کارتن: ' . number_format($expectedWeight) . 'g، اختلاف: ' . round($diff ?? 0, 1) . '%)', [
                'actual_weight' => $request->actual_weight,
                'expected_weight' => $expectedWeight,
                'difference_percent' => round($diff ?? 0, 1),
            ]);
            return response()->json([
                'success' => true,
                'verified' => true,
                'message' => 'وزن تایید شد. سفارش آماده ارسال است.',
                'difference' => $diff ? round($diff, 1) : 0,
            ]);
        }

        OrderLog::log($order, OrderLog::ACTION_WEIGHT_REJECTED, 'وزن رد شد: ' . number_format($request->actual_weight) . 'g (وزن مورد انتظار با کارتن: ' . number_format($expectedWeight) . 'g، اختلاف: ' . round($diff, 1) . '%)', [
            'actual_weight' => $request->actual_weight,
            'expected_weight' => $expectedWeight,
            'difference_percent' => round($diff, 1),
        ]);

        return response()->json([
            'success' => true,
            'verified' => false,
            'message' => 'اختلاف وزن: ' . round($diff, 1) . '% - احتمالا محصولی جا مانده!',
            'expected' => $expectedWeight,
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

        OrderLog::log($order, OrderLog::ACTION_WEIGHT_FORCED, 'وزن به صورت دستی تایید شد');

        return response()->json([
            'success' => true,
            'message' => 'وزن به صورت دستی تایید شد.',
        ]);
    }
}
