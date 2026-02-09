<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\WarehouseOrder;
use Modules\Warehouse\Models\WarehouseShippingType;

class WarehouseController extends Controller
{
    public function journey(Request $request)
    {
        if (!auth()->user()->can('view-warehouse') && !auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $search = $request->get('search');

        $orders = WarehouseOrder::with(['creator', 'assignee', 'items'])
            ->byStatus(WarehouseOrder::STATUS_PENDING)
            ->search($search)
            ->orderByRaw('timer_deadline IS NULL, timer_deadline ASC')
            ->paginate(20)
            ->appends($request->query());

        $shippingTypes = WarehouseShippingType::getActiveTypes();
        $pendingCount = WarehouseOrder::byStatus(WarehouseOrder::STATUS_PENDING)->count();

        return view('warehouse::warehouse.journey', compact(
            'orders', 'search', 'shippingTypes', 'pendingCount',
        ));
    }

    public function index(Request $request)
    {
        if (!auth()->user()->can('view-warehouse') && !auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $currentStatus = $request->get('status', 'pending');
        $search = $request->get('search');

        $statusCounts = WarehouseOrder::getStatusCounts();

        $query = WarehouseOrder::with(['creator', 'assignee', 'items'])
            ->byStatus($currentStatus)
            ->search($search);

        // For pending status, order by timer deadline (urgent first)
        if ($currentStatus === WarehouseOrder::STATUS_PENDING) {
            $query->orderByRaw('timer_deadline IS NULL, timer_deadline ASC');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $orders = $query->paginate(20)->appends($request->query());

        $shippingTypes = WarehouseShippingType::getActiveTypes();

        return view('warehouse::warehouse.index', compact(
            'orders', 'currentStatus', 'statusCounts', 'search', 'shippingTypes',
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

        $order->load(['creator', 'assignee', 'items']);

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
