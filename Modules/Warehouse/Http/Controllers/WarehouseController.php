<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\WarehouseOrder;
use Modules\Warehouse\Models\WarehouseShippingType;

class WarehouseController extends Controller
{
    public function journey()
    {
        return redirect()->route('warehouse.index');
    }

    public function index(Request $request)
    {
        if (!auth()->user()->can('view-warehouse') && !auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $currentStatus = $request->get('status', 'pending');
        $search = $request->get('search');
        $shippingFilter = $request->get('shipping');

        $statusCounts = WarehouseOrder::getStatusCounts();

        $query = WarehouseOrder::with(['creator', 'assignee', 'items']);

        // فیلتر نوع ارسال
        if (!empty($shippingFilter) && $shippingFilter !== 'all') {
            $query->where('shipping_type', $shippingFilter);
        }

        // اگر سرچ هست، در همه وضعیت‌ها جستجو کن
        if (!empty($search)) {
            $query->search($search)->orderBy('created_at', 'desc');
            $orders = $query->get();
        } else {
            $query->byStatus($currentStatus);

            // اولویت نوع ارسال: پیک → حضوری → پست
            $query->orderByRaw("FIELD(shipping_type, 'courier', 'pickup', 'post', 'emergency') ASC");

            // For pending status, order by timer deadline (urgent first)
            if ($currentStatus === WarehouseOrder::STATUS_PENDING) {
                $query->orderByRaw('timer_deadline IS NULL, timer_deadline ASC');
            } else {
                $query->orderBy('created_at', 'desc');
            }

            // وضعیت‌هایی که صفحه‌بندی نمیخوان - همه رو نشون بده
            $noPaginationStatuses = [
                WarehouseOrder::STATUS_PENDING,
                WarehouseOrder::STATUS_SUPPLY_WAIT,
                WarehouseOrder::STATUS_PREPARING,
                WarehouseOrder::STATUS_PACKED,
            ];

            if (in_array($currentStatus, $noPaginationStatuses)) {
                $orders = $query->get();
            } else {
                $orders = $query->paginate(20)->appends($request->query());
            }
        }

        $shippingTypes = WarehouseShippingType::getActiveTypes();

        return view('warehouse::warehouse.index', compact(
            'orders', 'currentStatus', 'statusCounts', 'search', 'shippingTypes', 'shippingFilter',
        ));
    }

    public function create()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $users = User::where('is_staff', true)->where('is_active', true)->get();
        $shippingTypes = WarehouseShippingType::getActiveTypes();

        return view('warehouse::warehouse.create', compact('users', 'shippingTypes'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_mobile' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'shipping_type' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $validated['order_number'] = WarehouseOrder::generateOrderNumber();
        $validated['barcode'] = WarehouseOrder::generateBarcode();
        $validated['created_by'] = auth()->id();
        $validated['status'] = WarehouseOrder::STATUS_PENDING;

        $order = WarehouseOrder::create($validated);
        $order->setTimerFromShippingType();

        return redirect()->route('warehouse.index')
            ->with('success', 'سفارش با موفقیت ثبت شد.');
    }

    public function show(WarehouseOrder $order)
    {
        if (!auth()->user()->can('view-warehouse') && !auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $order->load(['creator', 'assignee', 'items', 'boxSize']);

        return view('warehouse::warehouse.show', compact('order'));
    }

    public function edit(WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $users = User::where('is_staff', true)->where('is_active', true)->get();
        $shippingTypes = WarehouseShippingType::getActiveTypes();

        return view('warehouse::warehouse.edit', compact('order', 'users', 'shippingTypes'));
    }

    public function update(Request $request, WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_mobile' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'shipping_type' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'tracking_code' => 'nullable|string|max:255',
        ]);

        $order->update($validated);

        return redirect()->route('warehouse.show', $order)
            ->with('success', 'سفارش با موفقیت ویرایش شد.');
    }

    public function updateStatus(Request $request, WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $request->validate([
            'status' => 'required|in:' . implode(',', WarehouseOrder::$statuses),
        ]);

        $order->updateStatus($request->status);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'وضعیت با موفقیت تغییر کرد.']);
        }

        return redirect()->back()->with('success', 'وضعیت با موفقیت تغییر کرد.');
    }

    public function markSupplyWait(Request $request, WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $request->validate([
            'unavailable_items' => 'required|array|min:1',
            'unavailable_items.*' => 'exists:warehouse_order_items,id',
        ]);

        // Mark selected items as unavailable
        $order->items()->whereIn('id', $request->input('unavailable_items'))
            ->update(['is_unavailable' => true]);

        // Change shipping type: post → urgent (فوری), courier → emergency (اضطراری)
        $shippingMap = [
            'post' => 'urgent',
            'courier' => 'emergency',
        ];
        if (isset($shippingMap[$order->shipping_type])) {
            $order->shipping_type = $shippingMap[$order->shipping_type];
        }

        // Update order status
        $order->status = WarehouseOrder::STATUS_SUPPLY_WAIT;
        $order->status_changed_at = now();
        $order->save();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'سفارش به انتظار تامین منتقل شد.']);
        }

        return redirect()->route('warehouse.index', ['status' => 'supply_wait'])
            ->with('success', 'سفارش به انتظار تامین منتقل شد.');
    }

    public function updateShippingType(Request $request, WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'shipping_type' => 'required|string|max:50|exists:warehouse_shipping_types,slug',
        ]);

        $order->update(['shipping_type' => $validated['shipping_type']]);

        $shippingType = WarehouseShippingType::where('slug', $validated['shipping_type'])->first();

        return response()->json([
            'success' => true,
            'shipping_type' => $validated['shipping_type'],
            'shipping_label' => $shippingType ? $shippingType->name : $validated['shipping_type'],
        ]);
    }

    public function bulkUpdateStatus(Request $request)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:warehouse_orders,id',
            'status' => 'required|in:' . implode(',', WarehouseOrder::$statuses),
        ]);

        $orders = WarehouseOrder::whereIn('id', $validated['order_ids'])->get();
        $count = 0;

        foreach ($orders as $order) {
            $order->update([
                'status' => $validated['status'],
                'status_changed_at' => now(),
            ]);
            $count++;
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$count} سفارش به وضعیت «" . WarehouseOrder::statusLabels()[$validated['status']] . "» تغییر کرد.",
            ]);
        }

        return redirect()->back()->with('success', "{$count} سفارش تغییر وضعیت داده شد.");
    }

    /**
     * ذخیره استان و شهر تاپین برای سفارش
     */
    public function saveTapinLocation(Request $request, WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'province_code' => 'required|integer',
            'city_code' => 'required|integer',
            'province_name' => 'nullable|string|max:100',
            'city_name' => 'nullable|string|max:100',
        ]);

        // ذخیره در wc_order_data.tapin
        $wcData = is_array($order->wc_order_data) ? $order->wc_order_data : [];
        $wcData['tapin'] = [
            'province_code' => (int) $validated['province_code'],
            'city_code' => (int) $validated['city_code'],
            'province_name' => $validated['province_name'] ?? '',
            'city_name' => $validated['city_name'] ?? '',
        ];
        $order->update(['wc_order_data' => $wcData]);

        return response()->json([
            'success' => true,
            'message' => 'استان و شهر تاپین ذخیره شد: ' . ($validated['province_name'] ?? '') . ' - ' . ($validated['city_name'] ?? ''),
        ]);
    }

    public function destroy(WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $order->delete();

        return redirect()->route('warehouse.index')
            ->with('success', 'سفارش با موفقیت حذف شد.');
    }
}
