<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Enums\SmsTrigger;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderStatusLog;
use Modules\CRM\Models\Province;
use Modules\CRM\Models\Technician;
use Modules\CRM\Services\InvoiceService;
use Modules\CRM\Services\OrderSmsNotifier;

class OrderController extends Controller
{
    public function __construct(
        protected OrderSmsNotifier $smsNotifier,
        protected InvoiceService $invoiceService,
    ) {
    }

    public function index(Request $request)
    {
        $search = $request->string('q')->toString();
        $status = $request->string('status')->toString();
        $technicianId = $request->integer('technician_id');
        $provinceId = $request->integer('province_id');

        $orders = Order::with(['customer', 'technician', 'brand', 'device', 'province'])
            ->search($search)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($technicianId, fn ($q) => $q->where('technician_id', $technicianId))
            ->when($provinceId, fn ($q) => $q->where('province_id', $provinceId))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $technicians = Technician::active()->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        $provinces = Province::ordered()->get(['id', 'name']);

        return view('crm::orders.index', compact(
            'orders', 'technicians', 'provinces', 'search', 'status', 'technicianId', 'provinceId'
        ));
    }

    public function create(Request $request)
    {
        // اگر customer_id از مسیر جزئیات مشتری اومد، مشتری رو پیش‌گزینه کن
        $customerId = $request->integer('customer_id');
        $customer = $customerId ? Customer::find($customerId) : null;

        return view('crm::orders.create', [
            'customer' => $customer,
            'brands' => Brand::active()->ordered()->get(['id', 'name']),
            'devices' => Device::active()->ordered()->get(['id', 'name']),
            'provinces' => Province::ordered()->get(['id', 'name']),
            'cities' => collect(),
            'statuses' => OrderStatus::options(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateOrder($request);

        $order = DB::transaction(function () use ($validated) {
            $customer = Customer::findOrFail($validated['customer_id']);

            $order = Order::create([
                'order_code' => Order::generateOrderCode(),
                'customer_id' => $customer->id,
                'brand_id' => $validated['brand_id'] ?? null,
                'device_id' => $validated['device_id'] ?? null,
                // snapshot مشتری در زمان ثبت سفارش (مثل meta‌های سفارش در WP)
                'customer_name' => $customer->display_name,
                'customer_mobile' => $customer->mobile,
                'customer_phone' => $customer->phone,
                // آدرس به ازای هر سفارش جداگانه ثبت می‌شود (مثل WP)
                'province_id' => $validated['province_id'] ?? null,
                'city_id' => $validated['city_id'] ?? null,
                'address' => $validated['address'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'problem_title' => $validated['problem_title'] ?? null,
                'problem_description' => $validated['problem_description'] ?? null,
                'visit_scheduled_at' => $validated['visit_scheduled_at'] ?? null,
                'estimated_price' => $validated['estimated_price'] ?? null,
                'deposit' => $validated['deposit'] ?? 0,
                'status' => $validated['status'] ?? OrderStatus::New->value,
                'created_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            OrderStatusLog::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => $order->status instanceof OrderStatus ? $order->status->value : $order->status,
                'note' => 'ثبت اولیه سفارش',
                'changed_by' => auth()->id(),
                'created_at' => now(),
            ]);

            return $order;
        });

        $this->smsNotifier->notify($order, SmsTrigger::OrderCreated);

        return redirect()->route('crm.orders.show', $order)
            ->with('success', 'سفارش ثبت شد: ' . $order->order_code);
    }

    public function show(Order $order)
    {
        $order->load([
            'customer', 'brand', 'device', 'technician', 'province', 'city',
            'creator', 'items', 'statusLogs.changer',
        ]);

        return view('crm::orders.show', [
            'order' => $order,
            'technicians' => Technician::active()->ready()->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'statuses' => OrderStatus::options(),
        ]);
    }

    public function edit(Order $order)
    {
        $order->load(['customer']);

        return view('crm::orders.edit', [
            'order' => $order,
            'brands' => Brand::active()->ordered()->get(['id', 'name']),
            'devices' => Device::active()->ordered()->get(['id', 'name']),
            'provinces' => Province::ordered()->get(['id', 'name']),
            'cities' => $order->province_id
                ? City::where('province_id', $order->province_id)->ordered()->get(['id', 'name'])
                : collect(),
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $validated = $this->validateOrder($request, updating: true);

        $order->update([
            'brand_id' => $validated['brand_id'] ?? null,
            'device_id' => $validated['device_id'] ?? null,
            'province_id' => $validated['province_id'] ?? null,
            'city_id' => $validated['city_id'] ?? null,
            'address' => $validated['address'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'problem_title' => $validated['problem_title'] ?? null,
            'problem_description' => $validated['problem_description'] ?? null,
            'visit_scheduled_at' => $validated['visit_scheduled_at'] ?? null,
            'estimated_price' => $validated['estimated_price'] ?? null,
            'final_price' => $validated['final_price'] ?? null,
            'deposit' => $validated['deposit'] ?? 0,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('crm.orders.show', $order)->with('success', 'سفارش ویرایش شد.');
    }

    public function destroy(Order $order)
    {
        $order->delete();

        return redirect()->route('crm.orders.index')->with('success', 'سفارش حذف شد.');
    }

    // ───────────── تخصیص تکنسین ─────────────────────────────────
    public function assign(Request $request, Order $order)
    {
        $validated = $request->validate([
            'technician_id' => 'required|exists:crm_technicians,id',
        ]);

        $previousStatus = $order->status instanceof OrderStatus ? $order->status->value : $order->status;

        $order->update([
            'technician_id' => $validated['technician_id'],
            'status' => OrderStatus::Coordinated->value,
            'assigned_at' => now(),
        ]);

        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $previousStatus,
            'to_status' => OrderStatus::Coordinated->value,
            'note' => 'تخصیص تکنسین',
            'changed_by' => auth()->id(),
            'created_at' => now(),
        ]);

        // اطلاع به مشتری و تکنسین
        $order->refresh()->load('technician');
        $this->smsNotifier->notify($order, SmsTrigger::OrderAssigned);
        $this->smsNotifier->notify($order, SmsTrigger::OrderAssignedTech);

        return back()->with('success', 'تکنسین تخصیص داده شد.');
    }

    public function unassign(Order $order)
    {
        if (! $order->technician_id) {
            return back();
        }

        $previousStatus = $order->status instanceof OrderStatus ? $order->status->value : $order->status;

        $order->update([
            'technician_id' => null,
            'status' => OrderStatus::New->value,
            'assigned_at' => null,
        ]);

        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $previousStatus,
            'to_status' => OrderStatus::New->value,
            'note' => 'لغو تخصیص تکنسین',
            'changed_by' => auth()->id(),
            'created_at' => now(),
        ]);

        return back()->with('success', 'تکنسین از این سفارش برداشته شد.');
    }

    // ───────────── تغییر وضعیت ─────────────────────────────────
    public function changeStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['required', 'string'],
            'note' => 'nullable|string|max:1000',
        ]);

        $newStatus = OrderStatus::tryFrom($validated['status']);
        if (! $newStatus) {
            return back()->with('error', 'وضعیت نامعتبر.');
        }

        $previousStatus = $order->status instanceof OrderStatus ? $order->status->value : $order->status;
        if ($previousStatus === $newStatus->value) {
            return back()->with('error', 'وضعیت قبلاً همین بوده.');
        }

        $updates = ['status' => $newStatus->value];

        if ($newStatus === OrderStatus::Completed) {
            $updates['completed_at'] = now();
        }
        if (in_array($newStatus, [OrderStatus::Cancelled, OrderStatus::Declined])) {
            $updates['cancel_reason'] = $validated['note'] ?? null;
        }

        $order->update($updates);

        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $previousStatus,
            'to_status' => $newStatus->value,
            'note' => $validated['note'] ?? null,
            'changed_by' => auth()->id(),
            'created_at' => now(),
        ]);

        // تولید خودکار فاکتور در تکمیل سفارش (idempotent)
        if ($newStatus === OrderStatus::Completed) {
            $this->invoiceService->generateForOrder($order->refresh(), auth()->id());
        }

        // اگر این وضعیت جدید قالب SMS دارد، خودکار ارسال کن
        if ($trigger = SmsTrigger::fromOrderStatus($newStatus)) {
            $this->smsNotifier->notify($order->refresh(), $trigger);
        }

        return back()->with('success', 'وضعیت به "' . $newStatus->label() . '" تغییر کرد.');
    }

