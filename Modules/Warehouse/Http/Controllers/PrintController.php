<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\WarehouseOrder;

class PrintController extends Controller
{
    public function invoice(WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $order->load('items');

        // Mark as printed and move to preparing
        if ($order->status === WarehouseOrder::STATUS_PENDING) {
            $order->updateStatus(WarehouseOrder::STATUS_PREPARING);
        }

        return view('warehouse::print.invoice', compact('order'));
    }

    public function markPrinted(WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        if ($order->status === WarehouseOrder::STATUS_PENDING) {
            $order->updateStatus(WarehouseOrder::STATUS_PREPARING);
        }

        return response()->json(['success' => true, 'message' => 'سفارش به مرحله آماده‌سازی منتقل شد.']);
    }

    public function label(WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        return view('warehouse::print.label', compact('order'));
    }
}
