<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Warehouse\Models\OrderLog;
use Modules\Warehouse\Models\WarehouseBoxSize;
use Modules\Warehouse\Models\WarehouseOrder;
use Modules\Warehouse\Models\WarehouseSetting;
use Modules\Warehouse\Models\WarehouseShippingType;

class WarehouseController extends Controller
{
    public function journey()
    {
        return redirect()->route('warehouse.index');
    }

    public function quickSearch(Request $request)
    {
        $search = $request->get('q');
        if (!$search || mb_strlen($search) < 2) {
            return response()->json(['results' => []]);
        }

        $orders = WarehouseOrder::with(['items'])
            ->search($search)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'customer_mobile' => $order->customer_mobile,
                    'status' => $order->status,
                    'status_label' => $order->status_label,
                    'status_color' => $order->status_color,
                    'items_count' => $order->items->count(),
                    'tracking_code' => $order->tracking_code,
                    'shipping_type' => $order->shipping_type,
                    'url' => route('warehouse.show', $order->id),
                ];
            });

        return response()->json(['results' => $orders]);
    }

    public function index(Request $request)
    {
        if (!auth()->user()->can('view-warehouse') && !auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $currentStatus = $request->get('status', 'pending');
        // preparing حذف شده - به pending ریدایرکت کن
        if ($currentStatus === 'preparing') {
            return redirect()->route('warehouse.index', ['status' => 'pending']);
        }
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
            $query->search($search)->orderBy('created_at', 'asc');
            $orders = $query->get();
        } else {
            $query->byStatus($currentStatus);

            // اولویت نوع ارسال: پیک فوری → فوری → حضوری → پیک ۵ روزه → پست → بقیه
            $query->orderByRaw("
                CASE
                    WHEN shipping_type = 'emergency' THEN 0
                    WHEN shipping_type = 'urgent' THEN 1
                    WHEN shipping_type = 'pickup' THEN 2
                    WHEN shipping_type = 'courier' THEN 3
                    WHEN shipping_type = 'post' THEN 4
                    ELSE 5
                END ASC
            ");

            // پیک فوری: قدیمی‌ترین اول (FIFO) — بقیه: جدیدترین اول
            $query->orderByRaw("
                CASE
                    WHEN shipping_type IN ('emergency', 'urgent') THEN UNIX_TIMESTAMP(created_at)
                    ELSE -UNIX_TIMESTAMP(created_at)
                END ASC
            ");

            // وضعیت‌هایی که صفحه‌بندی نمیخوان - همه رو نشون بده
            $noPaginationStatuses = [
                WarehouseOrder::STATUS_PENDING,
                WarehouseOrder::STATUS_SUPPLY_WAIT,
                WarehouseOrder::STATUS_PACKED,
            ];

            if (in_array($currentStatus, $noPaginationStatuses)) {
                $orders = $query->get();
            } else {
                $orders = $query->paginate(20)->appends($request->query());
            }
        }

        $shippingTypes = WarehouseShippingType::getActiveTypes();
        $boxSizes = WarehouseBoxSize::active()->ordered()->get();

        return view('warehouse::warehouse.index', compact(
            'orders', 'currentStatus', 'statusCounts', 'search', 'shippingTypes', 'shippingFilter', 'boxSizes',
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

        OrderLog::log($order, OrderLog::ACTION_CREATED, 'سفارش ایجاد شد');

        return redirect()->route('warehouse.index')
            ->with('success', 'سفارش با موفقیت ثبت شد.');
    }

    public function show(WarehouseOrder $order)
    {
        if (!auth()->user()->can('view-warehouse') && !auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $order->load(['creator', 'assignee', 'items', 'boxSize', 'logs' => fn($q) => $q->with('user')->orderByDesc('created_at')]);

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

        OrderLog::log($order, OrderLog::ACTION_EDITED, 'سفارش ویرایش شد');

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

        $oldStatus = $order->status;

        // وقتی از آماده ارسال برمیگرده به پردازش، بارکدهای ارسال رو پاک کن
        if ($oldStatus === WarehouseOrder::STATUS_PACKED && $request->status === WarehouseOrder::STATUS_PENDING) {
            $oldBarcode = $order->amadest_barcode;
            $order->amadest_barcode = null;
            $order->post_tracking_code = null;
            // فلگ تاپین رو هم پاک کن
            $wcData = is_array($order->wc_order_data) ? $order->wc_order_data : [];
            if (isset($wcData['tapin']['registered'])) {
                unset($wcData['tapin']['registered']);
                $order->wc_order_data = $wcData;
            }
            $order->save();

            OrderLog::log($order, OrderLog::ACTION_STATUS_CHANGED, 'بارکد ارسال پاک شد: ' . ($oldBarcode ?? '—'), [
                'cleared_barcode' => $oldBarcode,
            ]);
        }

        $order->updateStatus($request->status);

        $statusLabels = WarehouseOrder::statusLabels();
        OrderLog::log($order, OrderLog::ACTION_STATUS_CHANGED,
            'تغییر وضعیت: ' . ($statusLabels[$oldStatus] ?? $oldStatus) . ' → ' . ($statusLabels[$request->status] ?? $request->status),
            ['from' => $oldStatus, 'to' => $request->status]
        );

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

        // سینک وضعیت به ووکامرس
        $order->syncStatusToWc(WarehouseOrder::STATUS_SUPPLY_WAIT);

        $unavailableNames = $order->items()->whereIn('id', $request->input('unavailable_items'))->pluck('product_name')->implode('، ');
        OrderLog::log($order, OrderLog::ACTION_SUPPLY_WAIT, 'منتقل به انتظار تامین — کالاهای ناموجود: ' . $unavailableNames);

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
            // سینک وضعیت به ووکامرس
            $order->syncStatusToWc($validated['status']);
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

        OrderLog::log($order, OrderLog::ACTION_TAPIN_LOCATION, 'تنظیم تاپین: ' . ($validated['province_name'] ?? '') . ' — ' . ($validated['city_name'] ?? ''));

        return response()->json([
            'success' => true,
            'message' => 'استان و شهر تاپین ذخیره شد: ' . ($validated['province_name'] ?? '') . ' - ' . ($validated['city_name'] ?? ''),
        ]);
    }

    /**
     * ذخیره کد پستی سفارش
     */
    public function savePostalCode(Request $request, WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'postal_code' => 'required|string|min:10|max:11',
        ]);

        // تبدیل اعداد فارسی/عربی به انگلیسی و حذف کاراکترهای غیرعددی
        $postalCode = \Modules\Warehouse\Services\TapinService::normalizePostalCode($validated['postal_code']);
        if (strlen($postalCode) !== 10) {
            return response()->json(['success' => false, 'message' => 'کد پستی باید ۱۰ رقم باشد'], 422);
        }

        $wcData = is_array($order->wc_order_data) ? $order->wc_order_data : [];
        if (!isset($wcData['shipping'])) $wcData['shipping'] = [];
        $wcData['shipping']['postcode'] = $postalCode;
        if (!isset($wcData['billing'])) $wcData['billing'] = [];
        $wcData['billing']['postcode'] = $postalCode;
        $order->update(['wc_order_data' => $wcData]);

        OrderLog::log($order, OrderLog::ACTION_EDITED, 'کد پستی تغییر کرد: ' . $postalCode);

        return response()->json([
            'success' => true,
            'message' => 'کد پستی ذخیره شد',
        ]);
    }

    /**
     * ذخیره آدرس سفارش
     */
    public function saveAddress(Request $request, WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'address' => 'required|string|max:500',
        ]);

        $wcData = is_array($order->wc_order_data) ? $order->wc_order_data : [];
        if (!isset($wcData['shipping'])) $wcData['shipping'] = [];
        if (!isset($wcData['billing'])) $wcData['billing'] = [];

        $wcData['shipping']['state'] = $validated['state'] ?? '';
        $wcData['shipping']['city'] = $validated['city'] ?? '';
        $wcData['shipping']['address_1'] = $validated['address'];
        $wcData['billing']['state'] = $validated['state'] ?? '';
        $wcData['billing']['city'] = $validated['city'] ?? '';
        $wcData['billing']['address_1'] = $validated['address'];

        $order->update(['wc_order_data' => $wcData]);

        $fullAddress = implode('، ', array_filter([$validated['state'], $validated['city'], $validated['address']]));
        OrderLog::log($order, OrderLog::ACTION_EDITED, 'آدرس تغییر کرد: ' . $fullAddress);

        return response()->json([
            'success' => true,
            'message' => 'آدرس ذخیره شد',
        ]);
    }

    /**
     * ذخیره کارتن و وزن قبل از پرینت (مرحله pending)
     * وزن خودکار محاسبه میشه: وزن محصولات + وزن کارتن
     * بعد از تایید سفارش به وضعیت packed (در انتظار اسکن خروج) میره
     */
    public function confirmAndPrint(Request $request, WarehouseOrder $order)
    {
        if (!auth()->user()->can('manage-warehouse') && !auth()->user()->can('manage-permissions')) {
            abort(403);
        }

        $validated = $request->validate([
            'box_size_id' => 'required|exists:warehouse_box_sizes,id',
            'total_weight_with_box' => 'required|numeric|min:1',
            'postal_code' => 'nullable|string|max:10',
        ]);

        // بارگذاری کارتن انتخابی
        $boxSize = \Modules\Warehouse\Models\WarehouseBoxSize::findOrFail($validated['box_size_id']);

        // اعتبارسنجی وزن با ضریب خطا
        $order->load('items');
        $expectedWeight = $order->total_weight_grams + $boxSize->weight;
        $actualWeight = (float) $validated['total_weight_with_box'];
        $tolerance = (float) WarehouseSetting::get('weight_tolerance', '5');

        if ($expectedWeight > 0) {
            $diff = abs($actualWeight - $expectedWeight) / $expectedWeight * 100;
            if ($diff > $tolerance) {
                return response()->json([
                    'success' => false,
                    'message' => 'اختلاف وزن ' . round($diff, 1) . '% — مورد انتظار: ' . number_format($expectedWeight) . 'g، دریافتی: ' . number_format($actualWeight) . 'g',
                ]);
            }
        }

        $order->update([
            'box_size_id' => $validated['box_size_id'],
            'actual_weight' => $actualWeight,
        ]);

        // ذخیره کد پستی در wc_order_data اگه وارد شده (تبدیل اعداد فارسی/عربی)
        if (!empty($validated['postal_code'])) {
            $postalCode = \Modules\Warehouse\Services\TapinService::normalizePostalCode($validated['postal_code']);
            if ($postalCode) {
                $wcData = is_array($order->wc_order_data) ? $order->wc_order_data : [];
                if (!isset($wcData['shipping'])) $wcData['shipping'] = [];
                $wcData['shipping']['postcode'] = $postalCode;
                if (!isset($wcData['billing'])) $wcData['billing'] = [];
                $wcData['billing']['postcode'] = $postalCode;
                $order->update(['wc_order_data' => $wcData]);
            }
        }

        // سفارشات پستی مستقیم به ارسال شده، بقیه به انتظار اسکن خروج
        if ($order->shipping_type === 'post') {
            $order->updateStatus(WarehouseOrder::STATUS_SHIPPED);
        } else {
            $order->updateStatus(WarehouseOrder::STATUS_PACKED);
        }

        OrderLog::log($order, OrderLog::ACTION_EDITED, 'تایید کارتن و وزن — سفارش به ' . ($order->shipping_type === 'post' ? 'ارسال شده' : 'انتظار اسکن خروج') . ' رفت', [
            'box_size_id' => $validated['box_size_id'],
            'box_name' => $boxSize->name,
            'total_weight_with_box' => $actualWeight,
            'expected_weight' => $expectedWeight,
            'postal_code' => $validated['postal_code'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'print_url' => route('warehouse.print.invoice', $order),
            'show_url' => route('warehouse.show', $order),
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
