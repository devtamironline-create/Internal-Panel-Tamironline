<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Warehouse\Models\WooOrder;

class CourierController extends Controller
{
    /**
     * Display courier manager panel
     */
    public function index()
    {
        // Get pending orders (packed but not shipped, with courier shipping method)
        $pendingOrders = WooOrder::where('is_packed', true)
            ->where(function ($q) {
                $q->where('is_shipped', false)
                  ->orWhereNull('is_shipped');
            })
            ->whereNull('courier_name')
            ->where(function ($q) {
                // Filter for courier shipping method (not post)
                $q->where('shipping_method', 'like', '%پیک%')
                  ->orWhere('shipping_method', 'like', '%courier%')
                  ->orWhere('shipping_method', 'like', '%فوری%')
                  ->orWhere('shipping_method', 'like', '%express%');
            })
            ->orderBy('date_created', 'asc')
            ->get();

        // Get couriers from settings
        $couriers = $this->getCouriers();

        // Get assigned today
        $assignedToday = WooOrder::whereNotNull('courier_name')
            ->whereDate('courier_assigned_at', today())
            ->orderByDesc('courier_assigned_at')
            ->get();

        // Stats
        $stats = [
            'pending' => $pendingOrders->count(),
            'assigned' => WooOrder::whereNotNull('courier_name')
                ->where('internal_status', '!=', WooOrder::INTERNAL_DELIVERED)
                ->where('is_shipped', true)
                ->count(),
            'delivered_today' => WooOrder::where('internal_status', WooOrder::INTERNAL_DELIVERED)
                ->whereDate('updated_at', today())
                ->count(),
        ];

        // Add active orders count to each courier
        $couriers = collect($couriers)->map(function ($courier) {
            $courier['active_orders'] = WooOrder::where('courier_mobile', $courier['mobile'])
                ->where('is_shipped', true)
                ->where('internal_status', '!=', WooOrder::INTERNAL_DELIVERED)
                ->count();
            return $courier;
        })->toArray();

        return view('warehouse::courier.index', compact(
            'pendingOrders',
            'couriers',
            'assignedToday',
            'stats'
        ));
    }

    /**
     * Store a new courier
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'mobile' => 'required|string|regex:/^09[0-9]{9}$/',
        ]);

        $couriers = $this->getCouriers();

        // Check if courier already exists
        $exists = collect($couriers)->contains('mobile', $request->mobile);
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'این شماره موبایل قبلاً ثبت شده است',
            ]);
        }

        $couriers[] = [
            'name' => $request->name,
            'mobile' => $request->mobile,
        ];

        Setting::set('warehouse_couriers', $couriers);

        return response()->json([
            'success' => true,
            'message' => 'پیک جدید اضافه شد',
        ]);
    }

    /**
     * Delete a courier
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'mobile' => 'required|string',
        ]);

        $couriers = $this->getCouriers();
        $couriers = collect($couriers)->reject(function ($courier) use ($request) {
            return $courier['mobile'] === $request->mobile;
        })->values()->toArray();

        Setting::set('warehouse_couriers', $couriers);

        return response()->json([
            'success' => true,
            'message' => 'پیک حذف شد',
        ]);
    }

    /**
     * Mark order as delivered
     */
    public function markDelivered(WooOrder $order): JsonResponse
    {
        $order->update([
            'internal_status' => WooOrder::INTERNAL_DELIVERED,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'سفارش به عنوان تحویل داده شده علامت‌گذاری شد',
        ]);
    }

    /**
     * Get list of couriers from settings
     */
    protected function getCouriers(): array
    {
        return Setting::get('warehouse_couriers', []);
    }
}
