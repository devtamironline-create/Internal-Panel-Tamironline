<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\OrderLog;
use Modules\Warehouse\Models\WarehouseOrder;

class ExitScanController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        return view('warehouse::exit-scan.index');
    }

    public function scanAndShip(Request $request)
    {
        $request->validate(['barcode' => 'required|string']);

        $barcode = trim($request->barcode);

        $order = WarehouseOrder::where('post_tracking_code', $barcode)
            ->orWhere('barcode', $barcode)
            ->orWhere('order_number', $barcode)
            ->orWhere('amadest_barcode', $barcode)
            ->orWhere('tracking_code', $barcode)
            ->first();

        if (!$order) {
            $numericBarcode = preg_replace('/\D/', '', $barcode);
            if ($numericBarcode) {
                $order = WarehouseOrder::where('post_tracking_code', 'like', '%' . $numericBarcode)
                    ->orWhere('order_number', 'like', '%' . $numericBarcode)
                    ->orWhere('barcode', 'like', '%' . $numericBarcode)
                    ->first();
            }
        }

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'سفارشی با این بارکد یافت نشد.']);
        }

        if ($order->status !== WarehouseOrder::STATUS_PACKED) {
            return response()->json([
                'success' => false,
                'message' => 'این سفارش در صف ارسال (اسکن) نیست. وضعیت فعلی: ' . $order->status_label,
            ]);
        }

        $order->exit_scanned_at = now();
        $order->save();

        $order->updateStatus(WarehouseOrder::STATUS_SHIPPED);
        OrderLog::log($order, OrderLog::ACTION_SCANNED_SHIPPED, 'اسکن خروج از ایستگاه صف ارسال (اسکن: ' . $barcode . ')');

        return response()->json([
            'success' => true,
            'message' => 'سفارش ' . $order->order_number . ' با موفقیت ارسال شد.',
            'order' => [
                'id'            => $order->id,
                'order_number'  => $order->order_number,
                'customer_name' => $order->customer_name,
            ],
        ]);
    }
}
