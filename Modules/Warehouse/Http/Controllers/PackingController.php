<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Warehouse\Models\WooOrder;
use Modules\Warehouse\Models\WooOrderItem;

class PackingController extends Controller
{
    /**
     * Display preparation queue with smart timers
     */
    public function queue()
    {
        $settings = $this->getQueueSettings();

        // Get orders in queue (processing status, not yet packed)
        $ordersInQueue = WooOrder::where('status', WooOrder::STATUS_PROCESSING)
            ->where(function ($q) {
                $q->where('is_packed', false)
                  ->orWhereNull('is_packed');
            })
            ->orderBy('date_created', 'asc')
            ->get();

        // Separate by shipping type
        $postOrders = $ordersInQueue->filter(function ($order) {
            return $this->isPostShipping($order);
        });

        $courierOrders = $ordersInQueue->filter(function ($order) {
            return !$this->isPostShipping($order);
        });

        // Calculate overdue orders
        $overdueCount = 0;
        $now = now();

        foreach ($postOrders as $order) {
            if ($order->date_created && $order->date_created->diffInMinutes($now) > $settings['post_deadline_minutes']) {
                $overdueCount++;
            }
        }

        foreach ($courierOrders as $order) {
            if ($order->date_created && $order->date_created->diffInMinutes($now) > $settings['courier_deadline_minutes']) {
                $overdueCount++;
            }
        }

        $stats = [
            'total_in_queue' => $ordersInQueue->count(),
            'overdue' => $overdueCount,
            'post_orders' => $postOrders->count(),
            'courier_orders' => $courierOrders->count(),
        ];

        return view('warehouse::orders.queue', compact(
            'postOrders',
            'courierOrders',
            'stats',
            'settings'
        ));
    }

    /**
     * Display packing station
     */
    public function index()
    {
        $settings = [
            'weight_tolerance_percent' => Setting::get('weight_tolerance_percent', 10),
            'default_carton_weight' => Setting::get('default_carton_weight', 0),
        ];

        $recentPackedOrders = WooOrder::where('is_packed', true)
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return view('warehouse::packing.index', compact('settings', 'recentPackedOrders'));
    }

    /**
     * Process barcode scan
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'barcode' => 'required|string',
            'order_id' => 'nullable|integer',
        ]);

        $barcode = trim($request->barcode);
        $currentOrderId = $request->order_id;

        // If no current order, try to find order by barcode/order number
        if (!$currentOrderId) {
            return $this->scanForOrder($barcode);
        }

        // Otherwise, scan for product
        return $this->scanForProduct($barcode, $currentOrderId);
    }

    /**
     * Scan to find/load an order
     */
    protected function scanForOrder(string $barcode): JsonResponse
    {
        // Try to find order by order number or barcode
        $order = WooOrder::where('order_number', $barcode)
            ->orWhere('woo_order_id', $barcode)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'سفارش با این بارکد یافت نشد',
            ]);
        }

        if ($order->is_packed) {
            return response()->json([
                'success' => false,
                'message' => 'این سفارش قبلاً بسته‌بندی شده است',
            ]);
        }

        $items = $order->items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'bin_location' => $item->bin_location,
                'product_id' => $item->product_id,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'سفارش بارگذاری شد',
            'type' => 'order',
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_full_name,
                'items_count' => $order->items->count(),
                'total' => $order->formatted_total,
            ],
            'items' => $items,
        ]);
    }

    /**
     * Scan a product barcode within an order
     */
    protected function scanForProduct(string $barcode, int $orderId): JsonResponse
    {
        $order = WooOrder::find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'سفارش یافت نشد',
            ]);
        }

        // Find item by SKU or product_id
        $item = $order->items()
            ->where(function ($q) use ($barcode) {
                $q->where('sku', $barcode)
                  ->orWhere('product_id', $barcode);
            })
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'این محصول در سفارش موجود نیست',
            ]);
        }

        // Check if already fully scanned
        $scannedQty = $item->picked_quantity ?? 0;
        if ($scannedQty >= $item->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'این محصول قبلاً اسکن شده است',
            ]);
        }

        // Increment picked quantity
        $item->increment('picked_quantity');

        return response()->json([
            'success' => true,
            'message' => 'محصول اسکن شد: ' . $item->name,
            'type' => 'product',
            'item_id' => $item->id,
            'scanned_quantity' => $item->picked_quantity,
            'total_quantity' => $item->quantity,
        ]);
    }

    /**
     * Complete order packing
     */
    public function complete(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|integer|exists:woo_orders,id',
            'package_weight' => 'required|numeric|min:0',
            'force' => 'boolean',
            'items' => 'array',
        ]);

        $order = WooOrder::findOrFail($request->order_id);

        // Set package weight
        $order->setPackageWeight($request->package_weight);
        $order->refresh();

        $tolerance = (float) Setting::get('weight_tolerance_percent', 10);
        $isWithinTolerance = $order->isWeightWithinTolerance();

        // If weight is not within tolerance and not forcing
        if (!$isWithinTolerance && !$request->boolean('force')) {
            return response()->json([
                'success' => true,
                'weight_warning' => true,
                'expected_weight' => $order->getExpectedWeight(),
                'actual_weight' => $request->package_weight,
                'difference_percent' => round($order->weight_difference_percent, 1),
                'message' => 'وزن بسته خارج از محدوده مجاز است',
            ]);
        }

        // Mark items as picked
        if ($request->has('items')) {
            foreach ($request->items as $itemData) {
                $item = WooOrderItem::find($itemData['id']);
                if ($item && $item->woo_order_id === $order->id) {
                    $item->update([
                        'is_picked' => true,
                        'picked_quantity' => $itemData['scanned_quantity'] ?? $item->quantity,
                    ]);
                }
            }
        }

        // Mark order as packed and change status
        $order->markAsPacked();

        // Auto change internal status to 'picking' -> 'packed'
        if ($order->internal_status === WooOrder::INTERNAL_PICKING || $order->internal_status === WooOrder::INTERNAL_NEW) {
            $order->updateInternalStatus(WooOrder::INTERNAL_PACKED);
        }

        return response()->json([
            'success' => true,
            'message' => 'بسته‌بندی سفارش تکمیل شد',
            'weight_verified' => $isWithinTolerance,
        ]);
    }

    /**
     * Get queue settings
     */
    protected function getQueueSettings(): array
    {
        return [
            'post_deadline_minutes' => (int) Setting::get('queue_post_deadline_minutes', 60), // 1 hour default
            'courier_deadline_minutes' => (int) Setting::get('queue_courier_deadline_minutes', 420), // 7 hours default
        ];
    }

    /**
     * Check if order is for post shipping (vs courier)
     */
    protected function isPostShipping(WooOrder $order): bool
    {
        $shippingMethod = strtolower($order->shipping_method ?? '');

        // Check common post shipping keywords
        $postKeywords = ['پست', 'post', 'پستی', 'pishtaz', 'پیشتاز', 'sefareshi', 'سفارشی', 'amadast', 'آمادست'];

        foreach ($postKeywords as $keyword) {
            if (str_contains($shippingMethod, $keyword)) {
                return true;
            }
        }

        // Default to post if no courier keywords found
        $courierKeywords = ['پیک', 'courier', 'bike', 'express', 'فوری', 'ارسال فوری'];

        foreach ($courierKeywords as $keyword) {
            if (str_contains($shippingMethod, $keyword)) {
                return false;
            }
        }

        return true; // Default to post
    }
}
