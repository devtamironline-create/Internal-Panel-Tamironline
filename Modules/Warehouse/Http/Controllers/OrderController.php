<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Warehouse\Models\WooOrder;
use Modules\Warehouse\Models\WooSyncLog;
use Modules\Warehouse\Services\WooCommerceService;

class OrderController extends Controller
{
    protected WooCommerceService $wooService;

    public function __construct(WooCommerceService $wooService)
    {
        $this->wooService = $wooService;
    }

    /**
     * Display dashboard with order statistics
     */
    public function dashboard()
    {
        $stats = [
            'total' => WooOrder::count(),
            'processing' => WooOrder::status(WooOrder::STATUS_PROCESSING)->count(),
            'pending' => WooOrder::status(WooOrder::STATUS_PENDING)->count(),
            'completed' => WooOrder::status(WooOrder::STATUS_COMPLETED)->count(),
            'on_hold' => WooOrder::status(WooOrder::STATUS_ON_HOLD)->count(),
            'not_shipped' => WooOrder::notShipped()->whereIn('status', [WooOrder::STATUS_PROCESSING, WooOrder::STATUS_COMPLETED])->count(),
        ];

        $internalStats = [
            'new' => WooOrder::internalStatus(WooOrder::INTERNAL_NEW)->count(),
            'confirmed' => WooOrder::internalStatus(WooOrder::INTERNAL_CONFIRMED)->count(),
            'picking' => WooOrder::internalStatus(WooOrder::INTERNAL_PICKING)->count(),
            'packed' => WooOrder::internalStatus(WooOrder::INTERNAL_PACKED)->count(),
            'shipped' => WooOrder::internalStatus(WooOrder::INTERNAL_SHIPPED)->count(),
        ];

        $recentOrders = WooOrder::with('items')
            ->orderByDesc('date_created')
            ->limit(10)
            ->get();

        $recentSyncs = WooSyncLog::with('user')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $isConfigured = $this->wooService->isConfigured();

        return view('warehouse::orders.dashboard', compact(
            'stats',
            'internalStats',
            'recentOrders',
            'recentSyncs',
            'isConfigured'
        ));
    }

    /**
     * Display list of orders
     */
    public function index(Request $request)
    {
        $query = WooOrder::with(['items', 'assignedUser']);

        // Filters
        if ($request->filled('status')) {
            $query->status($request->status);
        }

        if ($request->filled('internal_status')) {
            $query->internalStatus($request->internal_status);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('assigned_to')) {
            $query->assignedTo($request->assigned_to);
        }

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $query->dateRange($request->date_from, $request->date_to);
        }

        if ($request->filled('is_shipped')) {
            $query->where('is_shipped', $request->is_shipped === 'yes');
        }

        // Sorting
        $sortField = $request->get('sort', 'date_created');
        $sortDir = $request->get('dir', 'desc');
        $query->orderBy($sortField, $sortDir);

        $orders = $query->paginate(config('warehouse.orders.per_page', 20));

        // Get new orders for the sidebar queue
        $newOrders = WooOrder::with('items')
            ->whereIn('internal_status', [WooOrder::INTERNAL_NEW, null])
            ->whereIn('status', [WooOrder::STATUS_PROCESSING, WooOrder::STATUS_PENDING, WooOrder::STATUS_ON_HOLD])
            ->where('is_shipped', false)
            ->orderByDesc('date_created')
            ->limit(20)
            ->get();

        $statuses = WooOrder::getStatuses();
        $internalStatuses = WooOrder::getInternalStatuses();
        $staff = User::staff()->active()->get();

