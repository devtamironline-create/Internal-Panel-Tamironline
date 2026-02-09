<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\WarehouseOrder;
use Modules\Warehouse\Services\AmadestService;

class DispatchController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $tab = $request->get('tab', 'ready');

        if ($tab === 'ready') {
            $orders = WarehouseOrder::with(['creator', 'assignee'])
                ->byStatus(WarehouseOrder::STATUS_PACKED)
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        } elseif ($tab === 'shipped') {
            $orders = WarehouseOrder::with(['creator', 'assignee'])
                ->byStatus(WarehouseOrder::STATUS_SHIPPED)
                ->orderBy('shipped_at', 'desc')
                ->paginate(20);
        } else {
            $orders = WarehouseOrder::with(['creator', 'assignee'])
                ->byStatus(WarehouseOrder::STATUS_DELIVERED)
                ->orderBy('delivered_at', 'desc')
                ->paginate(20);
        }

        $readyCount = WarehouseOrder::byStatus(WarehouseOrder::STATUS_PACKED)->count();
        $shippedCount = WarehouseOrder::byStatus(WarehouseOrder::STATUS_SHIPPED)->count();

        return view('warehouse::dispatch.index', compact('orders', 'tab', 'readyCount', 'shippedCount'));
    }

    public function shipViaPost(Request $request, WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید.'], 403);
        }

        try {
            $amadest = new AmadestService();

            if ($amadest->isConfigured()) {
                $result = $amadest->createShipment([
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'customer_mobile' => $order->customer_mobile,
                    'weight' => $order->actual_weight ?? $order->total_weight,
                ]);

                if ($result['success'] ?? false) {
                    $data = $result['data'] ?? [];
                    // کد رهگیری آمادست
                    if (!empty($data['tracking_code'])) {
                        $order->tracking_code = $data['tracking_code'];
                    }
                    // بارکد آماده
                    if (!empty($data['barcode'])) {
                        $order->amadest_barcode = $data['barcode'];
                    }
                    // کد رهگیری پست
                    $postCode = $data['post_tracking_code'] ?? $data['courier_tracking_code'] ?? null;
                    if (!empty($postCode)) {
                        $order->post_tracking_code = $postCode;
                    }
                    $order->save();
                }
            }

            $order->updateStatus(WarehouseOrder::STATUS_SHIPPED);

            return response()->json(['success' => true, 'message' => 'سفارش از طریق پست ارسال شد.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'خطا: ' . $e->getMessage()]);
        }
    }

    public function shipViaCourier(Request $request, WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید.'], 403);
        }

        $request->validate([
            'driver_name' => 'required|string|max:255',
            'driver_phone' => 'nullable|string|max:20',
        ]);

        $order->driver_name = $request->driver_name;
        $order->driver_phone = $request->driver_phone;
        $order->save();
        $order->updateStatus(WarehouseOrder::STATUS_SHIPPED);

        return response()->json(['success' => true, 'message' => 'سفارش به پیک تخصیص داده شد.']);
    }

    public function markDelivered(WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید.'], 403);
        }

        $order->updateStatus(WarehouseOrder::STATUS_DELIVERED);

        return response()->json(['success' => true, 'message' => 'سفارش تحویل داده شد.']);
    }

    public function markReturned(Request $request, WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید.'], 403);
        }

        $order->updateStatus(WarehouseOrder::STATUS_RETURNED);
        if ($request->notes) {
            $order->notes = ($order->notes ? $order->notes . "\n" : '') . 'مرجوعی: ' . $request->notes;
            $order->save();
        }

        return response()->json(['success' => true, 'message' => 'سفارش مرجوعی شد.']);
    }
}
