<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Enums\SmsTrigger;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderStatusLog;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\WalletTransaction;
use Modules\CRM\Services\InvoiceService;
use Modules\CRM\Services\OrderSmsNotifier;

/**
 * کنترلر پنل شخصی تکنسین.
 *
 * همه متدها ابتدا رکورد Technician مرتبط با کاربر لاگین‌کرده را پیدا می‌کنند
 * و اطلاعات را به همان تکنسین محدود می‌کنند. اگر کاربر تکنسین نباشد، 403.
 */
class TechDashboardController extends Controller
{
    public function __construct(
        protected OrderSmsNotifier $smsNotifier,
        protected InvoiceService $invoiceService,
    ) {
    }

    protected function currentTechnician(): Technician
    {
        $tech = Technician::where('user_id', auth()->id())->first();

        if (! $tech) {
            abort(403, 'شما به عنوان تکنسین فعال ثبت نشده‌اید.');
        }

        return $tech;
    }

    public function index()
    {
        $tech = $this->currentTechnician();

        $orderQuery = Order::forTechnician($tech->id);

        $stats = [
            'total' => (clone $orderQuery)->count(),
            'pending' => (clone $orderQuery)->where('status', OrderStatus::Assigned->value)->count(),
            'in_progress' => (clone $orderQuery)->where('status', OrderStatus::InProgress->value)->count(),
            'completed' => (clone $orderQuery)->where('status', OrderStatus::Completed->value)->count(),
        ];

        $recentOrders = (clone $orderQuery)
            ->with(['customer', 'brand', 'device'])
            ->latest()
            ->limit(5)
            ->get();

        return view('crm::tech.dashboard', [
            'technician' => $tech,
            'stats' => $stats,
            'recentOrders' => $recentOrders,
        ]);
    }

    public function wallet(Request $request)
    {
        $tech = $this->currentTechnician();

        $transactions = WalletTransaction::with(['order', 'invoice'])
            ->where('technician_id', $tech->id)
            ->latest()
            ->paginate(20);

        return view('crm::tech.wallet', [
            'technician' => $tech,
            'transactions' => $transactions,
        ]);
    }

    public function invoices(Request $request)
    {
        $tech = $this->currentTechnician();

        $invoices = Invoice::with(['order', 'customer'])
            ->where('technician_id', $tech->id)
            ->latest()
            ->paginate(20);

        return view('crm::tech.invoices', [
            'technician' => $tech,
            'invoices' => $invoices,
        ]);
    }

    public function profile()
    {
        $tech = $this->currentTechnician();
        $tech->load(['province', 'city']);

        return view('crm::tech.profile', ['technician' => $tech]);
    }

    // ───────── دیدن/تغییر وضعیت سفارش‌های خود تکنسین ─────────

    public function showOrder(Order $order)
    {
        $tech = $this->currentTechnician();
        if ($order->technician_id !== $tech->id) {
            abort(403, 'این سفارش به شما تخصیص داده نشده.');
        }

        $order->load([
            'customer', 'brand', 'device', 'technician', 'province', 'city',
            'creator', 'items', 'statusLogs.changer',
        ]);

        return view('crm::tech.order_show', [
            'order' => $order,
            'allowedTransitions' => $this->allowedTransitionsFor($order->status),
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $tech = $this->currentTechnician();
        if ($order->technician_id !== $tech->id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'required|string',
            'note' => 'nullable|string|max:1000',
        ]);

        $newStatus = OrderStatus::tryFrom($validated['status']);
        if (! $newStatus) {
            return back()->with('error', 'وضعیت نامعتبر.');
        }

        // تکنسین فقط می‌تواند بین وضعیت‌های مجاز حرکت کند
        $allowed = $this->allowedTransitionsFor($order->status);
        if (! in_array($newStatus, $allowed, true)) {
            return back()->with('error', 'شما اجازه تغییر به این وضعیت را ندارید.');
        }

        $previousStatus = $order->status->value;

        $updates = ['status' => $newStatus->value];
        if ($newStatus === OrderStatus::Completed) {
            $updates['completed_at'] = now();
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

        // فاکتور خودکار موقع تکمیل (مشابه مسیر ادمین)
        if ($newStatus === OrderStatus::Completed) {
            $this->invoiceService->generateForOrder($order->refresh(), auth()->id());
        }

        // SMS خودکار
        if ($trigger = SmsTrigger::fromOrderStatus($newStatus)) {
            $this->smsNotifier->notify($order->refresh(), $trigger);
        }

        return back()->with('success', 'وضعیت به "' . $newStatus->label() . '" تغییر کرد.');
    }

    /**
     * مسیرهای مجاز تغییر وضعیت توسط تکنسین (سختگیرانه).
     *
     * تکنسین هرگز نمی‌تواند لغو/رد کند؛ آن‌ها فقط از سمت ادمین/اپراتور.
     */
    protected function allowedTransitionsFor(OrderStatus $current): array
    {
        return match ($current) {
            OrderStatus::Assigned => [OrderStatus::InProgress],
            OrderStatus::InProgress => [OrderStatus::Completed],
            OrderStatus::Completed => [OrderStatus::Delivered],
            default => [],
        };
    }
}