        return view('warehouse::orders.index', compact(
            'orders',
            'newOrders',
            'statuses',
            'internalStatuses',
            'staff'
        ));
    }

    /**
     * Display single order details
     */
    public function show(WooOrder $order)
    {
        $order->load(['items', 'assignedUser']);
        $statuses = WooOrder::getStatuses();
        $internalStatuses = WooOrder::getInternalStatuses();
        $staff = User::staff()->active()->get();

        return view('warehouse::orders.show', compact(
            'order',
            'statuses',
            'internalStatuses',
            'staff'
        ));
    }

    /**
     * Sync orders from WooCommerce
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            $result = $this->wooService->syncOrders(auth()->id());

            return response()->json([
                'success' => true,
                'message' => "همگام‌سازی انجام شد. {$result['created']} سفارش جدید، {$result['updated']} بروزرسانی شد.",
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در همگام‌سازی: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync recent orders (last 7 days)
     */
    public function syncRecent(Request $request): JsonResponse
    {
        try {
            $days = $request->get('days', 7);
            $result = $this->wooService->syncRecentOrders(auth()->id(), $days);

            return response()->json([
                'success' => true,
                'message' => "همگام‌سازی انجام شد. {$result['created']} سفارش جدید، {$result['updated']} بروزرسانی شد.",
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در همگام‌سازی: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync single order from WooCommerce
     */
    public function syncOrder(WooOrder $order): JsonResponse
    {
        try {
            $result = $this->wooService->syncOrder($order->woo_order_id, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'سفارش بروزرسانی شد.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در بروزرسانی: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update order status (WooCommerce status)
     */
    public function updateStatus(Request $request, WooOrder $order): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:' . implode(',', array_keys(WooOrder::getStatuses())),
        ]);

        try {
            $result = $this->wooService->updateStatus($order, $request->status, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'وضعیت سفارش بروزرسانی شد.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در بروزرسانی: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update internal status
     */
    public function updateInternalStatus(Request $request, WooOrder $order): JsonResponse
    {
        $request->validate([
            'internal_status' => 'required|string|in:' . implode(',', array_keys(WooOrder::getInternalStatuses())),
        ]);

        $order->updateInternalStatus($request->internal_status);

        return response()->json([
            'success' => true,
            'message' => 'وضعیت داخلی بروزرسانی شد.',
            'order' => $order->fresh(),
        ]);
    }

    /**
     * Assign order to staff
     */
    public function assign(Request $request, WooOrder $order): JsonResponse
    {
        $request->validate([
            'user_id' => 'nullable|exists:users,id',
        ]);

        $order->update(['assigned_to' => $request->user_id]);

        return response()->json([
            'success' => true,
            'message' => $request->user_id ? 'سفارش تخصیص داده شد.' : 'تخصیص سفارش لغو شد.',
            'order' => $order->fresh()->load('assignedUser'),
        ]);
    }

    /**
     * Update internal note
     */
    public function updateNote(Request $request, WooOrder $order): JsonResponse
    {
        $request->validate([
            'note' => 'nullable|string|max:2000',
        ]);

        $order->update(['internal_note' => $request->note]);

        return response()->json([
            'success' => true,
            'message' => 'یادداشت ذخیره شد.',
        ]);
    }

    /**
     * Mark order as packed
     */
    public function markPacked(WooOrder $order): JsonResponse
    {
        $order->markAsPacked();

        return response()->json([
            'success' => true,
            'message' => 'سفارش به عنوان بسته‌بندی شده علامت‌گذاری شد.',
            'order' => $order->fresh(),
        ]);
    }

    /**
     * Mark order as shipped
     */
    public function markShipped(Request $request, WooOrder $order): JsonResponse
    {
        $request->validate([
            'tracking_code' => 'required|string|max:100',
            'shipping_carrier' => 'nullable|string|max:100',
        ]);

        $order->markAsShipped($request->tracking_code, $request->shipping_carrier);

        return response()->json([
            'success' => true,
            'message' => 'سفارش به عنوان ارسال شده علامت‌گذاری شد.',
            'order' => $order->fresh(),
        ]);
    }

    /**
     * Mark order as printed
     */
    public function markPrinted(WooOrder $order): JsonResponse
    {
        $order->markAsPrinted();

        return response()->json([
            'success' => true,
            'message' => 'سفارش به عنوان چاپ شده علامت‌گذاری شد.',
        ]);
    }

    /**
     * Check print status before printing (for warning)
     */
    public function checkPrintStatus(Request $request, WooOrder $order): JsonResponse
    {
        $printType = $request->get('print_type', 'invoice');
        $userId = auth()->id();

        $existingCount = \Modules\Warehouse\Models\OrderPrintLog::getUserPrintCount(
            $order->id,
            $userId,
            $printType
        );

        return response()->json([
            'success' => true,
            'has_printed_before' => $existingCount > 0,
            'print_count' => $existingCount,
            'show_warning' => $existingCount > 0 && $existingCount < 2,
            'message' => $existingCount > 0
                ? "شما قبلاً {$existingCount} بار این سفارش را پرینت کرده‌اید."
                : null,
        ]);
    }

    /**
     * Print order (show printable view)
     */
    public function print(Request $request, WooOrder $order)
    {
        $order->load('items');

        // Record print and get duplicate info
        $printResult = $order->recordPrint(
            auth()->id(),
            'invoice',
            $request->ip(),
            $request->userAgent()
        );

        // Auto change status on first print
        if (!$printResult['is_duplicate']) {
            $this->autoChangeStatusOnPrint($order);
        }

        return view('warehouse::orders.print', compact('order', 'printResult'));
    }

    /**
     * Auto change order status when printed
     */
    protected function autoChangeStatusOnPrint(WooOrder $order): void
    {
        $autoChange = \App\Models\Setting::get('auto_status_on_print', true);
        if (!$autoChange) {
            return;
        }

        $newStatus = \App\Models\Setting::get('print_status_change_to', 'picking');

        // Only change if current status is 'new'
        if ($order->internal_status === WooOrder::INTERNAL_NEW || !$order->internal_status) {
            $order->updateInternalStatus($newStatus);
        }
    }

    /**
     * Print Amadast shipping label with tracking codes and barcodes
     */
    public function printAmadast(Request $request, WooOrder $order)
    {
        $order->load('items');

        // Try to fetch latest tracking info from Amadast
        if ($order->amadast_order_id && !$order->courier_tracking_code) {
            $order->updateAmadastTracking();
            $order->refresh();
        }

        // Record print and get duplicate info
        $printResult = $order->recordPrint(
            auth()->id(),
            'amadast',
            $request->ip(),
            $request->userAgent()
        );

        return view('warehouse::orders.print-amadast', compact('order', 'printResult'));
    }

    /**
     * Update package weight
     */
    public function updateWeight(Request $request, WooOrder $order): JsonResponse
    {
        $request->validate([
            'package_weight' => 'required|numeric|min:0',
            'carton_weight' => 'nullable|numeric|min:0',
        ]);

        // Update carton weight if provided
        if ($request->filled('carton_weight')) {
            $order->update(['carton_weight' => $request->carton_weight]);
        }

        // Set package weight and calculate difference
        $order->setPackageWeight($request->package_weight);
        $order->refresh();

        $isWithinTolerance = $order->isWeightWithinTolerance();
        $expectedWeight = $order->getExpectedWeight();

        return response()->json([
            'success' => true,
            'message' => $isWithinTolerance
                ? 'وزن ثبت شد و در محدوده مجاز است.'
                : 'وزن ثبت شد اما خارج از محدوده مجاز است!',
            'data' => [
                'package_weight' => $order->package_weight,
                'expected_weight' => $expectedWeight,
                'difference_percent' => $order->weight_difference_percent,
                'is_within_tolerance' => $isWithinTolerance,
                'weight_verified' => $order->weight_verified,
            ],
        ]);
    }

    /**
     * Assign courier to order
     */
    public function assignCourier(Request $request, WooOrder $order): JsonResponse
    {
        $request->validate([
            'courier_name' => 'required|string|max:100',
            'courier_mobile' => 'required|string|regex:/^09[0-9]{9}$/',
            'notify_customer' => 'boolean',
        ]);

        $result = $order->assignCourier(
            $request->courier_name,
            $request->courier_mobile,
            $request->boolean('notify_customer', true)
        );

        return response()->json([
            'success' => true,
            'message' => 'اطلاعات پیک ثبت شد.',
            'customer_notified' => $result['customer_notified'],
            'order' => $order->fresh(),
        ]);
    }

    /**
     * Get print logs for an order
     */
    public function getPrintLogs(WooOrder $order): JsonResponse
    {
        $logs = $order->printLogs()
            ->with('user')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user' => $log->user->full_name ?? 'کاربر ناشناس',
                    'print_type' => $log->print_type_label,
                    'was_duplicate' => $log->was_duplicate,
                    'manager_notified' => $log->manager_notified,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'created_at_jalali' => jdate($log->created_at)->format('Y/m/d H:i'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $logs,
            'total_count' => $logs->count(),
        ]);
    }

    /**
     * Bulk update orders
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'exists:woo_orders,id',
            'action' => 'required|string|in:mark_packed,mark_printed,update_internal_status,assign',
            'value' => 'nullable|string',
        ]);

        $orders = WooOrder::whereIn('id', $request->order_ids)->get();
        $updated = 0;

        foreach ($orders as $order) {
            switch ($request->action) {
                case 'mark_packed':
                    $order->markAsPacked();
                    $updated++;
                    break;
                case 'mark_printed':
                    $order->markAsPrinted();
                    $updated++;
                    break;
                case 'update_internal_status':
                    if ($request->value && array_key_exists($request->value, WooOrder::getInternalStatuses())) {
                        $order->updateInternalStatus($request->value);
                        $updated++;
                    }
                    break;
                case 'assign':
                    $order->update(['assigned_to' => $request->value ?: null]);
                    $updated++;
                    break;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$updated} سفارش بروزرسانی شد.",
            'updated' => $updated,
        ]);
    }

    /**
     * Test WooCommerce connection
     */
    public function testConnection(): JsonResponse
    {
        $result = $this->wooService->testConnection();

        return response()->json($result);
    }

    /**
     * Get sync logs
     */
    public function syncLogs(Request $request)
    {
        $logs = WooSyncLog::with('user')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('warehouse::orders.sync-logs', compact('logs'));
    }

    /**
     * Send order to Amadast
     */
    public function sendToAmadast(WooOrder $order): JsonResponse
    {
        $result = $order->sendToAmadast();

        return response()->json($result);
    }

    /**
     * Update Amadast tracking info
     */
    public function updateAmadastTracking(WooOrder $order): JsonResponse
    {
        $success = $order->updateAmadastTracking();

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'اطلاعات رهگیری بروزرسانی شد',
                'data' => [
                    'amadast_tracking_code' => $order->amadast_tracking_code,
                    'courier_tracking_code' => $order->courier_tracking_code,
                    'courier_title' => $order->courier_title,
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'خطا در دریافت اطلاعات رهگیری'
        ]);
    }

    /**
     * Get orders for floating panel (API endpoint)
     */
    public function floatingOrders(): JsonResponse
    {
        // Get active orders (not shipped, not cancelled)
        $orders = WooOrder::with('items')
            ->whereIn('status', [
                WooOrder::STATUS_PROCESSING,
                WooOrder::STATUS_PENDING,
                WooOrder::STATUS_ON_HOLD,
            ])
            ->whereIn('internal_status', [
                WooOrder::INTERNAL_NEW,
                WooOrder::INTERNAL_CONFIRMED,
                WooOrder::INTERNAL_PICKING,
                WooOrder::INTERNAL_PACKED,
            ])
            ->where('is_shipped', false)
            ->orderByDesc('date_created')
            ->limit(50)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_full_name,
                    'items_count' => $order->items_count,
                    'total' => $order->formatted_total,
                    'status' => $order->status,
                    'status_label' => $order->status_label,
                    'internal_status' => $order->internal_status,
                    'internal_status_label' => $order->internal_status_label,
                    'is_printed' => $order->is_printed,
                    'is_packed' => $order->is_packed,
                    'date' => $order->date_created?->format('m/d H:i'),
                ];
            });

        // Count by internal status
        $counts = [
            'pending' => WooOrder::whereIn('internal_status', [WooOrder::INTERNAL_NEW, WooOrder::INTERNAL_CONFIRMED])
                ->where('is_shipped', false)
                ->whereIn('status', [WooOrder::STATUS_PROCESSING, WooOrder::STATUS_PENDING, WooOrder::STATUS_ON_HOLD])
                ->count(),
            'processing' => WooOrder::whereIn('internal_status', [WooOrder::INTERNAL_PICKING, WooOrder::INTERNAL_PACKED])
                ->whereNot('internal_status', WooOrder::INTERNAL_PACKED)
                ->where('is_shipped', false)
                ->whereIn('status', [WooOrder::STATUS_PROCESSING, WooOrder::STATUS_PENDING, WooOrder::STATUS_ON_HOLD])
                ->count(),
            'packed' => WooOrder::where('internal_status', WooOrder::INTERNAL_PACKED)
                ->where('is_shipped', false)
                ->whereIn('status', [WooOrder::STATUS_PROCESSING, WooOrder::STATUS_PENDING, WooOrder::STATUS_ON_HOLD])
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'orders' => $orders,
            'counts' => $counts,
        ]);
    }
}
