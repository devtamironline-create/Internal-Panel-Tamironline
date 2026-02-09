<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

    public function scanAndShip(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            return response()->json(['success' => false, 'message' => 'دسترسی ندارید.'], 403);
        }

        $request->validate(['barcode' => 'required|string']);

        $barcode = trim($request->barcode);

        // Search by barcode, order_number, or amadest_barcode
        $order = WarehouseOrder::where('barcode', $barcode)
            ->orWhere('order_number', $barcode)
            ->orWhere('amadest_barcode', $barcode)
            ->orWhere('tracking_code', $barcode)
            ->first();

        if (!$order) {
            // Try numeric-only match (barcode scanners sometimes add/strip prefixes)
            $numericBarcode = preg_replace('/\D/', '', $barcode);
            if ($numericBarcode) {
                $order = WarehouseOrder::where('order_number', 'like', '%' . $numericBarcode)
                    ->orWhere('barcode', 'like', '%' . $numericBarcode)
                    ->first();
            }
        }

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'سفارشی با این بارکد یافت نشد.']);
        }

        if ($order->status === WarehouseOrder::STATUS_SHIPPED) {
            return response()->json([
                'success' => false,
                'message' => 'سفارش ' . $order->order_number . ' قبلا ارسال شده.',
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'status' => $order->status,
                    'status_label' => $order->status_label,
                ],
            ]);
        }

        if ($order->status !== WarehouseOrder::STATUS_PACKED) {
            return response()->json([
                'success' => false,
                'message' => 'سفارش ' . $order->order_number . ' آماده ارسال نیست. وضعیت: ' . $order->status_label,
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'status' => $order->status,
                    'status_label' => $order->status_label,
                ],
            ]);
        }

        // Ship the order
        $order->updateStatus(WarehouseOrder::STATUS_SHIPPED);

        Log::channel('daily')->info('سفارش با اسکن ارسال شد', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'scanned_barcode' => $barcode,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->name,
            'shipped_at' => now()->toDateTimeString(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'سفارش ' . $order->order_number . ' (' . $order->customer_name . ') ارسال شد.',
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'shipping_type' => $order->shipping_type,
                'status' => $order->status,
            ],
        ]);
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
