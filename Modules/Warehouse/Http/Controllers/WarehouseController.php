<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\WarehouseOrder;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->can('view-warehouse') && !auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $currentStatus = $request->get('status', 'processing');
        $search = $request->get('search');

        $statusCounts = WarehouseOrder::getStatusCounts();

        $orders = WarehouseOrder::with(['creator', 'assignee'])
            ->byStatus($currentStatus)
            ->search($search)
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->appends($request->query());

        return view('warehouse::warehouse.index', compact(
            'orders',
            'currentStatus',
            'statusCounts',
            'search',
        ));
    }

    public function create()
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $users = User::where('is_staff', true)->where('is_active', true)->get();

        return view('warehouse::warehouse.create', compact('users'));
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
            'notes' => 'nullable|string',
        ]);

        $validated['order_number'] = WarehouseOrder::generateOrderNumber();
        $validated['created_by'] = auth()->id();
        $validated['status'] = WarehouseOrder::STATUS_PROCESSING;

        WarehouseOrder::create($validated);

        return redirect()->route('warehouse.index')
            ->with('success', 'سفارش با موفقیت ثبت شد.');
    }

    public function show(WarehouseOrder $order)
    {
        if (!auth()->user()->can('view-warehouse') && !auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $order->load(['creator', 'assignee']);

        return view('warehouse::warehouse.show', compact('order'));
    }

    public function edit(WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $users = User::where('is_staff', true)->where('is_active', true)->get();

        return view('warehouse::warehouse.edit', compact('order', 'users'));
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

        if ($request->ajax()) {
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