    // ───────────── داشبورد تکنسین ─────────────────────────────
    public function myOrders(Request $request)
    {
        $technician = Technician::where('user_id', auth()->id())->first();
        if (! $technician) {
            abort(403, 'شما به عنوان تکنسین فعال ثبت نشده‌اید.');
        }

        $status = $request->string('status')->toString();

        $orders = Order::with(['customer', 'brand', 'device', 'province', 'city'])
            ->forTechnician($technician->id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('crm::orders.my', [
            'orders' => $orders,
            'technician' => $technician,
            'status' => $status,
            'statuses' => OrderStatus::options(),
        ]);
    }

    // ───────────── Validation ───────────────────────────────────
    protected function validateOrder(Request $request, bool $updating = false): array
    {
        $rules = [
            'brand_id' => 'nullable|exists:crm_brands,id',
            'device_id' => 'nullable|exists:crm_devices,id',
            'province_id' => 'nullable|exists:crm_provinces,id',
            'city_id' => 'nullable|exists:crm_cities,id',
            'address' => 'nullable|string|max:2000',
            'postal_code' => 'nullable|string|max:20',
            'problem_title' => 'nullable|string|max:255',
            'problem_description' => 'nullable|string|max:5000',
            'visit_scheduled_at' => 'nullable|date',
            'estimated_price' => 'nullable|integer|min:0',
            'deposit' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:5000',
        ];

        if ($updating) {
            $rules['final_price'] = 'nullable|integer|min:0';
        } else {
            $rules['customer_id'] = 'required|exists:crm_customers,id';
            $rules['status'] = 'nullable|string|in:' . implode(',', array_keys(OrderStatus::options()));
        }

        return $request->validate($rules);
    }
}
